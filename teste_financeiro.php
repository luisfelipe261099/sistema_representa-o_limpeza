<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

echo "<h1>Teste do Sistema Financeiro</h1>";

// Verificar se a tabela transacoes_financeiras existe
echo "<h2>Verificando Tabela transacoes_financeiras:</h2>";
$sql_check_table = "SHOW TABLES LIKE 'transacoes_financeiras'";
$result_check = $conn->query($sql_check_table);

if ($result_check->num_rows > 0) {
    echo "<p style='color: green;'>✅ Tabela transacoes_financeiras existe</p>";
    
    // Verificar estrutura da tabela
    echo "<h3>Estrutura da Tabela:</h3>";
    $sql_describe = "DESCRIBE transacoes_financeiras";
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
    }
    
    // Verificar transações existentes
    echo "<h3>Transações Existentes:</h3>";
    $sql_transacoes = "SELECT * FROM transacoes_financeiras ORDER BY data_transacao DESC";
    $result_transacoes = $conn->query($sql_transacoes);
    
    echo "<p>Total de transações: " . $result_transacoes->num_rows . "</p>";
    
    if ($result_transacoes->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Tipo</th><th>Valor</th><th>Data</th><th>Descrição</th><th>Categoria</th><th>Ref ID</th><th>Tabela Ref</th></tr>";
        
        while ($row = $result_transacoes->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['tipo'] . "</td>";
            echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
            echo "<td>" . $row['data_transacao'] . "</td>";
            echo "<td>" . htmlspecialchars($row['descricao']) . "</td>";
            echo "<td>" . $row['categoria'] . "</td>";
            echo "<td>" . $row['referencia_id'] . "</td>";
            echo "<td>" . $row['tabela_referencia'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Tabela transacoes_financeiras NÃO existe</p>";
    echo "<p>A tabela precisa ser criada para o sistema financeiro funcionar.</p>";
}

// Verificar vendas existentes
echo "<h2>Vendas Existentes:</h2>";
$sql_vendas = "SELECT id, cliente_id, valor_total, status_venda, data_venda FROM vendas ORDER BY data_venda DESC";
$result_vendas = $conn->query($sql_vendas);

echo "<p>Total de vendas: " . $result_vendas->num_rows . "</p>";

if ($result_vendas->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Cliente ID</th><th>Valor</th><th>Status</th><th>Data</th></tr>";
    
    while ($row = $result_vendas->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['cliente_id'] . "</td>";
        echo "<td>R$ " . number_format($row['valor_total'], 2, ',', '.') . "</td>";
        echo "<td>" . $row['status_venda'] . "</td>";
        echo "<td>" . $row['data_venda'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nenhuma venda encontrada.</p>";
}

$conn->close();

echo "<br><br><a href='dashboard.php'>← Voltar ao Dashboard</a>";
?>
