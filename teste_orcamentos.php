<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

echo "<h1>Teste de Orçamentos</h1>";

// Verificar estrutura da tabela orcamentos
echo "<h2>Estrutura da Tabela orcamentos:</h2>";
$sql_describe = "DESCRIBE orcamentos";
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

// Verificar se há orçamentos
echo "<h2>Orçamentos no Banco:</h2>";
$sql_orcamentos = "SELECT * FROM orcamentos ORDER BY id DESC";
$result_orcamentos = $conn->query($sql_orcamentos);

echo "<p>Total de orçamentos: " . $result_orcamentos->num_rows . "</p>";

if ($result_orcamentos->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Cliente ID</th><th>Valor Total</th><th>Status</th><th>Data Criação</th><th>Observações</th></tr>";
    
    while ($row = $result_orcamentos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['cliente_id'] . "</td>";
        echo "<td>R$ " . number_format($row['valor_total'], 2, ',', '.') . "</td>";
        echo "<td>" . $row['status_orcamento'] . "</td>";
        echo "<td>" . (isset($row['data_orcamento']) ? $row['data_orcamento'] : 'Campo não existe') . "</td>";
        echo "<td>" . htmlspecialchars($row['observacoes']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Nenhum orçamento encontrado!</p>";
    echo "<p>Você precisa criar orçamentos primeiro.</p>";
    echo "<a href='criar_orcamento.php'>Criar Orçamento</a>";
}

// Testar a consulta da página orcamentos.php
echo "<h2>Teste da Consulta Original:</h2>";
$sql_select_orcamentos = "SELECT o.id, c.nome AS nome_cliente, o.data_orcamento, o.valor_total, o.status_orcamento
                          FROM orcamentos o
                          LEFT JOIN clientes c ON o.cliente_id = c.id
                          ORDER BY o.data_orcamento DESC";
$result_test = $conn->query($sql_select_orcamentos);

if ($result_test) {
    echo "<p style='color: green;'>✅ Consulta executada com sucesso</p>";
    echo "<p>Registros encontrados: " . $result_test->num_rows . "</p>";
    
    if ($result_test->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Cliente</th><th>Data</th><th>Valor</th><th>Status</th></tr>";
        
        while ($row = $result_test->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['nome_cliente']) . "</td>";
            echo "<td>" . $row['data_orcamento'] . "</td>";
            echo "<td>R$ " . number_format($row['valor_total'], 2, ',', '.') . "</td>";
            echo "<td>" . $row['status_orcamento'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>❌ Erro na consulta: " . $conn->error . "</p>";
}

$conn->close();

echo "<br><br><a href='dashboard.php'>← Voltar ao Dashboard</a>";
?>
