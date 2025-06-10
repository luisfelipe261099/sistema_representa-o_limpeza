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

// Processar ações
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'atualizar_status':
            $pedido_id = (int)$_POST['pedido_id'];
            $novo_status = $_POST['novo_status'];

            try {
                $conn->begin_transaction();

                // Buscar dados do pedido
                $sql_pedido = "SELECT mp.*, c.nome as cliente_nome
                               FROM marketplace_pedidos mp
                               LEFT JOIN clientes c ON mp.cliente_id = c.id
                               WHERE mp.id = ?";
                $stmt_pedido = $conn->prepare($sql_pedido);
                $stmt_pedido->bind_param("i", $pedido_id);
                $stmt_pedido->execute();
                $result_pedido = $stmt_pedido->get_result();
                $pedido = $result_pedido->fetch_assoc();

                if (!$pedido) {
                    throw new Exception("Pedido não encontrado");
                }

                // Atualizar status do pedido
                $sql_update = "UPDATE marketplace_pedidos SET status_pedido = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $novo_status, $pedido_id);

                if (!$stmt_update->execute()) {
                    throw new Exception("Erro ao atualizar status do pedido");
                }

                // Se confirmado, criar venda e transações financeiras
                if ($novo_status == 'confirmado' && $pedido['status_pedido'] != 'confirmado') {
                    // Atualizar data de confirmação
                    $sql_confirm = "UPDATE marketplace_pedidos SET data_confirmacao = NOW() WHERE id = ?";
                    $stmt_confirm = $conn->prepare($sql_confirm);
                    $stmt_confirm->bind_param("i", $pedido_id);
                    $stmt_confirm->execute();

                    // Criar venda no sistema principal
                    $forma_pagamento = ($pedido['tipo_faturamento'] == 'avista') ? 'A Vista' : 'Faturado ' . str_replace('_', ' ', $pedido['tipo_faturamento']);

                    $sql_venda = "INSERT INTO vendas (cliente_id, valor_total, forma_pagamento, status_venda, data_venda)
                                  VALUES (?, ?, ?, 'concluida', NOW())";
                    $stmt_venda = $conn->prepare($sql_venda);
                    $stmt_venda->bind_param("ids", $pedido['cliente_id'], $pedido['valor_total'], $forma_pagamento);

                    if (!$stmt_venda->execute()) {
                        throw new Exception("Erro ao criar venda: " . $stmt_venda->error);
                    }

                    $venda_id = $conn->insert_id;

                    // Buscar itens do pedido e criar itens da venda
                    $sql_itens = "SELECT * FROM marketplace_itens_pedido WHERE pedido_id = ?";
                    $stmt_itens = $conn->prepare($sql_itens);
                    $stmt_itens->bind_param("i", $pedido_id);
                    $stmt_itens->execute();
                    $result_itens = $stmt_itens->get_result();

                    while ($item = $result_itens->fetch_assoc()) {
                        // Inserir item da venda
                        $sql_item_venda = "INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario)
                                           VALUES (?, ?, ?, ?)";
                        $stmt_item_venda = $conn->prepare($sql_item_venda);
                        $stmt_item_venda->bind_param("iiid", $venda_id, $item['produto_id'], $item['quantidade'], $item['preco_unitario']);

                        if (!$stmt_item_venda->execute()) {
                            throw new Exception("Erro ao criar item da venda: " . $stmt_item_venda->error);
                        }

                        // Atualizar estoque
                        $sql_estoque = "UPDATE produtos SET quantidade_estoque = quantidade_estoque - ? WHERE id = ?";
                        $stmt_estoque = $conn->prepare($sql_estoque);
                        $stmt_estoque->bind_param("ii", $item['quantidade'], $item['produto_id']);

                        if (!$stmt_estoque->execute()) {
                            throw new Exception("Erro ao atualizar estoque: " . $stmt_estoque->error);
                        }
                    }

                    // Criar transação financeira
                    $descricao = "Venda Marketplace #" . $pedido['numero_pedido'] . " - " . $pedido['cliente_nome'];
                    $categoria = "Vendas Marketplace";
                    $data_transacao = ($pedido['tipo_faturamento'] == 'avista') ? date('Y-m-d H:i:s') : $pedido['data_vencimento'] . ' 00:00:00';

                    $sql_transacao = "INSERT INTO transacoes_financeiras (tipo, valor, descricao, categoria, referencia_id, tabela_referencia, data_transacao)
                                      VALUES ('entrada', ?, ?, ?, ?, 'marketplace_pedidos', ?)";
                    $stmt_transacao = $conn->prepare($sql_transacao);
                    $stmt_transacao->bind_param("dssis", $pedido['valor_total'], $descricao, $categoria, $pedido_id, $data_transacao);

                    if (!$stmt_transacao->execute()) {
                        throw new Exception("Erro ao criar transação financeira: " . $stmt_transacao->error);
                    }

                    $transacao_id = $conn->insert_id;

                    // Vincular venda e transação ao pedido do marketplace
                    $sql_link = "UPDATE marketplace_pedidos SET venda_id = ?, transacao_financeira_id = ? WHERE id = ?";
                    $stmt_link = $conn->prepare($sql_link);
                    $stmt_link->bind_param("iii", $venda_id, $transacao_id, $pedido_id);

                    if (!$stmt_link->execute()) {
                        throw new Exception("Erro ao vincular venda e transação ao pedido: " . $stmt_link->error);
                    }
                }

                $conn->commit();

                if ($novo_status == 'confirmado' && $pedido['status_pedido'] != 'confirmado') {
                    $message = "Pedido confirmado! Venda e transação financeira criadas automaticamente.";
                } else {
                    $message = "Status do pedido atualizado com sucesso!";
                }
                $message_type = "success";

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Erro ao atualizar pedido: " . $e->getMessage();
                $message_type = "danger";
            }
            break;
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

// Buscar pedidos
$sql_pedidos = "SELECT mp.*, c.nome as cliente_nome, c.email as cliente_email,
                       COUNT(mip.id) as total_itens,
                       v.id as venda_integrada,
                       tf.id as transacao_integrada
                FROM marketplace_pedidos mp
                LEFT JOIN clientes c ON mp.cliente_id = c.id
                LEFT JOIN marketplace_itens_pedido mip ON mp.id = mip.pedido_id
                LEFT JOIN vendas v ON mp.venda_id = v.id
                LEFT JOIN transacoes_financeiras tf ON mp.transacao_financeira_id = tf.id
                WHERE 1=1";

$params = [];
$types = "";

if (!empty($filtro_status)) {
    $sql_pedidos .= " AND mp.status_pedido = ?";
    $params[] = $filtro_status;
    $types .= "s";
}

if (!empty($filtro_data_inicio)) {
    $sql_pedidos .= " AND DATE(mp.data_pedido) >= ?";
    $params[] = $filtro_data_inicio;
    $types .= "s";
}

if (!empty($filtro_data_fim)) {
    $sql_pedidos .= " AND DATE(mp.data_pedido) <= ?";
    $params[] = $filtro_data_fim;
    $types .= "s";
}

$sql_pedidos .= " GROUP BY mp.id ORDER BY mp.data_pedido DESC";

$stmt_pedidos = $conn->prepare($sql_pedidos);
if (!empty($params)) {
    $stmt_pedidos->bind_param($types, ...$params);
}
$stmt_pedidos->execute();
$result_pedidos = $stmt_pedidos->get_result();

// Estatísticas
$sql_stats = "SELECT 
    COUNT(*) as total_pedidos,
    SUM(CASE WHEN status_pedido = 'pendente' THEN 1 ELSE 0 END) as pedidos_pendentes,
    SUM(CASE WHEN status_pedido = 'confirmado' THEN 1 ELSE 0 END) as pedidos_confirmados,
    SUM(CASE WHEN status_pedido = 'entregue' THEN 1 ELSE 0 END) as pedidos_entregues,
    SUM(CASE WHEN status_pedido = 'entregue' THEN valor_total ELSE 0 END) as valor_entregue
    FROM marketplace_pedidos";
$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();

include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-shopping-cart"></i>
        Marketplace - Pedidos
    </h1>
    <p class="page-subtitle">
        Gerencie os pedidos recebidos através do marketplace
    </p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stats-card primary fade-in-up">
            <div class="stats-icon primary">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stats-value"><?php echo $stats['total_pedidos']; ?></div>
            <div class="stats-label">Total de Pedidos</div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card warning fade-in-up">
            <div class="stats-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-value"><?php echo $stats['pedidos_pendentes']; ?></div>
            <div class="stats-label">Pendentes</div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card info fade-in-up">
            <div class="stats-icon info">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-value"><?php echo $stats['pedidos_confirmados']; ?></div>
            <div class="stats-label">Confirmados</div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card success fade-in-up">
            <div class="stats-icon success">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stats-value"><?php echo $stats['pedidos_entregues']; ?></div>
            <div class="stats-label">Entregues</div>
            <div class="stats-change positive">
                <i class="fas fa-dollar-sign"></i> R$ <?php echo number_format($stats['valor_entregue'], 0, ',', '.'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="modern-card fade-in-up mb-4">
    <div class="card-header-modern">
        <i class="fas fa-filter"></i>
        Filtros
    </div>
    <div class="card-body-modern">
        <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">Todos os status</option>
                        <option value="pendente" <?php echo ($filtro_status == 'pendente' ? 'selected' : ''); ?>>Pendente</option>
                        <option value="confirmado" <?php echo ($filtro_status == 'confirmado' ? 'selected' : ''); ?>>Confirmado</option>
                        <option value="preparando" <?php echo ($filtro_status == 'preparando' ? 'selected' : ''); ?>>Preparando</option>
                        <option value="entregue" <?php echo ($filtro_status == 'entregue' ? 'selected' : ''); ?>>Entregue</option>
                        <option value="cancelado" <?php echo ($filtro_status == 'cancelado' ? 'selected' : ''); ?>>Cancelado</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $filtro_data_inicio; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $filtro_data_fim; ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                    <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Pedidos -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-list"></i>
        Pedidos do Marketplace
        <div class="ms-auto">
            <span class="badge bg-primary"><?php echo $result_pedidos->num_rows; ?> pedidos</span>
        </div>
    </div>
    <div class="card-body-modern">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Faturamento</th>
                        <th>Status</th>
                        <th>Integração</th>
                        <th>Itens</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_pedidos && $result_pedidos->num_rows > 0) {
                        while($pedido = $result_pedidos->fetch_assoc()) {
                            // Define a classe de badge para o status
                            $status_class = '';
                            switch ($pedido['status_pedido']) {
                                case 'confirmado':
                                case 'preparando':
                                    $status_class = 'bg-info';
                                    break;
                                case 'pendente':
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 'entregue':
                                    $status_class = 'bg-success';
                                    break;
                                case 'cancelado':
                                    $status_class = 'bg-danger';
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                            }
                            
                            $tipo_faturamento_texto = '';
                            switch ($pedido['tipo_faturamento']) {
                                case 'avista': $tipo_faturamento_texto = 'À Vista'; break;
                                case '15_dias': $tipo_faturamento_texto = '15 dias'; break;
                                case '20_dias': $tipo_faturamento_texto = '20 dias'; break;
                                case '30_dias': $tipo_faturamento_texto = '30 dias'; break;
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo htmlspecialchars($pedido['numero_pedido']); ?></strong><br>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pedido['cliente_nome']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($pedido['cliente_email']); ?></small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?><br>
                                    <?php if ($pedido['data_entrega_agendada']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-truck me-1"></i>
                                            <?php echo date('d/m/Y', strtotime($pedido['data_entrega_agendada'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></strong>
                                </td>
                                <td>
                                    <?php echo $tipo_faturamento_texto; ?><br>
                                    <?php if ($pedido['data_vencimento']): ?>
                                        <small class="text-muted">
                                            Venc: <?php echo date('d/m/Y', strtotime($pedido['data_vencimento'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $pedido['status_pedido'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pedido['venda_integrada'] && $pedido['transacao_integrada']): ?>
                                        <span class="badge bg-success" title="Venda e transação financeira criadas">
                                            <i class="fas fa-check"></i> Integrado
                                        </span>
                                    <?php elseif ($pedido['status_pedido'] == 'confirmado'): ?>
                                        <span class="badge bg-warning text-dark" title="Pedido confirmado mas não integrado">
                                            <i class="fas fa-exclamation-triangle"></i> Pendente
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Aguardando confirmação">
                                            <i class="fas fa-clock"></i> Aguardando
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo $pedido['total_itens']; ?> itens
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="marketplace_detalhes_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-info btn-sm" title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($pedido['status_pedido'] != 'cancelado' && $pedido['status_pedido'] != 'entregue'): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($pedido['status_pedido'] == 'pendente'): ?>
                                                        <li>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="acao" value="atualizar_status">
                                                                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                                                <input type="hidden" name="novo_status" value="confirmado">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="fas fa-check me-2"></i>Confirmar
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($pedido['status_pedido'] == 'confirmado'): ?>
                                                        <li>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="acao" value="atualizar_status">
                                                                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                                                <input type="hidden" name="novo_status" value="preparando">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="fas fa-cog me-2"></i>Preparando
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($pedido['status_pedido'] == 'preparando'): ?>
                                                        <li>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="acao" value="atualizar_status">
                                                                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                                                <input type="hidden" name="novo_status" value="entregue">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="fas fa-truck me-2"></i>Marcar como Entregue
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="acao" value="atualizar_status">
                                                            <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                                            <input type="hidden" name="novo_status" value="cancelado">
                                                            <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Tem certeza que deseja cancelar este pedido?')">
                                                                <i class="fas fa-times me-2"></i>Cancelar
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">Nenhum pedido encontrado.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once 'includes/footer.php';
?>
