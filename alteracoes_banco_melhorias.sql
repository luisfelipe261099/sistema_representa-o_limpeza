-- =====================================================
-- ALTERAÇÕES NO BANCO DE DADOS PARA MELHORIAS
-- Execute este SQL manualmente no seu banco de dados
-- =====================================================

-- 1. ADICIONAR CAMPOS DE FORMA DE PAGAMENTO NA TABELA ORCAMENTOS
ALTER TABLE `orcamentos` 
ADD COLUMN `forma_pagamento` ENUM('pix', 'debito', 'credito', 'dinheiro', 'faturamento') DEFAULT 'faturamento' AFTER `status_orcamento`,
ADD COLUMN `tipo_faturamento` ENUM('avista', '15_dias', '20_dias', '30_dias', '45_dias', '60_dias', '90_dias') DEFAULT 'avista' AFTER `forma_pagamento`,
ADD COLUMN `data_vencimento` DATE NULL AFTER `tipo_faturamento`;

-- 2. ADICIONAR CAMPO DE IMAGEM NA TABELA PRODUTOS (se não existir)
-- Verificar se já existe antes de executar
ALTER TABLE `produtos` 
ADD COLUMN `imagem_produto` VARCHAR(255) NULL AFTER `imagem_url`;

-- 3. CRIAR TABELA PARA CONTROLAR ACESSO DE CLIENTES ÀS EMPRESAS NO MARKETPLACE
CREATE TABLE IF NOT EXISTS `marketplace_cliente_empresas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL,
  `empresa_id` INT(11) NOT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `data_criacao` DATETIME DEFAULT CURRENT_TIMESTAMP(),
  `data_atualizacao` DATETIME DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cliente_empresa` (`cliente_id`, `empresa_id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas_representadas` (`id`) ON DELETE CASCADE,
  INDEX `idx_cliente_empresas` (`cliente_id`),
  INDEX `idx_empresa_clientes` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ATUALIZAR TABELA VENDAS PARA INCLUIR NOVOS TIPOS DE PAGAMENTO (se necessário)
ALTER TABLE `vendas` 
MODIFY COLUMN `forma_pagamento` ENUM('pix', 'debito', 'credito', 'dinheiro', 'faturamento', 'transferencia', 'boleto') NOT NULL;

-- 5. CRIAR ÍNDICES PARA MELHOR PERFORMANCE
CREATE INDEX `idx_orcamentos_forma_pagamento` ON `orcamentos` (`forma_pagamento`);
CREATE INDEX `idx_orcamentos_tipo_faturamento` ON `orcamentos` (`tipo_faturamento`);
CREATE INDEX `idx_orcamentos_data_vencimento` ON `orcamentos` (`data_vencimento`);
CREATE INDEX `idx_produtos_imagem` ON `produtos` (`imagem_produto`);

-- 6. INSERIR CONFIGURAÇÕES PADRÃO PARA O MARKETPLACE (se não existirem)
INSERT IGNORE INTO `marketplace_configuracoes` (`chave`, `valor`, `descricao`) VALUES
('permitir_selecao_empresas', '1', 'Permitir que administradores selecionem quais empresas cada cliente pode ver'),
('exibir_todas_empresas_padrao', '1', 'Por padrão, novos clientes podem ver todas as empresas (0 = não, 1 = sim)');

-- 7. CRIAR DIRETÓRIO PARA UPLOAD DE IMAGENS (será criado via PHP)
-- O diretório 'uploads/produtos/' será criado automaticamente pelo sistema

-- =====================================================
-- VERIFICAÇÕES APÓS EXECUÇÃO
-- =====================================================

-- Verificar se as colunas foram adicionadas corretamente:
-- DESCRIBE orcamentos;
-- DESCRIBE produtos;
-- SHOW TABLES LIKE 'marketplace_cliente_empresas';

-- Verificar configurações do marketplace:
-- SELECT * FROM marketplace_configuracoes WHERE chave IN ('permitir_selecao_empresas', 'exibir_todas_empresas_padrao');

-- =====================================================
-- OBSERVAÇÕES IMPORTANTES
-- =====================================================

-- 1. Faça backup do banco antes de executar
-- 2. Execute as alterações em ambiente de teste primeiro
-- 3. Alguns comandos podem falhar se as colunas já existirem (isso é normal)
-- 4. Verifique se todas as foreign keys foram criadas corretamente
-- 5. O sistema criará automaticamente o diretório de uploads quando necessário
