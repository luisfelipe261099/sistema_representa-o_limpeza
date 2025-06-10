<?php
// Script para instalar as tabelas do marketplace
require_once 'includes/db_connect.php';

echo "<h2>Instalação do Sistema de Marketplace</h2>";
echo "<p>Este script irá criar as tabelas necessárias para o marketplace.</p>";

$errors = [];
$success = [];

try {
    // 1. Tabela para links exclusivos de clientes
    $sql = "CREATE TABLE IF NOT EXISTS `marketplace_links` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cliente_id` int(11) NOT NULL,
      `token_acesso` varchar(64) NOT NULL UNIQUE,
      `ativo` tinyint(1) DEFAULT 1,
      `data_criacao` datetime DEFAULT current_timestamp(),
      `data_expiracao` datetime DEFAULT NULL,
      `ultimo_acesso` datetime DEFAULT NULL,
      `total_acessos` int(11) DEFAULT 0,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
      INDEX `idx_token` (`token_acesso`),
      INDEX `idx_cliente` (`cliente_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        $success[] = "✓ Tabela marketplace_links criada com sucesso";
    } else {
        $errors[] = "✗ Erro ao criar tabela marketplace_links: " . $conn->error;
    }

    // 2. Tabela para carrinho temporário do marketplace
    $sql = "CREATE TABLE IF NOT EXISTS `marketplace_carrinho` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `token_acesso` varchar(64) NOT NULL,
      `produto_id` int(11) NOT NULL,
      `quantidade` int(11) NOT NULL,
      `preco_unitario` decimal(10,2) NOT NULL,
      `data_adicao` datetime DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
      INDEX `idx_token_carrinho` (`token_acesso`),
      INDEX `idx_produto_carrinho` (`produto_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        $success[] = "✓ Tabela marketplace_carrinho criada com sucesso";
    } else {
        $errors[] = "✗ Erro ao criar tabela marketplace_carrinho: " . $conn->error;
    }

    // 3. Tabela para pedidos do marketplace
    $sql = "CREATE TABLE IF NOT EXISTS `marketplace_pedidos` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cliente_id` int(11) NOT NULL,
      `token_acesso` varchar(64) NOT NULL,
      `numero_pedido` varchar(20) NOT NULL UNIQUE,
      `valor_total` decimal(10,2) NOT NULL,
      `status_pedido` enum('pendente','confirmado','preparando','entregue','cancelado') DEFAULT 'pendente',
      `tipo_faturamento` enum('avista','15_dias','20_dias','30_dias') DEFAULT 'avista',
      `data_vencimento` date DEFAULT NULL,
      `data_entrega_agendada` datetime DEFAULT NULL,
      `endereco_entrega` text DEFAULT NULL,
      `observacoes` text DEFAULT NULL,
      `data_pedido` datetime DEFAULT current_timestamp(),
      `data_confirmacao` datetime DEFAULT NULL,
      `venda_id` int(11) DEFAULT NULL,
      `transacao_financeira_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE SET NULL,
      FOREIGN KEY (`transacao_financeira_id`) REFERENCES `transacoes_financeiras` (`id`) ON DELETE SET NULL,
      INDEX `idx_cliente_pedido` (`cliente_id`),
      INDEX `idx_token_pedido` (`token_acesso`),
      INDEX `idx_numero_pedido` (`numero_pedido`),
      INDEX `idx_status` (`status_pedido`),
      INDEX `idx_venda` (`venda_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        $success[] = "✓ Tabela marketplace_pedidos criada com sucesso";
    } else {
        $errors[] = "✗ Erro ao criar tabela marketplace_pedidos: " . $conn->error;
    }

    // 4. Tabela para itens dos pedidos do marketplace
    $sql = "CREATE TABLE IF NOT EXISTS `marketplace_itens_pedido` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `pedido_id` int(11) NOT NULL,
      `produto_id` int(11) NOT NULL,
      `quantidade` int(11) NOT NULL,
      `preco_unitario` decimal(10,2) NOT NULL,
      `subtotal` decimal(10,2) NOT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`pedido_id`) REFERENCES `marketplace_pedidos` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
      INDEX `idx_pedido_item` (`pedido_id`),
      INDEX `idx_produto_item` (`produto_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        $success[] = "✓ Tabela marketplace_itens_pedido criada com sucesso";
    } else {
        $errors[] = "✗ Erro ao criar tabela marketplace_itens_pedido: " . $conn->error;
    }

    // 5. Tabela para configurações do marketplace
    $sql = "CREATE TABLE IF NOT EXISTS `marketplace_configuracoes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `chave` varchar(100) NOT NULL UNIQUE,
      `valor` text DEFAULT NULL,
      `descricao` varchar(255) DEFAULT NULL,
      `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      INDEX `idx_chave` (`chave`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        $success[] = "✓ Tabela marketplace_configuracoes criada com sucesso";
    } else {
        $errors[] = "✗ Erro ao criar tabela marketplace_configuracoes: " . $conn->error;
    }

    // 6. Inserir configurações padrão
    $configuracoes = [
        ['marketplace_ativo', '1', 'Marketplace ativo (1) ou inativo (0)'],
        ['titulo_marketplace', 'Karla Wollinger - Marketplace', 'Título do marketplace'],
        ['descricao_marketplace', 'Faça seus pedidos online de forma rápida e prática', 'Descrição do marketplace'],
        ['email_notificacoes', 'contato@karlawollinger.com', 'Email para receber notificações de pedidos'],
        ['prazo_entrega_padrao', '3', 'Prazo padrão de entrega em dias úteis'],
        ['valor_minimo_pedido', '0.00', 'Valor mínimo para pedidos'],
        ['permitir_agendamento', '1', 'Permitir agendamento de entrega (1) ou não (0)'],
        ['horario_funcionamento', '08:00-18:00', 'Horário de funcionamento para entregas'],
        ['dias_funcionamento', '1,2,3,4,5', 'Dias da semana que funciona (1=segunda, 7=domingo)']
    ];

    foreach ($configuracoes as $config) {
        $sql = "INSERT IGNORE INTO marketplace_configuracoes (chave, valor, descricao) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $config[0], $config[1], $config[2]);
        $stmt->execute();
    }
    $success[] = "✓ Configurações padrão inseridas";

    // 7. Adicionar campos extras na tabela produtos para marketplace
    $campos_produtos = [
        "ADD COLUMN `ativo_marketplace` tinyint(1) DEFAULT 1",
        "ADD COLUMN `destaque_marketplace` tinyint(1) DEFAULT 0",
        "ADD COLUMN `ordem_exibicao` int(11) DEFAULT 0",
        "ADD COLUMN `imagem_url` varchar(255) DEFAULT NULL",
        "ADD COLUMN `descricao_completa` text DEFAULT NULL"
    ];

    foreach ($campos_produtos as $campo) {
        $sql = "ALTER TABLE produtos $campo";
        if ($conn->query($sql)) {
            $success[] = "✓ Campo adicionado à tabela produtos: " . explode('`', $campo)[1];
        } else {
            // Ignorar erro se campo já existe
            if (strpos($conn->error, 'Duplicate column name') === false) {
                $errors[] = "✗ Erro ao adicionar campo à tabela produtos: " . $conn->error;
            }
        }
    }

    // 7.1. Adicionar campos de integração na tabela marketplace_pedidos (se já existir)
    $campos_pedidos = [
        "ADD COLUMN `venda_id` int(11) DEFAULT NULL",
        "ADD COLUMN `transacao_financeira_id` int(11) DEFAULT NULL"
    ];

    foreach ($campos_pedidos as $campo) {
        $sql = "ALTER TABLE marketplace_pedidos $campo";
        if ($conn->query($sql)) {
            $success[] = "✓ Campo de integração adicionado à tabela marketplace_pedidos";
        } else {
            // Ignorar erro se campo já existe
            if (strpos($conn->error, 'Duplicate column name') === false) {
                $errors[] = "✗ Erro ao adicionar campo à tabela marketplace_pedidos: " . $conn->error;
            }
        }
    }

    // 7.2. Adicionar foreign keys para integração
    $foreign_keys = [
        "ADD FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE SET NULL",
        "ADD FOREIGN KEY (`transacao_financeira_id`) REFERENCES `transacoes_financeiras` (`id`) ON DELETE SET NULL"
    ];

    foreach ($foreign_keys as $fk) {
        $sql = "ALTER TABLE marketplace_pedidos $fk";
        if ($conn->query($sql)) {
            $success[] = "✓ Foreign key de integração adicionada";
        } else {
            // Ignorar erro se foreign key já existe
            if (strpos($conn->error, 'Duplicate foreign key constraint name') === false &&
                strpos($conn->error, 'foreign key constraint fails') === false) {
                $errors[] = "✗ Erro ao adicionar foreign key: " . $conn->error;
            }
        }
    }

    // 8. Adicionar índices para melhor performance
    $indices = [
        "ADD INDEX `idx_ativo_marketplace` (`ativo_marketplace`)",
        "ADD INDEX `idx_destaque` (`destaque_marketplace`)",
        "ADD INDEX `idx_empresa_ativo` (`empresa_id`, `ativo_marketplace`)"
    ];

    foreach ($indices as $indice) {
        $sql = "ALTER TABLE produtos $indice";
        if ($conn->query($sql)) {
            $success[] = "✓ Índice adicionado à tabela produtos";
        } else {
            // Ignorar erro se índice já existe
            if (strpos($conn->error, 'Duplicate key name') === false) {
                $errors[] = "✗ Erro ao adicionar índice à tabela produtos: " . $conn->error;
            }
        }
    }

    // 9. Criar trigger para gerar número do pedido automaticamente
    $sql = "DROP TRIGGER IF EXISTS gerar_numero_pedido";
    $conn->query($sql);

    $sql = "CREATE TRIGGER gerar_numero_pedido 
            BEFORE INSERT ON marketplace_pedidos
            FOR EACH ROW 
            BEGIN
                DECLARE novo_numero VARCHAR(20);
                DECLARE contador INT;
                
                SELECT COALESCE(MAX(CAST(SUBSTRING(numero_pedido, 4) AS UNSIGNED)), 0) + 1 
                INTO contador 
                FROM marketplace_pedidos 
                WHERE numero_pedido LIKE CONCAT(DATE_FORMAT(NOW(), '%y%m'), '%');
                
                SET novo_numero = CONCAT(DATE_FORMAT(NOW(), '%y%m'), LPAD(contador, 4, '0'));
                SET NEW.numero_pedido = novo_numero;
                
                IF NEW.tipo_faturamento = '15_dias' THEN
                    SET NEW.data_vencimento = DATE_ADD(CURDATE(), INTERVAL 15 DAY);
                ELSEIF NEW.tipo_faturamento = '20_dias' THEN
                    SET NEW.data_vencimento = DATE_ADD(CURDATE(), INTERVAL 20 DAY);
                ELSEIF NEW.tipo_faturamento = '30_dias' THEN
                    SET NEW.data_vencimento = DATE_ADD(CURDATE(), INTERVAL 30 DAY);
                ELSE
                    SET NEW.data_vencimento = CURDATE();
                END IF;
            END";
    
    if ($conn->query($sql)) {
        $success[] = "✓ Trigger para geração automática de número do pedido criado";
    } else {
        $errors[] = "✗ Erro ao criar trigger: " . $conn->error;
    }

    // 10. Criar view para relatórios do marketplace
    $sql = "DROP VIEW IF EXISTS vw_marketplace_vendas";
    $conn->query($sql);

    $sql = "CREATE VIEW vw_marketplace_vendas AS
            SELECT 
                mp.id,
                mp.numero_pedido,
                c.nome as cliente_nome,
                c.email as cliente_email,
                mp.valor_total,
                mp.status_pedido,
                mp.tipo_faturamento,
                mp.data_pedido,
                mp.data_entrega_agendada,
                mp.data_vencimento,
                COUNT(mip.id) as total_itens
            FROM marketplace_pedidos mp
            LEFT JOIN clientes c ON mp.cliente_id = c.id
            LEFT JOIN marketplace_itens_pedido mip ON mp.id = mip.pedido_id
            GROUP BY mp.id";
    
    if ($conn->query($sql)) {
        $success[] = "✓ View vw_marketplace_vendas criada";
    } else {
        $errors[] = "✗ Erro ao criar view: " . $conn->error;
    }

} catch (Exception $e) {
    $errors[] = "✗ Erro geral: " . $e->getMessage();
}

// Exibir resultados
echo "<h3>Resultados da Instalação:</h3>";

if (!empty($success)) {
    echo "<div style='color: green; margin-bottom: 20px;'>";
    foreach ($success as $msg) {
        echo "<p>$msg</p>";
    }
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div style='color: red; margin-bottom: 20px;'>";
    foreach ($errors as $msg) {
        echo "<p>$msg</p>";
    }
    echo "</div>";
}

if (empty($errors)) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✓ Instalação Concluída com Sucesso!</h4>";
    echo "<p>O sistema de marketplace foi instalado e está pronto para uso.</p>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>Acesse <a href='marketplace_admin.php'>Marketplace - Links Exclusivos</a> para gerar links para seus clientes</li>";
    echo "<li>Configure os produtos que estarão disponíveis no marketplace</li>";
    echo "<li>Gerencie os pedidos em <a href='marketplace_pedidos.php'>Pedidos Marketplace</a></li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>⚠ Instalação Concluída com Avisos</h4>";
    echo "<p>Alguns erros ocorreram durante a instalação. Verifique os detalhes acima.</p>";
    echo "</div>";
}

$conn->close();
?>
