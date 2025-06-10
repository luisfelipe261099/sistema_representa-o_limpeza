# Instruções de Implantação no Servidor

Este documento contém instruções para implantar o sistema no servidor e resolver possíveis problemas de dependências.

## Requisitos do Servidor

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Extensões PHP: mysqli, gd, mbstring, json, curl
- Composer (recomendado)

## Etapas de Implantação

1. Faça upload de todos os arquivos para o diretório desejado no servidor.
2. Configure o banco de dados usando o arquivo SQL incluído.
3. Configure as informações de conexão do banco de dados em `includes/db_connect.php`.
4. Configure as informações do servidor SMTP em `includes/email_config.php`.
5. Execute o Composer no servidor para instalar dependências.

## Instalando Dependências com Composer

Se você tiver acesso ao SSH no servidor, execute:

```bash
cd /caminho/para/seu/site
composer install
```

Se não tiver acesso ao SSH, acesse `https://seu-site.com/instalar_phpmailer.php` para instalar o PHPMailer.

## Resolução de Problemas

### Erro: "Class PHPMailer not found"

Este erro ocorre quando o PHPMailer não está instalado corretamente. Para resolver:

1. Acesse `https://seu-site.com/instalar_phpmailer.php` em seu navegador
2. Siga as instruções na página para instalar o PHPMailer

Ou, se tiver acesso SSH:

```bash
cd /caminho/para/seu/site
composer require phpmailer/phpmailer
```

### Erro de Conexão SMTP

Se você estiver tendo problemas com o envio de e-mails:

1. Verifique as configurações em `includes/email_config.php`
2. Confirme com seu provedor de hospedagem se as portas SMTP (587 ou 465) estão abertas
3. Verifique se o servidor permite o uso da função `mail()` do PHP

## Informações de Contato

Para assistência técnica, entre em contato:
- Email: suporte@seudominio.com
- Telefone: (XX) XXXX-XXXX
