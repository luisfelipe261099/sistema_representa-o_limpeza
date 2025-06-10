<?php
// Script para criar usuário administrador inicial
// Execute este arquivo uma vez para criar o usuário admin

require_once 'includes/db_connect.php';

// Dados do usuário administrador
$nome = "Administrador";
$email = "admin@karlawollinger.com";
$senha = "123456"; // Senha padrão - ALTERE após o primeiro login
$nivel_acesso = "admin";

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Criar Usuário Admin - Karla Wollinger</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .alert { border-radius: 10px; }
        .btn { border-radius: 8px; }
    </style>
</head>
<body class='d-flex align-items-center justify-content-center'>
    <div class='container'>
        <div class='row justify-content-center'>
            <div class='col-md-8 col-lg-6'>
                <div class='card'>
                    <div class='card-header bg-primary text-white text-center'>
                        <h3><i class='fas fa-user-shield me-2'></i>Criar Usuário Administrador</h3>
                    </div>
                    <div class='card-body p-4'>";

// Gerar hash da senha
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// Verificar se o usuário já existe
$sql_check = "SELECT id FROM usuarios WHERE email = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    echo "<div class='alert alert-warning'>
            <i class='fas fa-exclamation-triangle me-2'></i>
            <strong>Usuário já existe!</strong><br>
            Já existe um usuário com este email no sistema.
          </div>";
    
    echo "<div class='alert alert-info'>
            <strong>Credenciais de acesso:</strong><br>
            📧 <strong>Email:</strong> " . $email . "<br>
            🔑 <strong>Senha:</strong> " . $senha . "<br>
            <small class='text-muted'>Se você esqueceu a senha, use a página de perfil para alterá-la.</small>
          </div>";
} else {
    // Inserir novo usuário
    $sql_insert = "INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("ssss", $nome, $email, $senha_hash, $nivel_acesso);
    
    if ($stmt_insert->execute()) {
        echo "<div class='alert alert-success'>
                <i class='fas fa-check-circle me-2'></i>
                <strong>Usuário administrador criado com sucesso!</strong>
              </div>";
        
        echo "<div class='alert alert-info'>
                <strong>Credenciais de acesso:</strong><br>
                📧 <strong>Email:</strong> " . $email . "<br>
                🔑 <strong>Senha:</strong> " . $senha . "<br>
                👤 <strong>Nome:</strong> " . $nome . "<br>
                🛡️ <strong>Nível:</strong> " . $nivel_acesso . "
              </div>";
        
        echo "<div class='alert alert-warning'>
                <i class='fas fa-exclamation-triangle me-2'></i>
                <strong>IMPORTANTE:</strong><br>
                • Altere a senha após o primeiro login<br>
                • Delete este arquivo após usar por segurança<br>
                • Mantenha suas credenciais seguras
              </div>";
    } else {
        echo "<div class='alert alert-danger'>
                <i class='fas fa-times-circle me-2'></i>
                <strong>Erro ao criar usuário:</strong> " . $stmt_insert->error . "
              </div>";
    }
    $stmt_insert->close();
}

$stmt_check->close();

// Verificar estrutura da tabela usuarios
echo "<hr><h5><i class='fas fa-database me-2'></i>Informações do Sistema</h5>";

$sql_describe = "DESCRIBE usuarios";
$result_describe = $conn->query($sql_describe);

if ($result_describe) {
    echo "<div class='table-responsive'>
            <table class='table table-sm table-striped'>
                <thead class='table-dark'>
                    <tr>
                        <th>Campo</th>
                        <th>Tipo</th>
                        <th>Nulo</th>
                        <th>Chave</th>
                    </tr>
                </thead>
                <tbody>";
    
    while ($row = $result_describe->fetch_assoc()) {
        echo "<tr>
                <td><code>" . $row['Field'] . "</code></td>
                <td>" . $row['Type'] . "</td>
                <td>" . ($row['Null'] == 'YES' ? 'Sim' : 'Não') . "</td>
                <td>" . $row['Key'] . "</td>
              </tr>";
    }
    echo "</tbody></table></div>";
} else {
    echo "<div class='alert alert-danger'>Erro ao verificar estrutura da tabela: " . $conn->error . "</div>";
}

// Listar usuários existentes
echo "<h6 class='mt-4'><i class='fas fa-users me-2'></i>Usuários Cadastrados</h6>";
$sql_users = "SELECT id, nome, email, nivel_acesso, data_cadastro FROM usuarios ORDER BY id";
$result_users = $conn->query($sql_users);

if ($result_users && $result_users->num_rows > 0) {
    echo "<div class='table-responsive'>
            <table class='table table-sm table-striped'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Nível</th>
                        <th>Cadastro</th>
                    </tr>
                </thead>
                <tbody>";
    
    while ($row = $result_users->fetch_assoc()) {
        $badge_class = $row['nivel_acesso'] == 'admin' ? 'bg-success' : 'bg-primary';
        echo "<tr>
                <td>" . $row['id'] . "</td>
                <td>" . htmlspecialchars($row['nome']) . "</td>
                <td>" . htmlspecialchars($row['email']) . "</td>
                <td><span class='badge {$badge_class}'>" . ucfirst($row['nivel_acesso']) . "</span></td>
                <td>" . date('d/m/Y H:i', strtotime($row['data_cadastro'])) . "</td>
              </tr>";
    }
    echo "</tbody></table></div>";
} else {
    echo "<div class='alert alert-info'>Nenhum usuário encontrado.</div>";
}

echo "                    <div class='text-center mt-4'>
                            <a href='login.php' class='btn btn-primary btn-lg'>
                                <i class='fas fa-sign-in-alt me-2'></i>Fazer Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";

$conn->close();
?>
