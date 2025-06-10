# Guia para Testar Geração de PDFs de Orçamentos

## Testes de Geração de PDF

1. Acesse o script de teste especial: `teste_pdf_orcamento.php`
   Este script permite testar a geração de PDF independentemente do restante do sistema.

2. Você pode fornecer o ID de um orçamento específico através do parâmetro URL:
   `teste_pdf_orcamento.php?id=123`

3. O script exibirá informações sobre o orçamento e permitirá testar a geração do PDF.

## Resolução de Problemas

Se ocorrerem erros na geração do PDF, verifique:

1. **Dados do Orçamento**:
   - Certifique-se de que o orçamento existe no banco de dados
   - Verifique se o orçamento possui todos os campos obrigatórios preenchidos

2. **Itens do Orçamento**:
   - Certifique-se de que existem itens associados ao orçamento
   - Verifique se cada item possui os campos necessários: `quantidade` e `preco_unitario`

3. **Biblioteca PDF**:
   - Confirme que a biblioteca FPDF está corretamente instalada em `vendor/setasign/fpdf/`

4. **Log de Erros**:
   - Verifique o log de erros do PHP para mensagens detalhadas sobre falhas

## Correções Implementadas

O script de geração de PDF foi atualizado com as seguintes melhorias:

1. **Tratamento robusto de erros**: Captura e exibe mensagens de erro detalhadas
2. **Validação de dados**: Verifica se todos os campos necessários existem antes de tentar usá-los
3. **Valores padrão**: Fornece valores padrão para campos ausentes
4. **Verificação de estrutura**: Verifica se a tabela `itens_orcamento` tem a estrutura esperada
5. **Fallback de renderização**: Tenta métodos alternativos de geração quando um método falha

## Arquivos Relevantes

- `gerar_pdf_orcamento.php`: Script principal de geração de PDF
- `teste_pdf_orcamento.php`: Script de teste para isolar problemas
- `vendor/setasign/fpdf/fpdf.php`: Biblioteca PDF utilizada

## Como Usar

1. Para gerar um PDF de orçamento, acesse:
   `gerar_pdf_orcamento.php?id=123` (substitua 123 pelo ID do orçamento)

2. Para testar um orçamento específico, acesse:
   `teste_pdf_orcamento.php?id=123` (substitua 123 pelo ID do orçamento)

## Contato para Suporte

Se os problemas persistirem, entre em contato com o suporte técnico fornecendo:

1. ID do orçamento que está causando o problema
2. Mensagens de erro exibidas
3. Captura de tela da página de erro
