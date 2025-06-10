<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Teste de Conexão com Banco de Dados</h2>";

try {
    require_once 'includes/db_connect.php';
    echo "<p style='color: green;'>✓ Conexão com banco de dados estabelecida com sucesso!</p>";
    
    // Test basic queries
    echo "<h3>Testando consultas básicas:</h3>";
    
    // Test empresas table
    $result = $conn->query("SELECT COUNT(*) as count FROM empresas_representadas");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>✓ Tabela empresas_representadas: " . $row['count'] . " registros</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na tabela empresas_representadas: " . $conn->error . "</p>";
    }
    
    // Test clientes table
    $result = $conn->query("SELECT COUNT(*) as count FROM clientes");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>✓ Tabela clientes: " . $row['count'] . " registros</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na tabela clientes: " . $conn->error . "</p>";
    }
    
    // Test produtos table
    $result = $conn->query("SELECT COUNT(*) as count FROM produtos");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>✓ Tabela produtos: " . $row['count'] . " registros</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na tabela produtos: " . $conn->error . "</p>";
    }
    
    // Test orcamentos table
    $result = $conn->query("SELECT COUNT(*) as count FROM orcamentos");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>✓ Tabela orcamentos: " . $row['count'] . " registros</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na tabela orcamentos: " . $conn->error . "</p>";
    }
    
    // Test vendas table
    $result = $conn->query("SELECT COUNT(*) as count FROM vendas");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>✓ Tabela vendas: " . $row['count'] . " registros</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na tabela vendas: " . $conn->error . "</p>";
    }
    
    // Test transacoes_financeiras table
    $result = $conn->query("SELECT COUNT(*) as count FROM transacoes_financeiras");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>✓ Tabela transacoes_financeiras: " . $row['count'] . " registros</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na tabela transacoes_financeiras: " . $conn->error . "</p>";
    }
    
    // Test the specific query from orcamentos.php
    echo "<h3>Testando consulta específica do orcamentos.php:</h3>";
    $sql_test = "SELECT o.id, c.nome AS nome_cliente, o.data_orcamento, o.valor_total, o.status_orcamento
                 FROM orcamentos o
                 LEFT JOIN clientes c ON o.cliente_id = c.id
                 ORDER BY o.data_orcamento DESC";
    $result = $conn->query($sql_test);
    if ($result) {
        echo "<p style='color: green;'>✓ Consulta de orçamentos executada com sucesso!</p>";
        echo "<p>Número de orçamentos encontrados: " . $result->num_rows . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na consulta de orçamentos: " . $conn->error . "</p>";
    }
    
    // Test the specific query from relatorios.php
    echo "<h3>Testando consultas específicas do relatorios.php:</h3>";
    $sql_test = "SELECT id, nome_empresa as nome FROM empresas_representadas ORDER BY nome_empresa ASC";
    $result = $conn->query($sql_test);
    if ($result) {
        echo "<p style='color: green;'>✓ Consulta de empresas executada com sucesso!</p>";
        echo "<p>Número de empresas encontradas: " . $result->num_rows . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro na consulta de empresas: " . $conn->error . "</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro: " . $e->getMessage() . "</p>";
}
?>
