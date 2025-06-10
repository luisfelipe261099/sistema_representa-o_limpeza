<?php
// Inicia o output buffering para evitar problemas de "headers already sent"
ob_start();
session_start();

// ===================================================================================
// 1. SETUP INICIAL E INCLUSÃO DE BIBLIOTECAS
// ===================================================================================

// Habilita exibição de erros para depuração (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    if (ob_get_length()) ob_end_clean();
    header("location: index.php");
    exit;
}

// Inclui PHPMailer e conexão com o banco
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';
require_once 'includes/db_connect.php';

// ===================================================================================
// 2. DEFINIÇÃO DE FUNÇÕES AUXILIARES
// ===================================================================================

function registrarHistoricoOrcamento($conn, $orcamento_id, $status_anterior, $status_novo, $observacoes = '') {
    if ($status_anterior === $status_novo && empty($observacoes)) return true;
    $usuario_id = $_SESSION['id'] ?? null;
    $status_anterior_db = $status_anterior ?? 'criado';
    
    $sql = "INSERT INTO historico_orcamentos (orcamento_id, status_anterior, status_novo, observacoes, data_alteracao, usuario_id)
            VALUES (?, ?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssi", $orcamento_id, $status_anterior_db, $status_novo, $observacoes, $usuario_id);
    return $stmt->execute();
}

function gerarPDFComoString($orcamento_id, $conn) {
    try {
        $sql_orcamento = "SELECT o.*, c.nome AS nome_cliente, c.email FROM orcamentos o JOIN clientes c ON o.cliente_id = c.id WHERE o.id = ?";
        $stmt = $conn->prepare($sql_orcamento);
        $stmt->bind_param("i", $orcamento_id);
        $stmt->execute();
        $orcamento = $stmt->get_result()->fetch_assoc();
        
        if (!$orcamento) throw new Exception("Orçamento não encontrado");

        $sql_itens = "SELECT i.*, p.nome AS nome_produto FROM itens_orcamento i JOIN produtos p ON i.produto_id = p.id WHERE i.orcamento_id = ?";
        $stmt_itens = $conn->prepare($sql_itens);
        $stmt_itens->bind_param("i", $orcamento_id);
        $stmt_itens->execute();
        $itens = $stmt_itens->get_result();

        require_once('vendor/setasign/fpdf/fpdf.php');
        
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',16);
        $pdf->Cell(0,10,'Orcamento N: ' . $orcamento['id'],0,1,'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'Informacoes do Orcamento',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->Cell(40,7,'Cliente:',0);
        $pdf->Cell(0,7,$orcamento['nome_cliente'],0,1);
        $pdf->Cell(40,7,'Data:',0);
        $pdf->Cell(0,7,date('d/m/Y', strtotime($orcamento['data_orcamento'])),0,1);
        $pdf->Cell(40,7,'Status:',0);
        $pdf->Cell(0,7,ucfirst($orcamento['status_orcamento']),0,1);
        $pdf->Ln(10);
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'Itens do Orcamento',0,1);
        $pdf->SetFillColor(230,230,230);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(90,7,'Produto',1,0,'C',true);
        $pdf->Cell(25,7,'Quantidade',1,0,'C',true);
        $pdf->Cell(35,7,'Valor Unit.',1,0,'C',true);
        $pdf->Cell(40,7,'Subtotal',1,1,'C',true);
        $pdf->SetFont('Arial','',10);
        
        while ($item = $itens->fetch_assoc()) {
            $preco = $item['preco_unitario'] ?? 0;
            $subtotal = $item['quantidade'] * $preco;
            $pdf->Cell(90,7,iconv('UTF-8', 'ISO-8859-1//IGNORE', $item['nome_produto']),1,0);
            $pdf->Cell(25,7,$item['quantidade'],1,0,'C');
            $pdf->Cell(35,7,'R$ '.number_format($preco, 2, ',', '.'),1,0,'R');
            $pdf->Cell(40,7,'R$ '.number_format($subtotal, 2, ',', '.'),1,1,'R');
        }
        
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell(150,7,'Valor Total:',1,0,'R');
        $pdf->Cell(40,7,'R$ '.number_format($orcamento['valor_total'], 2, ',', '.'),1,1,'R');
        
        if (!empty($orcamento['observacoes'])) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial','B',12);
            $pdf->Cell(0,7,'Observacoes:',0,1);
            $pdf->SetFont('Arial','',11);
            $pdf->MultiCell(0,7,iconv('UTF-8', 'ISO-8859-1//IGNORE', $orcamento['observacoes']),0);
        }
        
        return $pdf->Output('S');
    } catch (Exception $e) {
        error_log("Erro ao gerar PDF do orçamento #$orcamento_id: " . $e->getMessage());
        throw $e;
    }
}

// ===================================================================================
// 3. BLOCO DE PROCESSAMENTO DE AÇÕES (POST e GET)
// ===================================================================================

// --- NOVA AÇÃO DE EXCLUIR ORÇAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $orcamento_id = intval($_GET['id']);

    $conn->begin_transaction();
    try {
        // 1. Excluir itens do orçamento
        $stmt_itens = $conn->prepare("DELETE FROM itens_orcamento WHERE orcamento_id = ?");
        $stmt_itens->bind_param("i", $orcamento_id);
        $stmt_itens->execute();

        // 2. Excluir histórico do orçamento
        $stmt_hist = $conn->prepare("DELETE FROM historico_orcamentos WHERE orcamento_id = ?");
        $stmt_hist->bind_param("i", $orcamento_id);
        $stmt_hist->execute();

        // 3. Excluir o orçamento principal
        $stmt_orc = $conn->prepare("DELETE FROM orcamentos WHERE id = ?");
        $stmt_orc->bind_param("i", $orcamento_id);
        $stmt_orc->execute();

        $conn->commit();
        $_SESSION['message'] = "Orçamento #{$orcamento_id} e seus dados foram excluídos com sucesso.";
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Erro ao excluir o orçamento: " . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
        error_log("Erro na exclusão do orçamento #$orcamento_id: " . $e->getMessage());
    }
    
    if (ob_get_length()) ob_end_clean();
    header("Location: orcamentos.php");
    exit;
}

// Ação de Mudar Status (via POST do Modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $orcamento_id = intval($_POST['orcamento_id']);
    $novo_status = $_POST['novo_status'];
    $observacoes = trim($_POST['observacoes']);

    $stmt_get = $conn->prepare("SELECT status_orcamento FROM orcamentos WHERE id = ?");
    $stmt_get->bind_param("i", $orcamento_id);
    $stmt_get->execute();
    $status_anterior = $stmt_get->get_result()->fetch_assoc()['status_orcamento'];

    $stmt_update = $conn->prepare("UPDATE orcamentos SET status_orcamento = ? WHERE id = ?");
    $stmt_update->bind_param("si", $novo_status, $orcamento_id);
    if ($stmt_update->execute()) {
        registrarHistoricoOrcamento($conn, $orcamento_id, $status_anterior, $novo_status, $observacoes);
        $_SESSION['message'] = "Status do orçamento #{$orcamento_id} atualizado para '{$novo_status}'.";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Erro ao atualizar o status do orçamento.";
        $_SESSION['message_type'] = 'danger';
    }
    
    if (ob_get_length()) ob_end_clean();
    header("Location: orcamentos.php");
    exit;
}

// Ação de Enviar E-mail (via GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'send_email') {
    $orcamento_id = intval($_GET['id']);

    // Buscar dados do cliente e orçamento
    $stmt_cli = $conn->prepare("SELECT c.nome, c.email, o.status_orcamento FROM clientes c JOIN orcamentos o ON c.id = o.cliente_id WHERE o.id = ?");
    $stmt_cli->bind_param("i", $orcamento_id);
    $stmt_cli->execute();
    $data = $stmt_cli->get_result()->fetch_assoc();

    if ($data && !empty($data['email'])) {
        $mail = new PHPMailer(true);
        try {
            // Carrega configurações do SMTP do arquivo de configuração
            $email_config = include 'includes/email_config.php';

            // Configurações do servidor SMTP
            $mail->isSMTP();
            $mail->Host = $email_config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $email_config['username'];
            $mail->Password = $email_config['password'];

            // Configurar encryption baseado no arquivo de configuração
            if ($email_config['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = $email_config['port'];
            $mail->CharSet = 'UTF-8';

            // Configurações do remetente e destinatário
            $mail->setFrom($email_config['from_email'], $email_config['from_name']);
            $mail->addAddress($data['email'], $data['nome']);

            // Gerar e anexar PDF
            try {
                $pdf_content = gerarPDFComoString($orcamento_id, $conn);
                $mail->addStringAttachment($pdf_content, 'Orcamento_'.$orcamento_id.'.pdf', 'base64', 'application/pdf');
            } catch (Exception $pdf_error) {
                error_log("Erro ao gerar PDF para orçamento #$orcamento_id: " . $pdf_error->getMessage());
                throw new Exception("Erro ao gerar PDF do orçamento: " . $pdf_error->getMessage());
            }

            // Configurar conteúdo do email
            $mail->isHTML(true);
            $mail->Subject = 'Seu Orçamento Nº ' . $orcamento_id . ' - LFM Tecnologia';

            $mail->Body = "
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h2>LFM Tecnologia</h2>
                </div>
                <div class='content'>
                    <p>Olá, <strong>" . htmlspecialchars($data['nome']) . "</strong>!</p>

                    <p>Esperamos que esteja bem!</p>

                    <p>Segue em anexo o orçamento solicitado (Nº <strong>$orcamento_id</strong>).</p>

                    <p>Caso tenha alguma dúvida ou precise de esclarecimentos, não hesite em entrar em contato conosco.</p>

                    <p>Aguardamos seu retorno!</p>

                    <br>
                    <p>Atenciosamente,<br>
                    <strong>Karla Wollinge</strong><br>
                    LFM Tecnologia</p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático. Por favor, não responda diretamente a este e-mail.</p>
                    <p>Para entrar em contato, utilize: desenvolvimento@lfmtecnologia.com</p>
                </div>
            </body>
            </html>";

            $mail->AltBody = "Olá, " . htmlspecialchars($data['nome']) . "!\n\n" .
                           "Segue em anexo o orçamento solicitado (Nº $orcamento_id).\n\n" .
                           "Caso tenha alguma dúvida, entre em contato conosco.\n\n" .
                           "Atenciosamente,\nKarla Wollinge\nLFM Tecnologia";

            // Enviar email
            if ($mail->send()) {
                // Log de sucesso
                error_log("EMAIL ENVIADO COM SUCESSO - Orçamento #$orcamento_id para {$data['email']} em " . date('Y-m-d H:i:s'));

                // Registrar no histórico
                registrarHistoricoOrcamento($conn, $orcamento_id, $data['status_orcamento'], $data['status_orcamento'], "Orçamento enviado por e-mail para {$data['email']} em " . date('d/m/Y H:i:s'));

                $_SESSION['message'] = "Orçamento enviado com sucesso para " . htmlspecialchars($data['email']) . "!";
                $_SESSION['message_type'] = 'success';
            } else {
                throw new Exception("Falha no envio do email");
            }

        } catch (Exception $e) {
            // Log detalhado do erro
            error_log("ERRO NO ENVIO DE EMAIL - Orçamento #$orcamento_id: " . $e->getMessage());
            error_log("Dados do cliente: " . print_r($data, true));
            error_log("Configurações de email: Host=" . ($email_config['host'] ?? 'N/A') . ", Username=" . ($email_config['username'] ?? 'N/A') . ", Port=" . ($email_config['port'] ?? 'N/A'));

            $_SESSION['message'] = "Erro ao enviar e-mail: " . htmlspecialchars($e->getMessage());
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        if (!$data) {
            $_SESSION['message'] = "Orçamento não encontrado.";
        } else {
            $_SESSION['message'] = "Cliente não possui e-mail cadastrado. Por favor, atualize o cadastro do cliente.";
        }
        $_SESSION['message_type'] = 'warning';
    }

    if (ob_get_length()) ob_end_clean();
    header("Location: orcamentos.php");
    exit;
}

// ===================================================================================
// 4. BLOCO DE BUSCA DE DADOS E MENSAGENS FLASH
// ===================================================================================



$sql_select_orcamentos = "SELECT o.id, c.nome AS nome_cliente, o.data_orcamento, o.valor_total, o.status_orcamento
                          FROM orcamentos o LEFT JOIN clientes c ON o.cliente_id = c.id
                          ORDER BY o.id DESC";
$result_orcamentos = $conn->query($sql_select_orcamentos);
if (!$result_orcamentos) die("Erro na consulta de orçamentos: " . $conn->error);

$orcamentos_data = $result_orcamentos->fetch_all(MYSQLI_ASSOC);
$total_orcamentos = count($orcamentos_data);
$stats = ['pendentes' => 0, 'aprovados' => 0, 'rejeitados' => 0, 'convertidos' => 0];
foreach ($orcamentos_data as $row) {
    if ($row['status_orcamento'] == 'pendente') $stats['pendentes']++;
    if ($row['status_orcamento'] == 'aprovado') $stats['aprovados']++;
    if ($row['status_orcamento'] == 'rejeitado') $stats['rejeitados']++;
    if ($row['status_orcamento'] == 'convertido_venda') $stats['convertidos']++;
}

// ===================================================================================
// 5. RENDERIZAÇÃO DA PÁGINA HTML
// ===================================================================================

include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title"><i class="fas fa-file-invoice-dollar"></i> Gerenciamento de Orçamentos</h1>
</div>



<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3"><div class="stats-card primary"><div class="stats-icon primary"><i class="fas fa-file-invoice-dollar"></i></div><div class="stats-value"><?php echo $total_orcamentos; ?></div><div class="stats-label">Total</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card warning"><div class="stats-icon warning"><i class="fas fa-clock"></i></div><div class="stats-value"><?php echo $stats['pendentes']; ?></div><div class="stats-label">Pendentes</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card success"><div class="stats-icon success"><i class="fas fa-check-circle"></i></div><div class="stats-value"><?php echo $stats['aprovados']; ?></div><div class="stats-label">Aprovados</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card info"><div class="stats-icon info"><i class="fas fa-exchange-alt"></i></div><div class="stats-value"><?php echo $stats['convertidos']; ?></div><div class="stats-label">Convertidos</div></div></div>
</div>

<div class="modern-card">
    <div class="card-header-modern d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Lista de Orçamentos</span>
        <a href="criar_orcamento.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-2"></i> Novo Orçamento</a>
    </div>
    <div class="card-body-modern">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th><th>Cliente</th><th>Data</th><th>Valor</th><th>Status</th><th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_orcamentos > 0): ?>
                        <?php foreach ($orcamentos_data as $row): ?>
                            <?php
                            $status_class = '';
                            switch ($row['status_orcamento']) {
                                case 'aprovado': $status_class = 'bg-success'; break;
                                case 'pendente': $status_class = 'bg-warning text-dark'; break;
                                case 'rejeitado': $status_class = 'bg-danger'; break;
                                case 'convertido_venda': $status_class = 'bg-info'; break;
                                default: $status_class = 'bg-secondary';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['nome_cliente']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['data_orcamento'])); ?></td>
                                <td>R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status_orcamento']))); ?></span></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="detalhes_orcamento.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                                        <a href="gerar_pdf_orcamento.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" title="Baixar PDF" target="_blank"><i class="fas fa-file-pdf"></i></a>
                                        <a href="enviar_orcamento.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Preparar E-mail"><i class="fas fa-envelope"></i></a>
                                        
                                        <?php if ($row['status_orcamento'] == 'pendente' || $row['status_orcamento'] == 'aprovado'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" title="Mudar Status" data-bs-toggle="modal" data-bs-target="#statusModal" data-id="<?php echo $row['id']; ?>">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['status_orcamento'] == 'aprovado'): ?>
                                            <a href="registrar_venda.php?from_orcamento_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="Converter para Venda"><i class="fas fa-exchange-alt"></i></a>
                                        <?php endif; ?>
                                        
                                        <a href="orcamentos.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" title="Excluir Orçamento" onclick="return confirm('Tem certeza que deseja excluir este orçamento e todos os seus itens? Esta ação não pode ser desfeita.');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">Nenhum orçamento registrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="orcamentos.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Alterar Status do Orçamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="orcamento_id" id="modal_orcamento_id">
                    
                    <div class="mb-3">
                        <label for="novo_status" class="form-label">Novo Status</label>
                        <select name="novo_status" id="novo_status" class="form-select" required>
                            <option value="aprovado">Aprovado</option>
                            <option value="rejeitado">Rejeitado</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações (Opcional)</label>
                        <textarea name="observacoes" id="observacoes" class="form-control" rows="3" placeholder="Ex: Cliente aprovou por telefone."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alteração</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php
$conn->close();
ob_end_flush();
include_once 'includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var statusModal = document.getElementById('statusModal');
    statusModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var orcamentoId = button.getAttribute('data-id');
        var modalInput = statusModal.querySelector('#modal_orcamento_id');
        modalInput.value = orcamentoId;
    });
});
</script>