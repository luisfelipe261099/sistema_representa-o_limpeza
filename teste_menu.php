<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-bug"></i>
        Teste do Menu Marketplace
    </h1>
    <p class="page-subtitle">
        Verificando se o menu do marketplace está aparecendo
    </p>
</div>

<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-info-circle"></i>
        Status do Sistema
    </div>
    <div class="card-body-modern">
        <h5>Verificações:</h5>
        
        <?php
        // Verificar se as tabelas do marketplace existem
        $tabelas_marketplace = [
            'marketplace_links',
            'marketplace_carrinho', 
            'marketplace_pedidos',
            'marketplace_itens_pedido',
            'marketplace_configuracoes'
        ];
        
        echo "<h6>Tabelas do Marketplace:</h6>";
        foreach ($tabelas_marketplace as $tabela) {
            $sql = "SHOW TABLES LIKE '$tabela'";
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                echo "<p style='color: green;'>✅ Tabela $tabela existe</p>";
            } else {
                echo "<p style='color: red;'>❌ Tabela $tabela NÃO existe</p>";
            }
        }
        
        // Verificar se os arquivos do marketplace existem
        $arquivos_marketplace = [
            'marketplace_admin.php',
            'marketplace_pedidos.php',
            'marketplace.php',
            'marketplace_checkout.php'
        ];
        
        echo "<h6>Arquivos do Marketplace:</h6>";
        foreach ($arquivos_marketplace as $arquivo) {
            if (file_exists($arquivo)) {
                echo "<p style='color: green;'>✅ Arquivo $arquivo existe</p>";
            } else {
                echo "<p style='color: red;'>❌ Arquivo $arquivo NÃO existe</p>";
            }
        }
        
        // Verificar se o usuário está logado
        echo "<h6>Status da Sessão:</h6>";
        echo "<p style='color: green;'>✅ Usuário logado: " . ($_SESSION['nome'] ?? 'Não definido') . "</p>";
        echo "<p style='color: green;'>✅ Sessão ativa: " . ($_SESSION['loggedin'] ? 'Sim' : 'Não') . "</p>";
        
        // Verificar se o header.php contém o menu do marketplace
        echo "<h6>Verificação do Header:</h6>";
        $header_content = file_get_contents('includes/header.php');
        if (strpos($header_content, 'marketplace_admin.php') !== false) {
            echo "<p style='color: green;'>✅ Menu do marketplace encontrado no header.php</p>";
        } else {
            echo "<p style='color: red;'>❌ Menu do marketplace NÃO encontrado no header.php</p>";
        }
        ?>
        
        <hr>
        
        <h5>Links Diretos para Teste:</h5>
        <p><a href="marketplace_admin.php" class="btn btn-primary">🔗 Links Marketplace</a></p>
        <p><a href="marketplace_pedidos.php" class="btn btn-info">🛒 Pedidos Marketplace</a></p>
        <p><a href="atualizar_marketplace.php" class="btn btn-warning">⚙️ Atualizar Marketplace</a></p>
        
        <hr>
        
        <h5>Instruções:</h5>
        <div class="alert alert-info">
            <h6>Se o menu não está aparecendo:</h6>
            <ol>
                <li>Execute primeiro: <a href="atualizar_marketplace.php" target="_blank">atualizar_marketplace.php</a></li>
                <li>Limpe o cache do navegador (Ctrl+F5)</li>
                <li>Faça logout e login novamente</li>
                <li>Verifique se todas as tabelas foram criadas acima</li>
            </ol>
        </div>
        
        <div class="alert alert-success">
            <h6>Se tudo estiver OK:</h6>
            <p>O menu deve aparecer na barra lateral esquerda, na seção "Marketplace" com os itens:</p>
            <ul>
                <li>🔗 Links Marketplace</li>
                <li>🛒 Pedidos Marketplace</li>
            </ul>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once 'includes/footer.php';
?>
