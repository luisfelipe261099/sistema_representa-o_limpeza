# Correções para PHP 8.2+ Compatibilidade

## Resumo

Foram feitas várias correções para resolver os problemas de:
- Funções depreciadas (utf8_decode)
- Índices de array indefinidos (valor_unitario vs preco_unitario)
- Headers already sent

## Arquivos criados

1. **includes/PDFHelper.php** - Classe auxiliar para geração de PDF que:
   - Substitui utf8_decode() com alternativas modernas
   - Adiciona métodos para facilitar a manipulação de dados de orçamentos
   - Inclui utilitários para bufferização de saída HTTP

2. **teste_pdf_sem_utf8_decode.php** - Exemplo de como gerar PDFs sem usar utf8_decode()

3. **CORRECOES.md** - Documentação detalhada das mudanças

## Correções já aplicadas

1. **orcamentos.php**:
   - Adicionada bufferização de saída (ob_start/ob_end_clean/ob_end_flush)
   - Substituídas todas as chamadas de utf8_decode() por strings diretas
   - Corrigido o uso de valor_unitario para preco_unitario com verificação de ambos
   - Corrigido o erro "headers already sent"

2. **gerar_pdf_orcamento.php** (parcialmente):
   - Adicionada bufferização de saída
   - Incluída a nova classe PDFHelper
   - Substituídas as primeiras chamadas de utf8_decode() no começo do arquivo

## O que falta

### Em gerar_pdf_orcamento.php:
- Substituir todas as chamadas restantes de utf8_decode() por PDFHelper::addCell()
- Exemplo:
  ```php
  // Substituir isto:
  $pdf->Cell(0, 6, utf8_decode('Data de Emissão: ' . date('d/m/Y')), 0, 1, 'C');
  
  // Por isto:
  PDFHelper::addCell($pdf, 0, 6, 'Data de Emissão: ' . date('d/m/Y'), 0, 1, 'C');
  ```

### Em outras partes do sistema:
- Verificar e substituir outras ocorrências de utf8_decode() em outros arquivos
- Garantir que todos os campos de banco de dados sejam acessados corretamente
- Adicionar validações para evitar "undefined array key" em outros arquivos

## Como aplicar as correções restantes

1. Buscar todas as ocorrências de utf8_decode() no sistema:
   ```
   grep -r "utf8_decode" --include="*.php" .
   ```

2. Substituir cada ocorrência seguindo os padrões definidos na classe PDFHelper

3. Testar cada página modificada para garantir que não haja mais avisos ou erros

## Importante

A função utf8_decode() está oficialmente depreciada no PHP 8.2 e será removida em versões futuras. É essencial fazer estas correções para garantir a compatibilidade com versões mais recentes do PHP.
