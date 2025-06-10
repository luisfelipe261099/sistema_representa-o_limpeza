<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$orcamento_id = $_GET['id'] ?? null;
$orcamento = null;
$itens_orcamento = [];
$message = '';
$message_type = '';

if ($orcamento_id) {
    // Busca os dados do orçamento principal
    $sql_orcamento = "SELECT o.id, c.nome AS nome_cliente, c.cpf_cnpj, c.telefone, c.email,
                             o.data_orcamento, o.valor_total, o.status_orcamento, o.observacoes
                      FROM orcamentos o
                      JOIN clientes c ON o.cliente_id = c.id
                      WHERE o.id = ?";
    if ($stmt_orcamento = $conn->prepare($sql_orcamento)) {
        $stmt_orcamento->bind_param("i", $orcamento_id);
        $stmt_orcamento->execute();
        $result_orcamento = $stmt_orcamento->get_result();
        if ($result_orcamento->num_rows == 1) {
            $orcamento = $result_orcamento->fetch_assoc();
        } else {
            $message = "Orçamento não encontrado.";
            $message_type = "danger";
        }
        $stmt_orcamento->close();
    } else {
        $message = "Erro ao buscar detalhes do orçamento: " . $conn->error;
        $message_type = "danger";
    }

    // Busca os itens deste orçamento
    $sql_itens = "SELECT p.nome AS produto_nome, io.quantidade, io.preco_unitario
                  FROM itens_orcamento io
                  JOIN produtos p ON io.produto_id = p.id
                  WHERE io.orcamento_id = ?";
    if ($stmt_itens = $conn->prepare($sql_itens)) {
        $stmt_itens->bind_param("i", $orcamento_id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        while ($row_item = $result_itens->fetch_assoc()) {
            $itens_orcamento[] = $row_item;
        }
        $stmt_itens->close();
    } else {
        $message = "Erro ao buscar itens do orçamento: " . $conn->error;
        $message_type = "danger";
    }
} else {
    $message = "ID do orçamento não especificado.";
    $message_type = "danger";
}

$conn->close();

include_once 'includes/header.php';
?>

<h2 class="mb-4"><i class="fas fa-file-invoice me-2"></i> Detalhes do Orçamento #<?php echo htmlspecialchars($orcamento_id); ?></h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($orcamento):
    // Define a classe de badge para o status
    $status_class = '';
    switch ($orcamento['status_orcamento']) {
        case 'aprovado':
            $status_class = 'bg-success';
            break;
        case 'pendente':
            $status_class = 'bg-warning text-dark';
            break;
        case 'rejeitado':
            $status_class = 'bg-danger';
            break;
        case 'convertido_venda':
            $status_class = 'bg-info';
            break;
        default:
            $status_class = 'bg-secondary';
    }
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5>Informações do Orçamento</h5>
            <div>
                <a href="gerar_pdf_orcamento.php?id=<?php echo htmlspecialchars($orcamento['id']); ?>" class="btn btn-danger btn-sm me-2" title="Gerar PDF" target="_blank">
                    <i class="fas fa-file-pdf me-1"></i> Gerar PDF
                </a>
                <?php if ($orcamento['status_orcamento'] != 'convertido_venda' && $orcamento['status_orcamento'] != 'rejeitado'): ?>
                    <a href="criar_orcamento.php?id=<?php echo htmlspecialchars($orcamento['id']); ?>" class="btn btn-primary btn-sm me-2" title="Editar Orçamento">
                        <i class="fas fa-edit me-1"></i> Editar Orçamento
                    </a>
                <?php endif; ?>
                <?php if ($orcamento['status_orcamento'] == 'aprovado'): ?>
                    <a href="registrar_venda.php?from_orcamento_id=<?php echo htmlspecialchars($orcamento['id']); ?>" class="btn btn-success btn-sm me-2" title="Converter para Venda">
                        <i class="fas fa-exchange-alt me-1"></i> Converter para Venda
                    </a>
                <?php endif; ?>
                <a href="orcamentos.php" class="btn btn-secondary btn-sm" title="Voltar para Orçamentos">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Cliente:</strong> <?php echo htmlspecialchars($orcamento['nome_cliente']); ?> (<?php echo htmlspecialchars($orcamento['cpf_cnpj']); ?>)<br>
                    <strong>Contato:</strong> <?php echo htmlspecialchars($orcamento['telefone']); ?> | <?php echo htmlspecialchars($orcamento['email']); ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <strong>Data do Orçamento:</strong> <?php echo date('d/m/Y H:i', strtotime($orcamento['data_orcamento'])); ?><br>
                    <strong>Valor Total:</strong> <span class="fs-5 fw-bold text-primary">R$ <?php echo number_format($orcamento['valor_total'], 2, ',', '.'); ?></span>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <strong>Status:</strong> <span class="badge <?php echo $status_class; ?> fs-6"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $orcamento['status_orcamento']))); ?></span>
                </div>
            </div>
            <?php if (!empty($orcamento['observacoes'])): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <strong>Observações:</strong><br>
                    <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($orcamento['observacoes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <h4 class="mb-3"><i class="fas fa-boxes me-2 text-primary"></i> Itens do Orçamento</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Preço Unitário</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($itens_orcamento)) {
                    foreach ($itens_orcamento as $item) {
                        $subtotal_item = $item['quantidade'] * $item['preco_unitario'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['produto_nome']); ?></td>
                            <td><?php echo htmlspecialchars($item['quantidade']); ?></td>
                            <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($subtotal_item, 2, ',', '.'); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="4" class="text-center">Nenhum item encontrado para este orçamento.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>