<?php
// Script para verificar e corrigir estrutura das tabelas
require_once 'includes/db_connect.php';

echo "<h2>🔍 Verificação da Estrutura das Tabelas</h2>";
echo "<p>Verificando se todas as tabelas têm a estrutura correta...</p>";

$errors = [];
$success = [];

try {
    // 1. Verificar estrutura da tabela orcamentos
    echo "<h3>1. Verificando tabela 'orcamentos'...</h3>";
    
    $sql = "DESCRIBE orcamentos";
    $result = $conn->query($sql);
    
    if ($result) {
        $colunas = [];
        while ($row = $result->fetch_assoc()) {
            $colunas[] = $row['Field'];
        }
        
        echo "<p><strong>Colunas encontradas:</strong> " . implode(', ', $colunas) . "</p>";
        
        // Verificar se tem data_orcamento (correto) ou data_criacao (incorreto)
        if (in_array('data_orcamento', $colunas)) {
            $success[] = "✓ Tabela orcamentos tem a coluna 'data_orcamento' (correto)";
        } else {
            $errors[] = "✗ Tabela orcamentos não tem a coluna 'data_orcamento'";
        }
        
        if (in_array('data_criacao', $colunas)) {
            $errors[] = "✗ Tabela orcamentos tem coluna 'data_criacao' (incorreto - deveria ser 'data_orcamento')";
        }
        
    } else {
        $errors[] = "✗ Erro ao verificar tabela orcamentos: " . $conn->error;
    }

    // 2. Verificar estrutura da tabela marketplace_pedidos
    echo "<h3>2. Verificando tabela 'marketplace_pedidos'...</h3>";
    
    $sql = "SHOW TABLES LIKE 'marketplace_pedidos'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $sql = "DESCRIBE marketplace_pedidos";
        $result = $conn->query($sql);
        
        if ($result) {
            $colunas = [];
            while ($row = $result->fetch_assoc()) {
                $colunas[] = $row['Field'];
            }
            
            echo "<p><strong>Colunas encontradas:</strong> " . implode(', ', $colunas) . "</p>";
            
            // Verificar campos de integração
            if (in_array('venda_id', $colunas)) {
                $success[] = "✓ Tabela marketplace_pedidos tem campo 'venda_id' para integração";
            } else {
                $errors[] = "✗ Tabela marketplace_pedidos não tem campo 'venda_id' - execute atualizar_marketplace.php";
            }
            
            if (in_array('transacao_financeira_id', $colunas)) {
                $success[] = "✓ Tabela marketplace_pedidos tem campo 'transacao_financeira_id' para integração";
            } else {
                $errors[] = "✗ Tabela marketplace_pedidos não tem campo 'transacao_financeira_id' - execute atualizar_marketplace.php";
            }
            
        } else {
            $errors[] = "✗ Erro ao verificar estrutura da tabela marketplace_pedidos: " . $conn->error;
        }
    } else {
        $errors[] = "✗ Tabela marketplace_pedidos não existe - execute atualizar_marketplace.php";
    }

    // 3. Verificar estrutura da tabela produtos
    echo "<h3>3. Verificando tabela 'produtos'...</h3>";
    
    $sql = "DESCRIBE produtos";
    $result = $conn->query($sql);
    
    if ($result) {
        $colunas = [];
        while ($row = $result->fetch_assoc()) {
            $colunas[] = $row['Field'];
        }
        
        echo "<p><strong>Colunas encontradas:</strong> " . implode(', ', $colunas) . "</p>";
        
        // Verificar campos do marketplace
        $campos_marketplace = ['ativo_marketplace', 'destaque_marketplace', 'ordem_exibicao', 'imagem_url', 'descricao_completa'];
        
        foreach ($campos_marketplace as $campo) {
            if (in_array($campo, $colunas)) {
                $success[] = "✓ Tabela produtos tem campo '$campo' para marketplace";
            } else {
                $errors[] = "✗ Tabela produtos não tem campo '$campo' - execute atualizar_marketplace.php";
            }
        }
        
    } else {
        $errors[] = "✗ Erro ao verificar tabela produtos: " . $conn->error;
    }

    // 4. Verificar outras tabelas importantes
    echo "<h3>4. Verificando outras tabelas...</h3>";
    
    $tabelas_importantes = [
        'clientes' => ['nome', 'email', 'telefone'],
        'vendas' => ['cliente_id', 'valor_total', 'data_venda'],
        'transacoes_financeiras' => ['tipo', 'valor', 'data_transacao'],
        'empresas_representadas' => ['nome_empresa']
    ];
    
    foreach ($tabelas_importantes as $tabela => $campos_obrigatorios) {
        $sql = "DESCRIBE $tabela";
        $result = $conn->query($sql);
        
        if ($result) {
            $colunas = [];
            while ($row = $result->fetch_assoc()) {
                $colunas[] = $row['Field'];
            }
            
            $campos_faltando = array_diff($campos_obrigatorios, $colunas);
            
            if (empty($campos_faltando)) {
                $success[] = "✓ Tabela '$tabela' tem todos os campos obrigatórios";
            } else {
                $errors[] = "✗ Tabela '$tabela' está faltando campos: " . implode(', ', $campos_faltando);
            }
        } else {
            $errors[] = "✗ Tabela '$tabela' não existe ou erro ao verificar: " . $conn->error;
        }
    }

    // 5. Verificar se há dados de teste
    echo "<h3>5. Verificando dados...</h3>";
    
    $sql = "SELECT COUNT(*) as total FROM clientes";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $success[] = "✓ Total de clientes: " . $row['total'];
    }
    
    $sql = "SELECT COUNT(*) as total FROM produtos";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $success[] = "✓ Total de produtos: " . $row['total'];
    }
    
    $sql = "SELECT COUNT(*) as total FROM empresas_representadas";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $success[] = "✓ Total de empresas representadas: " . $row['total'];
    }

} catch (Exception $e) {
    $errors[] = "✗ Erro geral: " . $e->getMessage();
}

// Exibir resultados
echo "<h3>📊 Resultados da Verificação:</h3>";

if (!empty($success)) {
    echo "<div style='color: green; margin-bottom: 20px;'>";
    echo "<h4>✅ Sucessos:</h4>";
    foreach ($success as $msg) {
        echo "<p>$msg</p>";
    }
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div style='color: red; margin-bottom: 20px;'>";
    echo "<h4>❌ Problemas Encontrados:</h4>";
    foreach ($errors as $msg) {
        echo "<p>$msg</p>";
    }
    echo "</div>";
}

if (empty($errors)) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>🎉 ESTRUTURA CORRETA!</h4>";
    echo "<p>Todas as tabelas estão com a estrutura correta.</p>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>✅ O erro do orçamento foi corrigido</li>";
    echo "<li>✅ Teste criar um orçamento agora</li>";
    echo "<li>✅ Acesse o marketplace se as tabelas estiverem OK</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>⚠ AÇÃO NECESSÁRIA</h4>";
    echo "<p>Alguns problemas foram encontrados na estrutura das tabelas.</p>";
    echo "<p><strong>Soluções:</strong></p>";
    echo "<ul>";
    echo "<li>Para problemas do marketplace: Execute <a href='atualizar_marketplace.php'>atualizar_marketplace.php</a></li>";
    echo "<li>Para outros problemas: Verifique o arquivo SQL original</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<h4>🔧 Links Úteis:</h4>";
echo "<p><a href='atualizar_marketplace.php' class='btn btn-warning'>⚙️ Atualizar Marketplace</a></p>";
echo "<p><a href='teste_menu.php' class='btn btn-info'>🔍 Testar Menu</a></p>";
echo "<p><a href='criar_orcamento.php' class='btn btn-success'>📝 Testar Criar Orçamento</a></p>";

$conn->close();
?>
