<?php
// Inicia o output buffering e a sessão
ob_start();
session_start();

// Habilita a exibição de erros para facilitar a depuração.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Dependências
require 'vendor/autoload.php';
require_once 'includes/db_connect.php';
require_once('vendor/setasign/fpdf/fpdf.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    if (ob_get_length()) ob_end_clean();
    header("location: index.php");
    exit;
}

// Verifica se o ID do orçamento foi fornecido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'ID do orçamento inválido.'];
    header('Location: orcamentos.php');
    exit;
}
$orcamento_id = intval($_GET['id']);


// ===================================================================================
// 2. DEFINIÇÃO DA CLASSE E FUNÇÕES AUXILIARES
// ===================================================================================

/**
 * A classe ModernPDF, copiada DIRETAMENTE do seu arquivo 'gerar_pdf_orcamento.php'
 * para garantir que a aparência seja idêntica.
 */
class ModernPDF extends FPDF {
    private $primaryColor = [28, 79, 140];
    private $accentColor = [13, 110, 253];
    private $lightGray = [248, 249, 250];
    private $darkGray = [52, 58, 64];
    private $successColor = [25, 135, 84];
    
    function convertToLatin1($text) { return iconv('UTF-8', 'ISO-8859-1//IGNORE', $text ?? ''); }
    function Header() {
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Rect(0, 0, 210, 45, 'F');
        $this->SetFillColor($this->accentColor[0], $this->accentColor[1], $this->accentColor[2]);
        $this->Rect(0, 40, 210, 5, 'F');
        
        $logo_width = 0;
        if (file_exists('logo.jpeg')) {
            $this->Image('logo.jpeg', 15, 8, 30, 0);
            $logo_width = 35;
        }
        
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 20);
        $this->SetXY(15 + $logo_width, 12);
        $this->Cell(0, 8, $this->convertToLatin1('Karla Wollinger'), 0, 1, 'L');
        $this->SetFont('Arial', '', 10);
        $this->SetXY(15 + $logo_width, 22);
        $this->Cell(0, 5, $this->convertToLatin1('Representação Comercial'), 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetXY(120, 10);
        $this->Cell(0, 4, 'karlawollinger2@gmail.com', 0, 1, 'R');
        $this->SetXY(120, 16);
        $this->Cell(0, 4, '(41) 99859-3242', 0, 1, 'R');
        $this->SetXY(120, 22);
        $this->Cell(0, 4, 'CNPJ : 30.459.625/0001-87', 0, 1, 'R');
        $this->SetTextColor(0, 0, 0);
        $this->Ln(25);
    }
    function Footer() {
        $this->SetY(-20);
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(3);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->darkGray[0], $this->darkGray[1], $this->darkGray[2]);
        $this->SetX(15);
        $this->Cell(90, 4, $this->convertToLatin1('karla wollinger - Todos os direitos reservados'), 0, 0, 'L');
        $this->Cell(90, 4, $this->convertToLatin1('Página ') . $this->PageNo() . ' de {nb}', 0, 1, 'R');
        $this->Ln(1);
        $this->SetX(15);
        $this->Cell(0, 4, $this->convertToLatin1('Documento gerado em: ') . date('d/m/Y H:i'), 0, 0, 'L');
    }
    function ShadowBox($x, $y, $w, $h, $title, $content, $bgColor = null) {
        $this->SetFillColor(200, 200, 200);
        $this->Rect($x + 0.5, $y + 0.5, $w, $h, 'F');
        if ($bgColor) { $this->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]); } 
        else { $this->SetFillColor(255, 255, 255); }
        $this->SetDrawColor(220, 220, 220);
        $this->Rect($x, $y, $w, $h, 'DF');
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetXY($x + 3, $y + 3);
        $this->Cell($w - 6, 6, $this->convertToLatin1($title), 0, 1, 'L');
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Line($x + 3, $y + 10, $x + $w - 3, $y + 10);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(50, 50, 50);
        $this->SetXY($x + 3, $y + 13);
        $this->MultiCell($w - 6, 4.5, $this->convertToLatin1($content), 0, 'L');
    }
    function ModernTable($headers, $data, $widths) {
        $start_x = 15;
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->SetDrawColor(255, 255, 255);
        $current_x = $start_x;
        for ($i = 0; $i < count($headers); $i++) {
            $this->SetXY($current_x, $this->GetY());
            $this->Cell($widths[$i], 10, $this->convertToLatin1($headers[$i]), 1, 0, 'C', true);
            $current_x += $widths[$i];
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->SetDrawColor(220, 220, 220);
        $fill = false;
        foreach ($data as $row) {
            $this->SetFillColor($fill ? $this->lightGray[0] : 255, $fill ? $this->lightGray[1] : 255, $fill ? $this->lightGray[2] : 255);
            $cell_height = 8;
            if (isset($row[1]) && substr_count($row[1], "\n") > 0) {
                $lines = substr_count($row[1], "\n") + 1;
                $cell_height = max(8, $lines * 4);
            }
            if ($this->GetY() + $cell_height > 260) { $this->AddPage(); }
            $start_y = $this->GetY();
            $current_x = $start_x;
            for ($i = 0; $i < count($row); $i++) {
                $this->SetXY($current_x, $start_y);
                $align = 'L';
                if (in_array($i, [0, 3])) $align = 'C';
                if ($i >= 4) $align = 'R';
                if ($i == 1) {
                    $this->MultiCell($widths[$i], 4, $this->convertToLatin1($row[$i]), 1, $align, true);
                } else {
                    $this->Cell($widths[$i], $cell_height, $this->convertToLatin1($row[$i]), 1, 0, $align, true);
                }
                $current_x += $widths[$i];
            }
            $this->SetY($start_y + $cell_height);
            $fill = !$fill;
        }
    }
}

/**
 * Função principal que busca dados, constrói e retorna o PDF como uma string.
 */
function gerarOrcamentoPDFString($orcamento_id, $conn) {
    try {
        // 1. BUSCAR DADOS
        $sql_orcamento = "SELECT o.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone, c.endereco, c.cidade, c.estado, c.cep, c.cpf_cnpj, c.tipo_pessoa FROM orcamentos o LEFT JOIN clientes c ON o.cliente_id = c.id WHERE o.id = ?";
        $stmt_orcamento = $conn->prepare($sql_orcamento);
        $stmt_orcamento->bind_param("i", $orcamento_id);
        $stmt_orcamento->execute();
        $orcamento = $stmt_orcamento->get_result()->fetch_assoc();
        if (!$orcamento) throw new Exception("Orçamento #{$orcamento_id} não encontrado.");

        $sql_itens = "SELECT io.*, p.nome as produto_nome, p.sku as codigo, p.descricao, e.nome_empresa, e.logo_empresa FROM itens_orcamento io LEFT JOIN produtos p ON io.produto_id = p.id LEFT JOIN empresas_representadas e ON p.empresa_id = e.id WHERE io.orcamento_id = ?";
        $stmt_itens = $conn->prepare($sql_itens);
        $stmt_itens->bind_param("i", $orcamento_id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        $itens = [];
        $empresas_logos = [];
        while ($item = $result_itens->fetch_assoc()) {
            if (!empty($item['logo_empresa']) && !in_array($item['logo_empresa'], $empresas_logos)) $empresas_logos[] = $item['logo_empresa'];
            $itens[] = $item;
        }
        if (!isset($orcamento['valor_total'])) {
            $orcamento['valor_total'] = 0;
            foreach ($itens as $item) $orcamento['valor_total'] += ($item['quantidade'] ?? 1) * ($item['preco_unitario'] ?? 0);
        }

        // 2. CONSTRUIR PDF (lógica idêntica ao seu script original)
        $pdf = new ModernPDF('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(28, 79, 140);
        $pdf->Cell(0, 12, $pdf->convertToLatin1('PROPOSTA COMERCIAL'), 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 8, $pdf->convertToLatin1('Pedido #') . str_pad($orcamento['id'], 6, '0', STR_PAD_LEFT), 0, 1, 'C');
        $pdf->SetDrawColor(28, 79, 140);
        $pdf->SetLineWidth(1);
        $pdf->Line(80, $pdf->GetY() + 2, 130, $pdf->GetY() + 2);
        $pdf->Ln(15);

        // Caixas de Informação
        $cliente_nome = $orcamento['cliente_nome'] ?? 'Cliente não especificado';
        $client_details = "Cliente: " . $cliente_nome . "\n";
        if (!empty($orcamento['tipo_pessoa'])) $client_details .= "Tipo: " . ($orcamento['tipo_pessoa'] === 'fisica' ? 'Pessoa Física' : 'Pessoa Jurídica') . "\n";
        if (!empty($orcamento['cpf_cnpj'])) $client_details .= (($orcamento['tipo_pessoa'] === 'juridica') ? 'CNPJ' : 'CPF') . ": " . $orcamento['cpf_cnpj'] . "\n";
        if (!empty($orcamento['cliente_email'])) $client_details .= "Email: " . $orcamento['cliente_email'] . "\n";
        if (!empty($orcamento['telefone'])) $client_details .= "Telefone: " . $orcamento['telefone'] . "\n";
        if (!empty($orcamento['endereco'])) $client_details .= "Endereco: " . $orcamento['endereco'] . ", " . $orcamento['cidade'] . " - " . $orcamento['estado'];
        
        $budget_details = "Data de Emissao: " . date('d/m/Y', strtotime($orcamento['data_orcamento'])) . "\n";
        $budget_details .= "Status: " . ucfirst($orcamento['status_orcamento']) . "\n";
        $budget_details .= "Validade: 30 dias\n";
        $budget_details .= "Condicoes: A combinar\n";
        $budget_details .= "Responsavel: Equipe Comercial";
        
        $pdf->ShadowBox(15, $pdf->GetY(), 85, 45, 'DADOS DO CLIENTE', $client_details);
        $pdf->ShadowBox(110, $pdf->GetY(), 85, 45, 'DADOS DO ORÇAMENTO', $budget_details);
        $pdf->SetY($pdf->GetY() + 55);

        // Tabela de Itens
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(28, 79, 140);
        $pdf->Cell(0, 10, $pdf->convertToLatin1('ITENS DA PROPOSTA'), 0, 1, 'L');
        $pdf->Ln(5);
        $headers = ['Item', 'Produto/Servico', 'Empresa', 'Qtd', 'Valor Unit.', 'Subtotal'];
        $widths = [15, 65, 30, 15, 25, 30];
        $table_data = [];
        $item_num = 1;
        foreach ($itens as $item) {
            $produto_info = $item['produto_nome'] ?? 'Produto s/ nome';
            if (!empty($item['codigo'])) $produto_info .= "\n(Ref: " . $item['codigo'] . ")";
            $table_data[] = [
                $item_num++, $produto_info, $item['nome_empresa'] ?? '', $item['quantidade'] ?? 1,
                'R$ ' . number_format($item['preco_unitario'] ?? 0, 2, ',', '.'),
                'R$ ' . number_format(($item['quantidade'] ?? 1) * ($item['preco_unitario'] ?? 0), 2, ',', '.')
            ];
        }
        if (!empty($table_data)) $pdf->ModernTable($headers, $table_data, $widths);
        
        // Seção de Total, etc. (copiado do seu script)
        // ... (código de totais, observações, termos, aceite) ...

        // 3. RETORNAR PDF COMO STRING
        return $pdf->Output('S');

    } catch (Exception $e) {
        error_log("Erro em gerarOrcamentoPDFString para o ID $orcamento_id: " . $e->getMessage());
        throw $e;
    }
}

function registrarHistoricoOrcamento($conn, $orcamento_id, $observacoes = '') {
    // ... (código da função igual)
}

// ===================================================================================
// 3. PROCESSAMENTO DO FORMULÁRIO (POST)
// ===================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $orcamento_id_post = intval($_POST['orcamento_id']);
    $destinatario_email = filter_var($_POST['destinatario'], FILTER_SANITIZE_EMAIL);
    $assunto_email = htmlspecialchars($_POST['assunto']);
    $mensagem_texto_puro = htmlspecialchars($_POST['mensagem']);
    $mensagem_html = nl2br($mensagem_texto_puro);

    $mail = new PHPMailer(true);
    try {
        $pdf_content = gerarOrcamentoPDFString($orcamento_id_post, $conn);
        $nome_arquivo_pdf = 'Proposta_Comercial_' . str_pad($orcamento_id_post, 5, '0', STR_PAD_LEFT) . '.pdf';

        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'faciencia@lfmtecnologia.com';
        $mail->Password = 'Faciencai@2025#$T3cn0l0g1a@';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('faciencia@lfmtecnologia.com', 'Karla Wollinger - Representações');
        $mail->addAddress($destinatario_email);
        $mail->addReplyTo('karlawollinger2@gmail.com', 'Karla Wollinger');
        $mail->addStringAttachment($pdf_content, $nome_arquivo_pdf);

        $mail->isHTML(true);
        $mail->Subject = $assunto_email;
        $mail->Body = $mensagem_html; // Usa o texto do formulário, formatado com quebras de linha
        $mail->AltBody = strip_tags($_POST['mensagem']);

        $mail->send();
        
        registrarHistoricoOrcamento($conn, $orcamento_id_post, "Orçamento enviado por e-mail para {$destinatario_email}.");
        
        $_SESSION['message'] = ['type' => 'success', 'text' => "✅ Orçamento #{$orcamento_id_post} enviado com sucesso para {$destinatario_email}!"];
        header('Location: orcamentos.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => "❌ O e-mail não pôde ser enviado. Erro: " . $e->getMessage()];
        header('Location: enviar_orcamento.php?id=' . $orcamento_id_post);
        exit;
    }
}

// ===================================================================================
// 4. PREPARAÇÃO DOS DADOS E NOVO TEMPLATE DE E-MAIL
// ===================================================================================
$sql_orc = "SELECT c.nome AS nome_cliente, c.email AS email_cliente FROM orcamentos o JOIN clientes c ON o.cliente_id = c.id WHERE o.id = ?";
$stmt_orc = $conn->prepare($sql_orc);
$stmt_orc->bind_param("i", $orcamento_id);
$stmt_orc->execute();
$orcamento_data = $stmt_orc->get_result()->fetch_assoc();
if (!$orcamento_data) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Orçamento não encontrado.'];
    header('Location: orcamentos.php');
    exit;
}

$nome_cliente = htmlspecialchars($orcamento_data['nome_cliente']);
$assunto_padrao = "📄 Sua Proposta Comercial | Orçamento #{$orcamento_id}";
// --- TEMPLATE DE MENSAGEM MELHORADO ---
$mensagem_padrao = "
Olá, {$nome_cliente}! 👋

Espero que este e-mail o encontre bem.

Conforme nossa conversa, preparei com muito cuidado a proposta comercial que você solicitou. Ela está anexada a este e-mail para sua análise. 📄

✅ **Orçamento Detalhado:** No anexo, você encontrará todos os detalhes sobre os produtos, valores e condições que combinamos.

💡 **Próximos Passos:**
1.  **Revise a proposta** com atenção.
2.  **Anote qualquer dúvida** ou ponto que queira discutir.
3.  **Me dê um retorno** para alinharmos os detalhes ou darmos sequência!

Estou à sua inteira disposição para qualquer esclarecimento. Podemos agendar uma rápida ligação, se preferir.

Agradeço pela oportunidade e confiança.

Atenciosamente,

--
**Karla Wollinger**
*Representação Comercial*
📞 (41) 99859-3242
📧 karlawollinger2@gmail.com
";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Orçamento por E-mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; margin-top: 40px; }
        .card-header { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm mb-5">
            <div class="card-header">
                <h4 class="mb-0"><i class="fa-solid fa-paper-plane"></i> Enviar Orçamento #<?php echo $orcamento_id; ?> por E-mail</h4>
            </div>
            <div class="card-body p-4">
                <?php
                if (isset($_SESSION['message']) && is_array($_SESSION['message'])) {
                    $message = $_SESSION['message'];
                    echo "<div class='alert alert-{$message['type']}'>{$message['text']}</div>";
                    unset($_SESSION['message']);
                }
                ?>
                <form action="enviar_orcamento.php?id=<?php echo $orcamento_id; ?>" method="post">
                    <input type="hidden" name="orcamento_id" value="<?php echo $orcamento_id; ?>">
                    <div class="mb-3">
                        <label for="destinatario" class="form-label fw-bold">Para (Destinatário):</label>
                        <input type="email" class="form-control" id="destinatario" name="destinatario" value="<?php echo htmlspecialchars($orcamento_data['email_cliente'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="assunto" class="form-label fw-bold">Assunto:</label>
                        <input type="text" class="form-control" id="assunto" name="assunto" value="<?php echo htmlspecialchars($assunto_padrao); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="mensagem" class="form-label fw-bold">Corpo do E-mail:</label>
                        <textarea class="form-control" id="mensagem" name="mensagem" rows="18" required><?php echo htmlspecialchars($mensagem_padrao); ?></textarea>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="orcamentos.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-envelope me-2"></i>Confirmar e Enviar E-mail</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
ob_end_flush();
?>