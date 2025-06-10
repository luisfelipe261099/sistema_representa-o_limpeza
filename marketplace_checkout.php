<?php
session_start();
require_once 'includes/db_connect.php';

// Verificar token de acesso
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('Acesso negado. Token inválido.');
}

// Validar token e buscar dados do cliente
$sql_token = "SELECT ml.*, c.nome as cliente_nome, c.email as cliente_email, c.endereco, c.cidade, c.estado, c.cep, c.telefone
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

// Buscar itens do carrinho
$sql_carrinho = "SELECT mc.*, p.nome as produto_nome, p.preco_venda, p.quantidade_estoque
                 FROM marketplace_carrinho mc
                 LEFT JOIN produtos p ON mc.produto_id = p.id
                 WHERE mc.token_acesso = ?";
$stmt_carrinho = $conn->prepare($sql_carrinho);
$stmt_carrinho->bind_param("s", $token);
$stmt_carrinho->execute();
$result_carrinho = $stmt_carrinho->get_result();

$carrinho_itens = [];
$total_carrinho = 0;
while ($item = $result_carrinho->fetch_assoc()) {
    $carrinho_itens[] = $item;
    $total_carrinho += $item['quantidade'] * $item['preco_unitario'];
}

if (empty($carrinho_itens)) {
    header("Location: marketplace.php?token=" . $token);
    exit;
}

$message = '';
$message_type = '';

// Processar pedido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipo_faturamento = $_POST['tipo_faturamento'] ?? 'avista';
    $data_entrega = $_POST['data_entrega'] ?? '';
    $endereco_entrega = trim($_POST['endereco_entrega'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    try {
        $conn->begin_transaction();
        
        // Inserir pedido
        $sql_pedido = "INSERT INTO marketplace_pedidos (cliente_id, token_acesso, valor_total, tipo_faturamento, data_entrega_agendada, endereco_entrega, observacoes) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_pedido = $conn->prepare($sql_pedido);
        $stmt_pedido->bind_param("isdssss", $link_data['cliente_id'], $token, $total_carrinho, $tipo_faturamento, $data_entrega, $endereco_entrega, $observacoes);
        
        if (!$stmt_pedido->execute()) {
            throw new Exception("Erro ao criar pedido: " . $stmt_pedido->error);
        }
        
        $pedido_id = $conn->insert_id;
        
        // Inserir itens do pedido
        foreach ($carrinho_itens as $item) {
            $subtotal = $item['quantidade'] * $item['preco_unitario'];
            
            $sql_item = "INSERT INTO marketplace_itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, subtotal) 
                         VALUES (?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            $stmt_item->bind_param("iiidd", $pedido_id, $item['produto_id'], $item['quantidade'], $item['preco_unitario'], $subtotal);
            
            if (!$stmt_item->execute()) {
                throw new Exception("Erro ao inserir item do pedido: " . $stmt_item->error);
            }
        }
        
        // Limpar carrinho
        $sql_clear = "DELETE FROM marketplace_carrinho WHERE token_acesso = ?";
        $stmt_clear = $conn->prepare($sql_clear);
        $stmt_clear->bind_param("s", $token);
        $stmt_clear->execute();
        
        $conn->commit();
        
        // Redirecionar para página de sucesso
        header("Location: marketplace_sucesso.php?token=" . $token . "&pedido=" . $pedido_id);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Erro ao processar pedido: " . $e->getMessage();
        $message_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido - Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
        }
        
        .checkout-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1e293b 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-primary {
            background: #3b82f6;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.75rem 2rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="checkout-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-shopping-cart me-3"></i>
                        Finalizar Pedido
                    </h1>
                    <p class="mb-0 opacity-75">
                        Revise seus itens e complete seu pedido
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="bg-white bg-opacity-10 rounded p-3">
                        <h6 class="mb-1">Cliente:</h6>
                        <strong><?php echo htmlspecialchars($link_data['cliente_nome']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Resumo do Pedido -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Resumo do Pedido
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($carrinho_itens as $item): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['produto_nome']); ?></h6>
                                    <p class="text-muted small mb-0">
                                        Quantidade: <?php echo $item['quantidade']; ?> × 
                                        R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <strong>R$ <?php echo number_format($item['quantidade'] * $item['preco_unitario'], 2, ',', '.'); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <h5 class="mb-0">Total:</h5>
                            <h4 class="mb-0 text-primary">R$ <?php echo number_format($total_carrinho, 2, ',', '.'); ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Formulário de Checkout -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Dados do Pedido
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?token=" . $token; ?>">
                            <div class="row g-4">
                                <!-- Tipo de Faturamento -->
                                <div class="col-md-6">
                                    <label for="tipo_faturamento" class="form-label">
                                        <i class="fas fa-credit-card me-2"></i>
                                        Tipo de Faturamento *
                                    </label>
                                    <select class="form-select" id="tipo_faturamento" name="tipo_faturamento" required>
                                        <option value="avista">À Vista</option>
                                        <option value="15_dias">15 dias</option>
                                        <option value="20_dias">20 dias</option>
                                        <option value="30_dias">30 dias</option>
                                    </select>
                                </div>

                                <!-- Data de Entrega -->
                                <div class="col-md-6">
                                    <label for="data_entrega" class="form-label">
                                        <i class="fas fa-calendar me-2"></i>
                                        Data de Entrega Desejada
                                    </label>
                                    <input type="date" class="form-control" id="data_entrega" name="data_entrega" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                </div>

                                <!-- Endereço de Entrega -->
                                <div class="col-12">
                                    <label for="endereco_entrega" class="form-label">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        Endereço de Entrega
                                    </label>
                                    <textarea class="form-control" id="endereco_entrega" name="endereco_entrega" rows="3" 
                                              placeholder="Digite o endereço completo para entrega..."><?php echo htmlspecialchars($link_data['endereco'] . ', ' . $link_data['cidade'] . ' - ' . $link_data['estado']); ?></textarea>
                                </div>

                                <!-- Observações -->
                                <div class="col-12">
                                    <label for="observacoes" class="form-label">
                                        <i class="fas fa-comment me-2"></i>
                                        Observações (Opcional)
                                    </label>
                                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" 
                                              placeholder="Observações adicionais sobre o pedido..."></textarea>
                                </div>

                                <!-- Botões -->
                                <div class="col-12">
                                    <div class="d-flex gap-3 justify-content-end">
                                        <a href="marketplace.php?token=<?php echo $token; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>
                                            Voltar ao Marketplace
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check me-2"></i>
                                            Confirmar Pedido
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Informações Adicionais -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Informações Importantes
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-primary">
                                <i class="fas fa-truck me-2"></i>
                                Entrega
                            </h6>
                            <p class="small text-muted mb-0">
                                As entregas são realizadas de segunda a sexta-feira, das 8h às 18h.
                                Prazo padrão: 3 dias úteis.
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-primary">
                                <i class="fas fa-credit-card me-2"></i>
                                Faturamento
                            </h6>
                            <p class="small text-muted mb-0">
                                O faturamento será gerado conforme o prazo selecionado.
                                Você receberá a nota fiscal por email.
                            </p>
                        </div>
                        
                        <div>
                            <h6 class="text-primary">
                                <i class="fas fa-headset me-2"></i>
                                Suporte
                            </h6>
                            <p class="small text-muted mb-0">
                                Dúvidas? Entre em contato conosco pelo telefone ou email.
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
