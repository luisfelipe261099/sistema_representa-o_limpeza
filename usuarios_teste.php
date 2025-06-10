<?php
session_start();

// Verifica se o usuário está logado e é admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Verificar se é admin
if (!isset($_SESSION["nivel_acesso"]) || $_SESSION["nivel_acesso"] !== "admin") {
    echo "Acesso negado. Apenas administradores podem acessar esta página.";
    exit;
}

require_once 'includes/db_connect.php';

echo "<h1>Teste de Conexão e Dados</h1>";

// Testar conexão
if ($conn) {
    echo "<p style='color: green;'>✅ Conexão com banco OK</p>";
} else {
    echo "<p style='color: red;'>❌ Erro na conexão: " . mysqli_connect_error() . "</p>";
    exit;
}

// Verificar estrutura da tabela
echo "<h2>Estrutura da Tabela usuarios:</h2>";
$sql_describe = "DESCRIBE usuarios";
$result_describe = $conn->query($sql_describe);

if ($result_describe) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    
    while ($row = $result_describe->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Erro ao verificar estrutura: " . $conn->error . "</p>";
}

// Testar consulta simples
echo "<h2>Usuários na Tabela:</h2>";
$sql_users = "SELECT id, nome, email, nivel_acesso, ativo, data_cadastro FROM usuarios ORDER BY id";
$result_users = $conn->query($sql_users);

if ($result_users) {
    echo "<p style='color: green;'>✅ Consulta executada com sucesso</p>";
    echo "<p>Total de usuários: " . $result_users->num_rows . "</p>";
    
    if ($result_users->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Nível</th><th>Ativo</th><th>Cadastro</th></tr>";
        
        while ($row = $result_users->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . $row['nivel_acesso'] . "</td>";
            echo "<td>" . ($row['ativo'] ? 'Sim' : 'Não') . "</td>";
            echo "<td>" . $row['data_cadastro'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum usuário encontrado.</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Erro na consulta: " . $conn->error . "</p>";
}

// Testar consulta com ultimo_login
echo "<h2>Teste com campo ultimo_login:</h2>";
$sql_test = "SELECT id, nome, email, nivel_acesso, ultimo_login FROM usuarios LIMIT 1";
$result_test = $conn->query($sql_test);

if ($result_test) {
    echo "<p style='color: green;'>✅ Campo ultimo_login existe</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Campo ultimo_login não existe: " . $conn->error . "</p>";
    echo "<p>Execute o SQL: ALTER TABLE usuarios ADD COLUMN ultimo_login DATETIME NULL;</p>";
}

// Informações da sessão
echo "<h2>Informações da Sessão:</h2>";
echo "<p>ID: " . ($_SESSION['id'] ?? 'não definido') . "</p>";
echo "<p>Nome: " . ($_SESSION['nome'] ?? 'não definido') . "</p>";
echo "<p>Email: " . ($_SESSION['email'] ?? 'não definido') . "</p>";
echo "<p>Nível: " . ($_SESSION['nivel_acesso'] ?? 'não definido') . "</p>";

$conn->close();

echo "<br><br><a href='dashboard.php'>← Voltar ao Dashboard</a>";
?>
