<?php
session_start();

// Verifica se o usuário está logado e é admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

if (!isset($_SESSION["nivel_acesso"]) || $_SESSION["nivel_acesso"] !== "admin") {
    echo "Acesso negado. Apenas administradores podem executar esta sincronização.";
    exit;
}

require_once 'includes/db_connect.php';

echo "<h1>Sincronização do Sistema Financeiro</h1>";
echo "<p>Este script irá sincronizar vendas concluídas com o sistema financeiro.</p>";

$vendas_sincronizadas = 0;
$vendas_ja_sincronizadas = 0;
$erros = 0;

try {
    $conn->begin_transaction();
    
    // Buscar todas as vendas concluídas
    $sql_vendas = "SELECT id, valor_total, data_venda FROM vendas WHERE status_venda = 'concluida'";
    $result_vendas = $conn->query($sql_vendas);
    
    echo "<h2>Vendas Concluídas Encontradas: " . $result_vendas->num_rows . "</h2>";
    
    if ($result_vendas->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Venda ID</th><th>Valor</th><th>Data</th><th>Status Financeiro</th><th>Ação</th></tr>";
        
        while ($venda = $result_vendas->fetch_assoc()) {
            $venda_id = $venda['id'];
            $valor_total = $venda['valor_total'];
            $data_venda = $venda['data_venda'];
            
            // Verificar se já existe transação financeira para esta venda
            $sql_check = "SELECT id FROM transacoes_financeiras WHERE referencia_id = ? AND tabela_referencia = 'vendas'";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("i", $venda_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            echo "<tr>";
            echo "<td>Venda #" . $venda_id . "</td>";
            echo "<td>R$ " . number_format($valor_total, 2, ',', '.') . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($data_venda)) . "</td>";
            
            if ($result_check->num_rows > 0) {
                echo "<td style='color: green;'>✅ Já sincronizada</td>";
                echo "<td>-</td>";
                $vendas_ja_sincronizadas++;
            } else {
                // Criar transação financeira
                $descricao = "Receita da Venda #" . $venda_id . " (Sincronização)";
                $categoria = "Vendas";
                
                $sql_transacao = "INSERT INTO transacoes_financeiras (tipo, valor, descricao, categoria, referencia_id, tabela_referencia, data_transacao) VALUES ('entrada', ?, ?, ?, ?, 'vendas', ?)";
                $stmt_transacao = $conn->prepare($sql_transacao);
                $stmt_transacao->bind_param("dssis", $valor_total, $descricao, $categoria, $venda_id, $data_venda);
                
                if ($stmt_transacao->execute()) {
                    echo "<td style='color: blue;'>🔄 Sincronizada agora</td>";
                    echo "<td>✅ Criada</td>";
                    $vendas_sincronizadas++;
                } else {
                    echo "<td style='color: red;'>❌ Erro</td>";
                    echo "<td>Erro: " . $stmt_transacao->error . "</td>";
                    $erros++;
                }
                $stmt_transacao->close();
            }
            echo "</tr>";
            
            $stmt_check->close();
        }
        echo "</table>";
    }
    
    $conn->commit();
    
    echo "<h2>Resumo da Sincronização:</h2>";
    echo "<ul>";
    echo "<li><strong>Vendas já sincronizadas:</strong> " . $vendas_ja_sincronizadas . "</li>";
    echo "<li><strong>Vendas sincronizadas agora:</strong> " . $vendas_sincronizadas . "</li>";
    echo "<li><strong>Erros:</strong> " . $erros . "</li>";
    echo "</ul>";
    
    if ($vendas_sincronizadas > 0) {
        echo "<p style='color: green;'>✅ Sincronização concluída com sucesso!</p>";
        echo "<p>As vendas concluídas foram registradas como entradas financeiras.</p>";
    } else if ($vendas_ja_sincronizadas > 0) {
        echo "<p style='color: blue;'>ℹ️ Todas as vendas já estavam sincronizadas.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Nenhuma venda concluída encontrada para sincronizar.</p>";
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<p style='color: red;'>❌ Erro durante a sincronização: " . $e->getMessage() . "</p>";
}

// Mostrar estatísticas atuais
echo "<h2>Estatísticas Financeiras Atuais:</h2>";
$sql_stats = "SELECT 
    SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as total_entradas,
    SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas,
    COUNT(*) as total_transacoes
FROM transacoes_financeiras";

$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();

$saldo_atual = $stats['total_entradas'] - $stats['total_saidas'];

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Métrica</th><th>Valor</th></tr>";
echo "<tr><td>Total de Entradas</td><td style='color: green;'>R$ " . number_format($stats['total_entradas'], 2, ',', '.') . "</td></tr>";
echo "<tr><td>Total de Saídas</td><td style='color: red;'>R$ " . number_format($stats['total_saidas'], 2, ',', '.') . "</td></tr>";
echo "<tr><td>Saldo Atual</td><td style='color: " . ($saldo_atual >= 0 ? 'green' : 'red') . ";'><strong>R$ " . number_format($saldo_atual, 2, ',', '.') . "</strong></td></tr>";
echo "<tr><td>Total de Transações</td><td>" . $stats['total_transacoes'] . "</td></tr>";
echo "</table>";

$conn->close();

echo "<br><br>";
echo "<a href='financeiro.php' class='btn btn-primary'>Ver Controle Financeiro</a> ";
echo "<a href='dashboard.php' class='btn btn-secondary'>Voltar ao Dashboard</a>";
?>
