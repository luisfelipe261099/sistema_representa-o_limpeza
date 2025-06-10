<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$venda_id = $_GET['id'] ?? null;
$venda = null;
$itens_venda = [];
$message = '';
$message_type = '';

if ($venda_id) {
    // Busca os dados da venda principal
    $sql_venda = "SELECT v.id, c.nome AS nome_cliente, c.cpf_cnpj, c.telefone, c.email,
                         v.data_venda, v.valor_total, v.forma_pagamento, v.status_venda
                  FROM vendas v
                  JOIN clientes c ON v.cliente_id = c.id
                  WHERE v.id = ?";
    if ($stmt_venda = $conn->prepare($sql_venda)) {
        $stmt_venda->bind_param("i", $venda_id);
        $stmt_venda->execute();
        $result_venda = $stmt_venda->get_result();
        if ($result_venda->num_rows == 1) {
            $venda = $result_venda->fetch_assoc();
        } else {
            $message = "Venda não encontrada.";
            $message_type = "danger";
        }
        $stmt_venda->close();
    } else {
        $message = "Erro ao buscar detalhes da venda: " . $conn->error;
        $message_type = "danger";
    }

    // Busca os itens desta venda
    $sql_itens = "SELECT p.nome AS produto_nome, iv.quantidade, iv.preco_unitario
                  FROM itens_venda iv
                  JOIN produtos p ON iv.produto_id = p.id
                  WHERE iv.venda_id = ?";
    if ($stmt_itens = $conn->prepare($sql_itens)) {
        $stmt_itens->bind_param("i", $venda_id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        while ($row_item = $result_itens->fetch_assoc()) {
            $itens_venda[] = $row_item;
        }
        $stmt_itens->close();
    } else {
        $message = "Erro ao buscar itens da venda: " . $conn->error;
        $message_type = "danger";
    }
} else {
    $message = "ID da venda não especificado.";
    $message_type = "danger";
}

$conn->close();

include_once 'includes/header.php';
?>

<h2 class="mb-4"><i class="fas fa-file-invoice me-2"></i> Detalhes da Venda #<?php echo htmlspecialchars($venda_id); ?></h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($venda):
    // Define a classe de badge para o status
    $status_class = '';
    switch ($venda['status_venda']) {
        case 'concluida':
            $status_class = 'bg-success';
            break;
        case 'pendente':
            $status_class = 'bg-warning text-dark';
            break;
        case 'cancelada':
            $status_class = 'bg-danger';
            break;
        default:
            $status_class = 'bg-secondary';
    }
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5>Informações da Venda</h5>
            <div>
                <a href="registrar_venda.php?id=<?php echo htmlspecialchars($venda['id']); ?>" class="btn btn-primary btn-sm me-2" title="Editar Venda">
                    <i class="fas fa-edit me-1"></i> Editar Venda
                </a>
                <a href="vendas.php" class="btn btn-secondary btn-sm" title="Voltar para Vendas">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Cliente:</strong> <?php echo htmlspecialchars($venda['nome_cliente']); ?> (<?php echo htmlspecialchars($venda['cpf_cnpj']); ?>)<br>
                    <strong>Contato:</strong> <?php echo htmlspecialchars($venda['telefone']); ?> | <?php echo htmlspecialchars($venda['email']); ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <strong>Data da Venda:</strong> <?php echo date('d/m/Y H:i', strtotime($venda['data_venda'])); ?><br>
                    <strong>Valor Total:</strong> <span class="fs-5 fw-bold text-success">R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></span>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <strong>Forma de Pagamento:</strong> <?php echo htmlspecialchars($venda['forma_pagamento']); ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <strong>Status:</strong> <span class="badge <?php echo $status_class; ?> fs-6"><?php echo htmlspecialchars(ucfirst($venda['status_venda'])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <h4 class="mb-3"><i class="fas fa-boxes me-2 text-primary"></i> Itens da Venda</h4>
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
                if (!empty($itens_venda)) {
                    foreach ($itens_venda as $item) {
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
                    echo '<tr><td colspan="4" class="text-center">Nenhum item encontrado para esta venda.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>