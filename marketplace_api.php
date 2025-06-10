<?php
header('Content-Type: application/json');
require_once 'includes/db_connect.php';

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action']) || !isset($input['token'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$action = $input['action'];
$token = $input['token'];

// Validar token
$sql_token = "SELECT cliente_id FROM marketplace_links WHERE token_acesso = ? AND ativo = 1";
$stmt_token = $conn->prepare($sql_token);
$stmt_token->bind_param("s", $token);
$stmt_token->execute();
$result_token = $stmt_token->get_result();

if ($result_token->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

$link_data = $result_token->fetch_assoc();

switch ($action) {
    case 'add_to_cart':
        $produto_id = (int)$input['produto_id'];
        $quantidade = (int)$input['quantidade'];
        $preco_unitario = (float)$input['preco_unitario'];
        
        // Verificar se produto existe e está ativo
        $sql_produto = "SELECT id, preco_venda, quantidade_estoque FROM produtos WHERE id = ? AND ativo_marketplace = 1";
        $stmt_produto = $conn->prepare($sql_produto);
        $stmt_produto->bind_param("i", $produto_id);
        $stmt_produto->execute();
        $result_produto = $stmt_produto->get_result();
        
        if ($result_produto->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
            exit;
        }
        
        $produto = $result_produto->fetch_assoc();
        
        // Verificar estoque
        if ($quantidade > $produto['quantidade_estoque']) {
            echo json_encode(['success' => false, 'message' => 'Quantidade não disponível em estoque']);
            exit;
        }
        
        // Verificar se já existe no carrinho
        $sql_check = "SELECT id, quantidade FROM marketplace_carrinho WHERE token_acesso = ? AND produto_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $token, $produto_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Atualizar quantidade existente
            $item_existente = $result_check->fetch_assoc();
            $nova_quantidade = $item_existente['quantidade'] + $quantidade;
            
            if ($nova_quantidade > $produto['quantidade_estoque']) {
                echo json_encode(['success' => false, 'message' => 'Quantidade total excede o estoque']);
                exit;
            }
            
            $sql_update = "UPDATE marketplace_carrinho SET quantidade = ?, preco_unitario = ? WHERE token_acesso = ? AND produto_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("idsi", $nova_quantidade, $produto['preco_venda'], $token, $produto_id);
            $success = $stmt_update->execute();
        } else {
            // Inserir novo item
            $sql_insert = "INSERT INTO marketplace_carrinho (token_acesso, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("siid", $token, $produto_id, $quantidade, $produto['preco_venda']);
            $success = $stmt_insert->execute();
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'update_cart_item':
        $produto_id = (int)$input['produto_id'];
        $quantidade = (int)$input['quantidade'];
        
        if ($quantidade <= 0) {
            // Remover item se quantidade for 0 ou negativa
            $sql_delete = "DELETE FROM marketplace_carrinho WHERE token_acesso = ? AND produto_id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("si", $token, $produto_id);
            $success = $stmt_delete->execute();
        } else {
            // Verificar estoque
            $sql_produto = "SELECT quantidade_estoque FROM produtos WHERE id = ?";
            $stmt_produto = $conn->prepare($sql_produto);
            $stmt_produto->bind_param("i", $produto_id);
            $stmt_produto->execute();
            $result_produto = $stmt_produto->get_result();
            
            if ($result_produto->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
                exit;
            }
            
            $produto = $result_produto->fetch_assoc();
            
            if ($quantidade > $produto['quantidade_estoque']) {
                echo json_encode(['success' => false, 'message' => 'Quantidade não disponível em estoque']);
                exit;
            }
            
            // Atualizar quantidade
            $sql_update = "UPDATE marketplace_carrinho SET quantidade = ? WHERE token_acesso = ? AND produto_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("isi", $quantidade, $token, $produto_id);
            $success = $stmt_update->execute();
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'remove_from_cart':
        $produto_id = (int)$input['produto_id'];
        
        $sql_delete = "DELETE FROM marketplace_carrinho WHERE token_acesso = ? AND produto_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("si", $token, $produto_id);
        $success = $stmt_delete->execute();
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'clear_cart':
        $sql_clear = "DELETE FROM marketplace_carrinho WHERE token_acesso = ?";
        $stmt_clear = $conn->prepare($sql_clear);
        $stmt_clear->bind_param("s", $token);
        $success = $stmt_clear->execute();
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'get_carrinho':
        $sql_carrinho = "SELECT mc.*, p.nome as produto_nome, p.preco_venda, p.quantidade_estoque
                         FROM marketplace_carrinho mc
                         LEFT JOIN produtos p ON mc.produto_id = p.id
                         WHERE mc.token_acesso = ?";
        $stmt_carrinho = $conn->prepare($sql_carrinho);
        $stmt_carrinho->bind_param("s", $token);
        $stmt_carrinho->execute();
        $result_carrinho = $stmt_carrinho->get_result();
        
        $carrinho = [];
        while ($item = $result_carrinho->fetch_assoc()) {
            $carrinho[] = $item;
        }
        
        echo json_encode(['success' => true, 'carrinho' => $carrinho]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        break;
}

$conn->close();
?>
