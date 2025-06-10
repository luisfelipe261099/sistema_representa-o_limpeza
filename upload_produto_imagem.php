<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

require_once 'includes/db_connect.php';

// Configurações de upload
$upload_dir = 'uploads/produtos/';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Criar diretório se não existir
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar diretório de upload']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$produto_id = intval($_POST['produto_id'] ?? 0);

if (!$produto_id) {
    echo json_encode(['success' => false, 'message' => 'ID do produto inválido']);
    exit;
}

// Verificar se o produto existe
$stmt_check = $conn->prepare("SELECT id FROM produtos WHERE id = ?");
$stmt_check->bind_param("i", $produto_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
    exit;
}

if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erro no upload da imagem']);
    exit;
}

$file = $_FILES['imagem'];

// Validar tipo de arquivo
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Use JPEG, PNG, GIF ou WebP']);
    exit;
}

// Validar tamanho do arquivo
if ($file['size'] > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo 5MB']);
    exit;
}

// Gerar nome único para o arquivo
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = 'produto_' . $produto_id . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

try {
    // Mover arquivo para o diretório de upload
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Erro ao mover arquivo para o diretório de destino');
    }

    // Buscar imagem anterior para deletar
    $stmt_old = $conn->prepare("SELECT imagem_produto FROM produtos WHERE id = ?");
    $stmt_old->bind_param("i", $produto_id);
    $stmt_old->execute();
    $old_image = $stmt_old->get_result()->fetch_assoc()['imagem_produto'];

    // Atualizar banco de dados
    $stmt_update = $conn->prepare("UPDATE produtos SET imagem_produto = ? WHERE id = ?");
    $stmt_update->bind_param("si", $new_filename, $produto_id);
    
    if (!$stmt_update->execute()) {
        // Se falhou ao atualizar o banco, deletar o arquivo
        unlink($upload_path);
        throw new Exception('Erro ao atualizar banco de dados');
    }

    // Deletar imagem anterior se existir
    if ($old_image && file_exists($upload_dir . $old_image)) {
        unlink($upload_dir . $old_image);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Imagem enviada com sucesso',
        'filename' => $new_filename,
        'url' => $upload_path
    ]);

} catch (Exception $e) {
    // Limpar arquivo se algo deu errado
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
