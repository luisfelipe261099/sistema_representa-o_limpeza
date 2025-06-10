<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$pedido_id = $_GET['id'] ?? 0;

if (!$pedido_id) {
    header("location: marketplace_pedidos.php");
    exit;
}

// Buscar dados completos do pedido
$sql_pedido = "SELECT mp.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone, c.endereco, c.cidade, c.estado,
                      v.id as venda_id, v.data_venda,
                      tf.id as transacao_id, tf.data_transacao, tf.valor as valor_transacao
               FROM marketplace_pedidos mp
               LEFT JOIN clientes c ON mp.cliente_id = c.id
               LEFT JOIN vendas v ON mp.venda_id = v.id
               LEFT JOIN transacoes_financeiras tf ON mp.transacao_financeira_id = tf.id
               WHERE mp.id = ?";
$stmt_pedido = $conn->prepare($sql_pedido);
$stmt_pedido->bind_param("i", $pedido_id);
$stmt_pedido->execute();
$result_pedido = $stmt_pedido->get_result();

if ($result_pedido->num_rows === 0) {
    header("location: marketplace_pedidos.php");
    exit;
}

$pedido = $result_pedido->fetch_assoc();

// Buscar itens do pedido
$sql_itens = "SELECT mip.*, p.nome as produto_nome, p.codigo, e.nome_empresa
              FROM marketplace_itens_pedido mip
              LEFT JOIN produtos p ON mip.produto_id = p.id
              LEFT JOIN empresas_representadas e ON p.empresa_id = e.id
              WHERE mip.pedido_id = ?";
$stmt_itens = $conn->prepare($sql_itens);
$stmt_itens->bind_param("i", $pedido_id);
$stmt_itens->execute();
$result_itens = $stmt_itens->get_result();

$itens_pedido = [];
while ($item = $result_itens->fetch_assoc()) {
    $itens_pedido[] = $item;
}

include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-receipt"></i>
        Detalhes do Pedido #<?php echo htmlspecialchars($pedido['numero_pedido']); ?>
    </h1>
    <p class="page-subtitle">
        Informações completas do pedido do marketplace
    </p>
</div>

<div class="row">
    <!-- Informações do Pedido -->
    <div class="col-lg-8">
        <div class="modern-card fade-in-up mb-4">
            <div class="card-header-modern">
                <i class="fas fa-info-circle"></i>
                Informações do Pedido
            </div>
            <div class="card-body-modern">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Cliente:</h6>
                        <p class="mb-2"><strong><?php echo htmlspecialchars($pedido['cliente_nome']); ?></strong></p>
                        <p class="mb-2 text-muted"><?php echo htmlspecialchars($pedido['cliente_email']); ?></p>
                        <?php if ($pedido['telefone']): ?>
                            <p class="mb-3 text-muted">
                                <i class="fas fa-phone me-1"></i>
                                <?php echo htmlspecialchars($pedido['telefone']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Data do Pedido:</h6>
                        <p class="mb-2"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></p>
                        
                        <?php if ($pedido['data_confirmacao']): ?>
                            <h6 class="text-muted">Data de Confirmação:</h6>
                            <p class="mb-3"><?php echo date('d/m/Y H:i', strtotime($pedido['data_confirmacao'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Tipo de Faturamento:</h6>
                        <p class="mb-2">
                            <?php 
                            switch ($pedido['tipo_faturamento']) {
                                case 'avista': echo 'À Vista'; break;
                                case '15_dias': echo '15 dias'; break;
                                case '20_dias': echo '20 dias'; break;
                                case '30_dias': echo '30 dias'; break;
                            }
                            ?>
                        </p>
                        <?php if ($pedido['data_vencimento']): ?>
                            <p class="text-muted">Vencimento: <?php echo date('d/m/Y', strtotime($pedido['data_vencimento'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Valor Total:</h6>
                        <h4 class="text-primary">R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></h4>
                    </div>
                </div>
                
                <?php if ($pedido['data_entrega_agendada']): ?>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted">Entrega Agendada:</h6>
                            <p class="mb-2">
                                <i class="fas fa-truck me-2"></i>
                                <?php echo date('d/m/Y', strtotime($pedido['data_entrega_agendada'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($pedido['endereco_entrega']): ?>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted">Endereço de Entrega:</h6>
                            <p class="mb-2"><?php echo htmlspecialchars($pedido['endereco_entrega']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($pedido['observacoes']): ?>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted">Observações:</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($pedido['observacoes']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Itens do Pedido -->
        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-list"></i>
                Itens do Pedido
                <div class="ms-auto">
                    <span class="badge bg-primary"><?php echo count($itens_pedido); ?> itens</span>
                </div>
            </div>
            <div class="card-body-modern">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Empresa</th>
                                <th>Quantidade</th>
                                <th>Preço Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens_pedido as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['produto_nome']); ?></strong>
                                        <?php if ($item['codigo']): ?>
                                            <br><small class="text-muted">Código: <?php echo htmlspecialchars($item['codigo']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['nome_empresa']); ?></td>
                                    <td><?php echo $item['quantidade']; ?></td>
                                    <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                    <td><strong>R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="4">Total:</th>
                                <th>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Status e Integração -->
    <div class="col-lg-4">
        <!-- Status do Pedido -->
        <div class="modern-card fade-in-up mb-4">
            <div class="card-header-modern">
                <i class="fas fa-chart-line"></i>
                Status do Pedido
            </div>
            <div class="card-body-modern">
                <?php
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
                ?>
                <div class="text-center">
                    <span class="badge <?php echo $status_class; ?> fs-6 p-3">
                        <?php echo ucfirst(str_replace('_', ' ', $pedido['status_pedido'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Integração com Sistema -->
        <div class="modern-card fade-in-up mb-4">
            <div class="card-header-modern">
                <i class="fas fa-link"></i>
                Integração com Sistema
            </div>
            <div class="card-body-modern">
                <!-- Venda -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Venda Criada:</span>
                    <?php if ($pedido['venda_id']): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check me-1"></i>
                            <a href="vendas.php" class="text-white text-decoration-none">
                                Venda #<?php echo $pedido['venda_id']; ?>
                            </a>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">
                            <i class="fas fa-times me-1"></i>Não criada
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Transação Financeira -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Transação Financeira:</span>
                    <?php if ($pedido['transacao_id']): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check me-1"></i>
                            <a href="financeiro.php" class="text-white text-decoration-none">
                                Trans. #<?php echo $pedido['transacao_id']; ?>
                            </a>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">
                            <i class="fas fa-times me-1"></i>Não criada
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Estoque -->
                <div class="d-flex justify-content-between align-items-center">
                    <span>Estoque Atualizado:</span>
                    <?php if ($pedido['status_pedido'] == 'confirmado' || $pedido['status_pedido'] == 'preparando' || $pedido['status_pedido'] == 'entregue'): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check me-1"></i>Atualizado
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">
                            <i class="fas fa-clock me-1"></i>Aguardando
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($pedido['venda_id'] && $pedido['transacao_id']): ?>
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Sistema Integrado!</strong><br>
                        Pedido totalmente sincronizado com vendas e financeiro.
                    </div>
                <?php elseif ($pedido['status_pedido'] == 'confirmado'): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Integração Pendente!</strong><br>
                        Pedido confirmado mas não integrado ao sistema.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ações -->
        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-cogs"></i>
                Ações
            </div>
            <div class="card-body-modern">
                <div class="d-grid gap-2">
                    <a href="marketplace_pedidos.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Voltar aos Pedidos
                    </a>
                    
                    <?php if ($pedido['venda_id']): ?>
                        <a href="vendas.php?id=<?php echo $pedido['venda_id']; ?>" class="btn btn-info">
                            <i class="fas fa-shopping-cart me-2"></i>Ver Venda
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($pedido['transacao_id']): ?>
                        <a href="financeiro.php?id=<?php echo $pedido['transacao_id']; ?>" class="btn btn-success">
                            <i class="fas fa-dollar-sign me-2"></i>Ver Transação
                        </a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once 'includes/footer.php';
?>
