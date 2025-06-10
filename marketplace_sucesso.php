<?php
session_start();
require_once 'includes/db_connect.php';

// Verificar token e pedido
$token = $_GET['token'] ?? '';
$pedido_id = $_GET['pedido'] ?? '';

if (empty($token) || empty($pedido_id)) {
    die('Acesso negado. Parâmetros inválidos.');
}

// Validar token e buscar dados do cliente
$sql_token = "SELECT ml.*, c.nome as cliente_nome, c.email as cliente_email
              FROM marketplace_links ml 
              LEFT JOIN clientes c ON ml.cliente_id = c.id 
              WHERE ml.token_acesso = ? AND ml.ativo = 1";
$stmt_token = $conn->prepare($sql_token);
$stmt_token->bind_param("s", $token);
$stmt_token->execute();
$result_token = $stmt_token->get_result();

if ($result_token->num_rows === 0) {
    die('Link inválido ou expirado.');
}

$link_data = $result_token->fetch_assoc();

// Buscar dados do pedido
$sql_pedido = "SELECT mp.*, 
                      CASE 
                          WHEN mp.tipo_faturamento = 'avista' THEN 'À Vista'
                          WHEN mp.tipo_faturamento = '15_dias' THEN '15 dias'
                          WHEN mp.tipo_faturamento = '20_dias' THEN '20 dias'
                          WHEN mp.tipo_faturamento = '30_dias' THEN '30 dias'
                      END as tipo_faturamento_texto
               FROM marketplace_pedidos mp 
               WHERE mp.id = ? AND mp.cliente_id = ?";
$stmt_pedido = $conn->prepare($sql_pedido);
$stmt_pedido->bind_param("ii", $pedido_id, $link_data['cliente_id']);
$stmt_pedido->execute();
$result_pedido = $stmt_pedido->get_result();

if ($result_pedido->num_rows === 0) {
    die('Pedido não encontrado.');
}

$pedido = $result_pedido->fetch_assoc();

// Buscar itens do pedido
$sql_itens = "SELECT mip.*, p.nome as produto_nome
              FROM marketplace_itens_pedido mip
              LEFT JOIN produtos p ON mip.produto_id = p.id
              WHERE mip.pedido_id = ?";
$stmt_itens = $conn->prepare($sql_itens);
$stmt_itens->bind_param("i", $pedido_id);
$stmt_itens->execute();
$result_itens = $stmt_itens->get_result();

$itens_pedido = [];
while ($item = $result_itens->fetch_assoc()) {
    $itens_pedido[] = $item;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado - Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
        }
        
        .success-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 3rem 0;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .btn-primary {
            background: #3b82f6;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
        }
        
        .btn-outline-primary {
            border-color: #3b82f6;
            color: #3b82f6;
            border-radius: 8px;
            padding: 0.75rem 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            background: #10b981;
            border-radius: 50%;
        }
        
        .timeline-item.pending::before {
            background: #e5e7eb;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 1.2rem;
            width: 2px;
            height: calc(100% + 0.5rem);
            background: #e5e7eb;
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Header de Sucesso -->
    <div class="success-header text-center">
        <div class="container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="mb-3">Pedido Confirmado!</h1>
            <p class="lead mb-4">
                Seu pedido foi recebido com sucesso e está sendo processado.
            </p>
            <div class="bg-white bg-opacity-10 rounded p-3 d-inline-block">
                <h5 class="mb-1">Número do Pedido:</h5>
                <h3 class="mb-0">#<?php echo htmlspecialchars($pedido['numero_pedido']); ?></h3>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <!-- Detalhes do Pedido -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            Detalhes do Pedido
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted">Cliente:</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($link_data['cliente_nome']); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars($link_data['cliente_email']); ?></small>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Data do Pedido:</h6>
                                <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted">Tipo de Faturamento:</h6>
                                <p class="mb-0"><?php echo $pedido['tipo_faturamento_texto']; ?></p>
                                <?php if ($pedido['data_vencimento']): ?>
                                    <small class="text-muted">Vencimento: <?php echo date('d/m/Y', strtotime($pedido['data_vencimento'])); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Entrega Agendada:</h6>
                                <p class="mb-0">
                                    <?php 
                                    if ($pedido['data_entrega_agendada']) {
                                        echo date('d/m/Y', strtotime($pedido['data_entrega_agendada']));
                                    } else {
                                        echo 'A definir';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($pedido['endereco_entrega']): ?>
                            <div class="mb-4">
                                <h6 class="text-muted">Endereço de Entrega:</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($pedido['endereco_entrega']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($pedido['observacoes']): ?>
                            <div class="mb-4">
                                <h6 class="text-muted">Observações:</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($pedido['observacoes']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <h6 class="text-muted mb-3">Itens do Pedido:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Quantidade</th>
                                        <th>Preço Unit.</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens_pedido as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['produto_nome']); ?></td>
                                            <td><?php echo $item['quantidade']; ?></td>
                                            <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                            <td>R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th colspan="3">Total:</th>
                                        <th>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Ações -->
                <div class="d-flex gap-3 justify-content-center">
                    <a href="marketplace.php?token=<?php echo $token; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-store me-2"></i>
                        Continuar Comprando
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-2"></i>
                        Imprimir Pedido
                    </button>
                </div>
            </div>

            <!-- Status e Próximos Passos -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            Status do Pedido
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline-item">
                            <h6 class="mb-1">Pedido Recebido</h6>
                            <small class="text-muted">Seu pedido foi confirmado</small>
                        </div>
                        <div class="timeline-item pending">
                            <h6 class="mb-1">Processando</h6>
                            <small class="text-muted">Preparando os produtos</small>
                        </div>
                        <div class="timeline-item pending">
                            <h6 class="mb-1">Em Rota</h6>
                            <small class="text-muted">Saiu para entrega</small>
                        </div>
                        <div class="timeline-item pending">
                            <h6 class="mb-1">Entregue</h6>
                            <small class="text-muted">Pedido finalizado</small>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Próximos Passos
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-primary">
                                <i class="fas fa-envelope me-2"></i>
                                Confirmação por Email
                            </h6>
                            <p class="small text-muted mb-0">
                                Você receberá um email de confirmação com todos os detalhes do pedido.
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-primary">
                                <i class="fas fa-phone me-2"></i>
                                Contato para Entrega
                            </h6>
                            <p class="small text-muted mb-0">
                                Nossa equipe entrará em contato para agendar a entrega.
                            </p>
                        </div>
                        
                        <div>
                            <h6 class="text-primary">
                                <i class="fas fa-file-invoice me-2"></i>
                                Faturamento
                            </h6>
                            <p class="small text-muted mb-0">
                                A nota fiscal será enviada conforme o prazo selecionado.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
