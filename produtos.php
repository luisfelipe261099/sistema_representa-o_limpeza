<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$message = '';
$message_type = '';

// --- Lógica para Exclusão SEGURA de Produto ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $produto_id_to_delete = intval($_GET['id']);

    // 1. VERIFICAR DEPENDÊNCIAS ANTES DE EXCLUIR
    $sql_check_vendas = "SELECT COUNT(*) FROM itens_venda WHERE produto_id = ?";
    $stmt_check_vendas = $conn->prepare($sql_check_vendas);
    $stmt_check_vendas->bind_param("i", $produto_id_to_delete);
    $stmt_check_vendas->execute();
    $vendas_count = $stmt_check_vendas->get_result()->fetch_row()[0];

    $sql_check_orcamentos = "SELECT COUNT(*) FROM itens_orcamento WHERE produto_id = ?";
    $stmt_check_orcamentos = $conn->prepare($sql_check_orcamentos);
    $stmt_check_orcamentos->bind_param("i", $produto_id_to_delete);
    $stmt_check_orcamentos->execute();
    $orcamentos_count = $stmt_check_orcamentos->get_result()->fetch_row()[0];

    if ($vendas_count > 0 || $orcamentos_count > 0) {
        // Se houver dependências, impede a exclusão
        $message = "Este produto não pode ser excluído, pois está associado a vendas ou orçamentos existentes.";
        $message_type = "danger";
    } else {
        // Se não houver dependências, procede com a exclusão
        $sql_delete = "DELETE FROM produtos WHERE id = ?";
        if ($stmt_delete = $conn->prepare($sql_delete)) {
            $stmt_delete->bind_param("i", $produto_id_to_delete);
            if ($stmt_delete->execute()) {
                $message = "Produto excluído com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao excluir o produto: " . $stmt_delete->error;
                $message_type = "danger";
            }
            $stmt_delete->close();
        }
    }
}

// --- Lógica para buscar todos os produtos (SQL ATUALIZADO) ---
// Trocado 'preco_custo' por 'percentual_lucro'
$sql_select_produtos = "SELECT id, nome, sku, percentual_lucro, preco_venda, quantidade_estoque, estoque_minimo, fornecedor FROM produtos ORDER BY nome ASC";
$result_produtos = $conn->query($sql_select_produtos);

// Carregar todos os dados em um array para reutilização
$produtos_data = ($result_produtos->num_rows > 0) ? $result_produtos->fetch_all(MYSQLI_ASSOC) : [];

// Calcular estatísticas
$total_produtos = count($produtos_data);
$criticos = 0;
$valor_total_estoque = 0;
$fornecedores_unicos = [];

foreach($produtos_data as $produto) {
    if ($produto['quantidade_estoque'] <= $produto['estoque_minimo']) {
        $criticos++;
    }
    $valor_total_estoque += $produto['preco_venda'] * $produto['quantidade_estoque'];
    if (!empty($produto['fornecedor']) && !in_array($produto['fornecedor'], $fornecedores_unicos)) {
        $fornecedores_unicos[] = $produto['fornecedor'];
    }
}

$conn->close();

include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title"><i class="fas fa-box"></i> Gerenciamento de Produtos</h1>
    <p class="page-subtitle">Controle completo do seu catálogo e estoque.</p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3"><div class="stats-card primary fade-in-up"><div class="stats-icon primary"><i class="fas fa-boxes"></i></div><div class="stats-value"><?php echo $total_produtos; ?></div><div class="stats-label">Total de Produtos</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card danger fade-in-up"><div class="stats-icon danger"><i class="fas fa-exclamation-triangle"></i></div><div class="stats-value"><?php echo $criticos; ?></div><div class="stats-label">Estoque Crítico</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card success fade-in-up"><div class="stats-icon success"><i class="fas fa-dollar-sign"></i></div><div class="stats-value">R$ <?php echo number_format($valor_total_estoque, 2, ',', '.'); ?></div><div class="stats-label">Valor em Estoque</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card info fade-in-up"><div class="stats-icon info"><i class="fas fa-truck"></i></div><div class="stats-value"><?php echo count($fornecedores_unicos); ?></div><div class="stats-label">Fornecedores</div></div></div>
</div>

<div class="modern-card fade-in-up">
    <div class="card-header-modern d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Lista de Produtos</span>
        <a href="cadastro_produto.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Novo Produto</a>
    </div>
    <div class="card-body-modern">
        <?php if (!empty($produtos_data)): ?>
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-hover mb-0" id="produtosTable">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>SKU</th>
                            <th>Preço / Lucro</th>
                            <th class="text-center">Estoque</th>
                            <th>Fornecedor</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos_data as $row): ?>
                            <?php $estoque_baixo = $row['quantidade_estoque'] <= $row['estoque_minimo']; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['nome']); ?></div>
                                    <small class="text-muted">#<?php echo $row['id']; ?></small>
                                </td>
                                <td><code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($row['sku'] ?: 'N/A'); ?></code></td>
                                <td>
                                    <div class="fw-semibold text-success">R$ <?php echo number_format($row['preco_venda'], 2, ',', '.'); ?></div>
                                    <small class="text-muted">Lucro: <?php echo number_format($row['percentual_lucro'], 2, ',', '.'); ?>%</small>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?php echo $estoque_baixo ? 'status-danger' : 'status-success'; ?>" title="Mínimo: <?php echo $row['estoque_minimo']; ?>">
                                        <?php echo $row['quantidade_estoque']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['fornecedor'] ?: 'N/A'); ?></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="cadastro_produto.php?id=<?php echo $row['id']; ?>"><i class="fas fa-edit me-2"></i>Editar</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="produtos.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este produto? A exclusão só será permitida se ele não estiver em nenhuma venda ou orçamento.');"><i class="fas fa-trash-alt me-2"></i>Excluir</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-lg-none">
                <ul class="list-group list-group-flush">
                    <?php foreach ($produtos_data as $row): ?>
                         <?php $estoque_baixo = $row['quantidade_estoque'] <= $row['estoque_minimo']; ?>
                        <li class="list-group-item px-0 py-3">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($row['nome']); ?></h6>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="cadastro_produto.php?id=<?php echo $row['id']; ?>">Editar</a></li>
                                        <li><a class="dropdown-item text-danger" href="produtos.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Tem certeza? A exclusão só será permitida se o produto não estiver em uso.');">Excluir</a></li>
                                    </ul>
                                </div>
                            </div>
                            <p class="mb-2 text-muted small">SKU: <?php echo htmlspecialchars($row['sku'] ?: 'N/A'); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-success fw-bold">R$ <?php echo number_format($row['preco_venda'], 2, ',', '.'); ?></div>
                                <div class="text-info small">Lucro: <?php echo number_format($row['percentual_lucro'], 2, ',', '.'); ?>%</div>
                                <div class="text-center">
                                     <span class="status-badge <?php echo $estoque_baixo ? 'status-danger' : 'status-success'; ?>">
                                        <?php echo $row['quantidade_estoque']; ?> em estoque
                                    </span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php else: ?>
            <div class="text-center py-5">
                <div class="stats-icon primary mx-auto mb-3"><i class="fas fa-box-open"></i></div>
                <h5 class="text-muted mb-2">Nenhum produto cadastrado</h5>
                <p class="text-muted">Comece adicionando seu primeiro produto ao sistema.</p>
                <a href="cadastro_produto.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Adicionar Primeiro Produto</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>