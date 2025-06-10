-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 07/06/2025 às 14:45
-- Versão do servidor: 10.11.10-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u182607388_karla_wollinge`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `agendamentos_entrega`
--

CREATE TABLE `agendamentos_entrega` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) DEFAULT NULL,
  `orcamento_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `data_hora_entrega` datetime NOT NULL,
  `endereco_entrega` varchar(255) NOT NULL,
  `status_entrega` enum('agendado','em_rota','entregue','cancelado') DEFAULT 'agendado',
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `tipo_pessoa` enum('fisica','juridica') NOT NULL,
  `cpf_cnpj` varchar(22) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `data_cadastro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresas_representadas`
--

CREATE TABLE `empresas_representadas` (
  `id` int(11) NOT NULL,
  `nome_empresa` varchar(255) NOT NULL,
  `razao_social` varchar(255) DEFAULT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contato_responsavel` varchar(100) DEFAULT NULL,
  `telefone_responsavel` varchar(20) DEFAULT NULL,
  `email_responsavel` varchar(100) DEFAULT NULL,
  `logo_empresa` varchar(255) DEFAULT NULL COMMENT 'Caminho para o arquivo de logo da empresa representada',
  `comissao_padrao` decimal(5,2) DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `data_inicio_representacao` date DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_orcamentos`
--

CREATE TABLE `historico_orcamentos` (
  `id` int(11) NOT NULL,
  `orcamento_id` int(11) NOT NULL,
  `status_anterior` varchar(50) NOT NULL,
  `status_novo` varchar(50) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `data_alteracao` datetime NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_orcamento`
--

CREATE TABLE `itens_orcamento` (
  `id` int(11) NOT NULL,
  `orcamento_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_venda`
--

CREATE TABLE `itens_venda` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `marketplace_carrinho`
--

CREATE TABLE `marketplace_carrinho` (
  `id` int(11) NOT NULL,
  `token_acesso` varchar(64) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `data_adicao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `marketplace_configuracoes`
--

CREATE TABLE `marketplace_configuracoes` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `marketplace_itens_pedido`
--

CREATE TABLE `marketplace_itens_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `marketplace_links`
--

CREATE TABLE `marketplace_links` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `token_acesso` varchar(64) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_expiracao` datetime DEFAULT NULL,
  `ultimo_acesso` datetime DEFAULT NULL,
  `total_acessos` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `marketplace_pedidos`
--

CREATE TABLE `marketplace_pedidos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `token_acesso` varchar(64) NOT NULL,
  `numero_pedido` varchar(20) NOT NULL,
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
  `transacao_financeira_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `marketplace_pedidos`
--
DELIMITER $$
CREATE TRIGGER `gerar_numero_pedido` BEFORE INSERT ON `marketplace_pedidos` FOR EACH ROW BEGIN
    DECLARE novo_numero VARCHAR(20);
    DECLARE contador INT;
    
    -- Buscar o próximo número sequencial
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_pedido, 4) AS UNSIGNED)), 0) + 1 
    INTO contador 
    FROM marketplace_pedidos 
    WHERE numero_pedido LIKE CONCAT(DATE_FORMAT(NOW(), '%y%m'), '%');
    
    -- Gerar número no formato YYMM0001
    SET novo_numero = CONCAT(DATE_FORMAT(NOW(), '%y%m'), LPAD(contador, 4, '0'));
    SET NEW.numero_pedido = novo_numero;
    
    -- Calcular data de vencimento baseada no tipo de faturamento
    IF NEW.tipo_faturamento = '15_dias' THEN
        SET NEW.data_vencimento = DATE_ADD(CURDATE(), INTERVAL 15 DAY);
    ELSEIF NEW.tipo_faturamento = '20_dias' THEN
        SET NEW.data_vencimento = DATE_ADD(CURDATE(), INTERVAL 20 DAY);
    ELSEIF NEW.tipo_faturamento = '30_dias' THEN
        SET NEW.data_vencimento = DATE_ADD(CURDATE(), INTERVAL 30 DAY);
    ELSE
        SET NEW.data_vencimento = CURDATE();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `metas_vendas`
--

CREATE TABLE `metas_vendas` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `mes` int(11) NOT NULL,
  `ano` int(11) NOT NULL,
  `meta_valor` decimal(10,2) NOT NULL,
  `meta_quantidade` int(11) DEFAULT 0,
  `valor_alcancado` decimal(10,2) DEFAULT 0.00,
  `quantidade_alcancada` int(11) DEFAULT 0,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacoes_financeiras`
--

CREATE TABLE `movimentacoes_financeiras` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `categoria` enum('comissao','despesa','investimento','outros') NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_movimentacao` date NOT NULL,
  `venda_id` int(11) DEFAULT NULL,
  `orcamento_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `orcamentos`
--

CREATE TABLE `orcamentos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `data_orcamento` datetime DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `status_orcamento` enum('pendente','aprovado','rejeitado','convertido_venda') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `empresa_id` int(11) DEFAULT NULL,
  `comissao_percentual` decimal(5,2) DEFAULT 0.00,
  `valor_comissao` decimal(10,2) DEFAULT 0.00,
  `data_criacao` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `preco_venda` decimal(10,2) NOT NULL,
  `percentual_lucro` decimal(5,2) NOT NULL DEFAULT 0.00,
  `quantidade_estoque` int(11) NOT NULL DEFAULT 0,
  `estoque_minimo` int(11) DEFAULT 5,
  `fornecedor` varchar(100) DEFAULT NULL,
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `empresa_id` int(11) DEFAULT NULL,
  `ativo_marketplace` tinyint(1) DEFAULT 1,
  `destaque_marketplace` tinyint(1) DEFAULT 0,
  `ordem_exibicao` int(11) DEFAULT 0,
  `imagem_url` varchar(255) DEFAULT NULL,
  `descricao_completa` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes_financeiras`
--

CREATE TABLE `transacoes_financeiras` (
  `id` int(11) NOT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_transacao` datetime DEFAULT current_timestamp(),
  `descricao` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `tabela_referencia` varchar(50) DEFAULT NULL,
  `empresa_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel_acesso` enum('admin','colaborador') DEFAULT 'colaborador',
  `ativo` tinyint(1) DEFAULT 1,
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `ultimo_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `data_venda` datetime DEFAULT current_timestamp(),
  `valor_total` decimal(10,2) NOT NULL,
  `forma_pagamento` varchar(50) NOT NULL,
  `status_venda` enum('pendente','concluida','cancelada') DEFAULT 'pendente',
  `empresa_id` int(11) DEFAULT NULL,
  `comissao_percentual` decimal(5,2) DEFAULT 0.00,
  `valor_comissao` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_estoque_por_empresa`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_estoque_por_empresa` (
`nome_empresa` varchar(255)
,`empresa_id` int(11)
,`total_produtos` bigint(21)
,`total_estoque` decimal(32,0)
,`produtos_criticos` decimal(22,0)
,`valor_estoque` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_marketplace_vendas`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_marketplace_vendas` (
`id` int(11)
,`numero_pedido` varchar(20)
,`cliente_nome` varchar(255)
,`cliente_email` varchar(100)
,`valor_total` decimal(10,2)
,`status_pedido` enum('pendente','confirmado','preparando','entregue','cancelado')
,`tipo_faturamento` enum('avista','15_dias','20_dias','30_dias')
,`data_pedido` datetime
,`data_entrega_agendada` datetime
,`data_vencimento` date
,`total_itens` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_vendas_por_empresa`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_vendas_por_empresa` (
`nome_empresa` varchar(255)
,`empresa_id` int(11)
,`total_vendas` bigint(21)
,`valor_total_vendas` decimal(32,2)
,`total_comissoes` decimal(32,2)
,`media_comissao` decimal(9,6)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_estoque_por_empresa`
--
DROP TABLE IF EXISTS `vw_estoque_por_empresa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u182607388_karla_wollinge`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_estoque_por_empresa`  AS SELECT `e`.`nome_empresa` AS `nome_empresa`, `e`.`id` AS `empresa_id`, count(`p`.`id`) AS `total_produtos`, sum(`p`.`quantidade_estoque`) AS `total_estoque`, sum(case when `p`.`quantidade_estoque` <= `p`.`estoque_minimo` then 1 else 0 end) AS `produtos_criticos`, sum(`p`.`quantidade_estoque` * `p`.`preco_venda`) AS `valor_estoque` FROM (`empresas_representadas` `e` left join `produtos` `p` on(`e`.`id` = `p`.`empresa_id`)) GROUP BY `e`.`id`, `e`.`nome_empresa` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_marketplace_vendas`
--
DROP TABLE IF EXISTS `vw_marketplace_vendas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u182607388_karla_wollinge`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_marketplace_vendas`  AS SELECT `mp`.`id` AS `id`, `mp`.`numero_pedido` AS `numero_pedido`, `c`.`nome` AS `cliente_nome`, `c`.`email` AS `cliente_email`, `mp`.`valor_total` AS `valor_total`, `mp`.`status_pedido` AS `status_pedido`, `mp`.`tipo_faturamento` AS `tipo_faturamento`, `mp`.`data_pedido` AS `data_pedido`, `mp`.`data_entrega_agendada` AS `data_entrega_agendada`, `mp`.`data_vencimento` AS `data_vencimento`, count(`mip`.`id`) AS `total_itens` FROM ((`marketplace_pedidos` `mp` left join `clientes` `c` on(`mp`.`cliente_id` = `c`.`id`)) left join `marketplace_itens_pedido` `mip` on(`mp`.`id` = `mip`.`pedido_id`)) GROUP BY `mp`.`id` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_vendas_por_empresa`
--
DROP TABLE IF EXISTS `vw_vendas_por_empresa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u182607388_karla_wollinge`@`127.0.0.1` SQL SECURITY DEFINER VIEW `vw_vendas_por_empresa`  AS SELECT `e`.`nome_empresa` AS `nome_empresa`, `e`.`id` AS `empresa_id`, count(`v`.`id`) AS `total_vendas`, sum(`v`.`valor_total`) AS `valor_total_vendas`, sum(`v`.`valor_comissao`) AS `total_comissoes`, avg(`v`.`comissao_percentual`) AS `media_comissao` FROM (`empresas_representadas` `e` left join `vendas` `v` on(`e`.`id` = `v`.`empresa_id` and `v`.`status_venda` = 'concluida')) GROUP BY `e`.`id`, `e`.`nome_empresa` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `agendamentos_entrega`
--
ALTER TABLE `agendamentos_entrega`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `orcamento_id` (`orcamento_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf_cnpj` (`cpf_cnpj`);

--
-- Índices de tabela `empresas_representadas`
--
ALTER TABLE `empresas_representadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `historico_orcamentos`
--
ALTER TABLE `historico_orcamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orcamento_id` (`orcamento_id`),
  ADD KEY `idx_data_alteracao` (`data_alteracao`);

--
-- Índices de tabela `itens_orcamento`
--
ALTER TABLE `itens_orcamento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orcamento_id` (`orcamento_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `itens_venda`
--
ALTER TABLE `itens_venda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `marketplace_carrinho`
--
ALTER TABLE `marketplace_carrinho`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token_carrinho` (`token_acesso`),
  ADD KEY `idx_produto_carrinho` (`produto_id`);

--
-- Índices de tabela `marketplace_configuracoes`
--
ALTER TABLE `marketplace_configuracoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`),
  ADD KEY `idx_chave` (`chave`);

--
-- Índices de tabela `marketplace_itens_pedido`
--
ALTER TABLE `marketplace_itens_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedido_item` (`pedido_id`),
  ADD KEY `idx_produto_item` (`produto_id`);

--
-- Índices de tabela `marketplace_links`
--
ALTER TABLE `marketplace_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_acesso` (`token_acesso`),
  ADD KEY `idx_token` (`token_acesso`),
  ADD KEY `idx_cliente` (`cliente_id`);

--
-- Índices de tabela `marketplace_pedidos`
--
ALTER TABLE `marketplace_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_pedido` (`numero_pedido`),
  ADD KEY `idx_cliente_pedido` (`cliente_id`),
  ADD KEY `idx_token_pedido` (`token_acesso`),
  ADD KEY `idx_numero_pedido` (`numero_pedido`),
  ADD KEY `idx_status` (`status_pedido`),
  ADD KEY `idx_venda` (`venda_id`),
  ADD KEY `idx_transacao` (`transacao_financeira_id`);

--
-- Índices de tabela `metas_vendas`
--
ALTER TABLE `metas_vendas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meta_empresa_mes_ano` (`empresa_id`,`mes`,`ano`);

--
-- Índices de tabela `movimentacoes_financeiras`
--
ALTER TABLE `movimentacoes_financeiras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`),
  ADD KEY `orcamento_id` (`orcamento_id`),
  ADD KEY `idx_movimentacoes_empresa` (`empresa_id`),
  ADD KEY `idx_movimentacoes_data` (`data_movimentacao`);

--
-- Índices de tabela `orcamentos`
--
ALTER TABLE `orcamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `idx_orcamentos_empresa` (`empresa_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_produtos_empresa` (`empresa_id`),
  ADD KEY `idx_ativo_marketplace` (`ativo_marketplace`),
  ADD KEY `idx_destaque` (`destaque_marketplace`),
  ADD KEY `idx_empresa_ativo` (`empresa_id`,`ativo_marketplace`);

--
-- Índices de tabela `transacoes_financeiras`
--
ALTER TABLE `transacoes_financeiras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transacoes_empresa` (`empresa_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `idx_vendas_empresa` (`empresa_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `agendamentos_entrega`
--
ALTER TABLE `agendamentos_entrega`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `empresas_representadas`
--
ALTER TABLE `empresas_representadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_orcamentos`
--
ALTER TABLE `historico_orcamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `itens_orcamento`
--
ALTER TABLE `itens_orcamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `itens_venda`
--
ALTER TABLE `itens_venda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `marketplace_carrinho`
--
ALTER TABLE `marketplace_carrinho`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `marketplace_configuracoes`
--
ALTER TABLE `marketplace_configuracoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `marketplace_itens_pedido`
--
ALTER TABLE `marketplace_itens_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `marketplace_links`
--
ALTER TABLE `marketplace_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `marketplace_pedidos`
--
ALTER TABLE `marketplace_pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `metas_vendas`
--
ALTER TABLE `metas_vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `movimentacoes_financeiras`
--
ALTER TABLE `movimentacoes_financeiras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `orcamentos`
--
ALTER TABLE `orcamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `transacoes_financeiras`
--
ALTER TABLE `transacoes_financeiras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `agendamentos_entrega`
--
ALTER TABLE `agendamentos_entrega`
  ADD CONSTRAINT `agendamentos_entrega_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`),
  ADD CONSTRAINT `agendamentos_entrega_ibfk_2` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`),
  ADD CONSTRAINT `agendamentos_entrega_ibfk_3` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);

--
-- Restrições para tabelas `historico_orcamentos`
--
ALTER TABLE `historico_orcamentos`
  ADD CONSTRAINT `historico_orcamentos_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `itens_orcamento`
--
ALTER TABLE `itens_orcamento`
  ADD CONSTRAINT `itens_orcamento_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`),
  ADD CONSTRAINT `itens_orcamento_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `itens_venda`
--
ALTER TABLE `itens_venda`
  ADD CONSTRAINT `itens_venda_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`),
  ADD CONSTRAINT `itens_venda_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `marketplace_carrinho`
--
ALTER TABLE `marketplace_carrinho`
  ADD CONSTRAINT `marketplace_carrinho_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `marketplace_itens_pedido`
--
ALTER TABLE `marketplace_itens_pedido`
  ADD CONSTRAINT `marketplace_itens_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `marketplace_pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marketplace_itens_pedido_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `marketplace_links`
--
ALTER TABLE `marketplace_links`
  ADD CONSTRAINT `marketplace_links_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `marketplace_pedidos`
--
ALTER TABLE `marketplace_pedidos`
  ADD CONSTRAINT `marketplace_pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marketplace_pedidos_ibfk_2` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `marketplace_pedidos_ibfk_3` FOREIGN KEY (`transacao_financeira_id`) REFERENCES `transacoes_financeiras` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `metas_vendas`
--
ALTER TABLE `metas_vendas`
  ADD CONSTRAINT `metas_vendas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_representadas` (`id`);

--
-- Restrições para tabelas `movimentacoes_financeiras`
--
ALTER TABLE `movimentacoes_financeiras`
  ADD CONSTRAINT `movimentacoes_financeiras_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_representadas` (`id`),
  ADD CONSTRAINT `movimentacoes_financeiras_ibfk_2` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`),
  ADD CONSTRAINT `movimentacoes_financeiras_ibfk_3` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`);

--
-- Restrições para tabelas `orcamentos`
--
ALTER TABLE `orcamentos`
  ADD CONSTRAINT `fk_orcamentos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_representadas` (`id`),
  ADD CONSTRAINT `orcamentos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produtos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_representadas` (`id`);

--
-- Restrições para tabelas `transacoes_financeiras`
--
ALTER TABLE `transacoes_financeiras`
  ADD CONSTRAINT `fk_transacoes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_representadas` (`id`);

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `fk_vendas_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas_representadas` (`id`),
  ADD CONSTRAINT `vendas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
