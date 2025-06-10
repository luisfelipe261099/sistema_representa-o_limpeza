<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

echo "<h1>Teste de Clientes</h1>";

// Verificar se há clientes
$sql_clientes = "SELECT id, nome, email FROM clientes ORDER BY nome ASC";
$result_clientes = $conn->query($sql_clientes);

echo "<h2>Clientes no Banco:</h2>";
echo "<p>Total de clientes: " . $result_clientes->num_rows . "</p>";

if ($result_clientes->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th></tr>";
    
    while ($row = $result_clientes->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Nenhum cliente encontrado!</p>";
    echo "<p>Você precisa cadastrar clientes primeiro.</p>";
    echo "<a href='cadastro_cliente.php'>Cadastrar Cliente</a>";
}

// Verificar se há produtos
echo "<h2>Produtos no Banco:</h2>";
$sql_produtos = "SELECT id, nome, preco_venda FROM produtos ORDER BY nome ASC";
$result_produtos = $conn->query($sql_produtos);

echo "<p>Total de produtos: " . $result_produtos->num_rows . "</p>";

if ($result_produtos->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Preço</th></tr>";
    
    while ($row = $result_produtos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
        echo "<td>R$ " . number_format($row['preco_venda'], 2, ',', '.') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Nenhum produto encontrado!</p>";
    echo "<p>Você precisa cadastrar produtos primeiro.</p>";
    echo "<a href='cadastro_produto.php'>Cadastrar Produto</a>";
}

$conn->close();

echo "<br><br><a href='dashboard.php'>← Voltar ao Dashboard</a>";
?>
