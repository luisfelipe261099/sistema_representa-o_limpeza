<?php
// Script para verificar e instalar o PHPMailer

echo "<h1>Verificação e Instalação do PHPMailer</h1>";

// Verificar se o Composer está instalado
echo "<h2>1. Verificando o Composer...</h2>";
$composer_exists = false;

// Verificar se o arquivo composer.phar existe no diretório atual
if (file_exists('composer.phar')) {
    echo "<p>✅ Composer encontrado (composer.phar).</p>";
    $composer_command = 'php composer.phar';
    $composer_exists = true;
} else {
    // Tentar executar o comando composer
    exec('composer --version 2>&1', $output, $return_var);
    if ($return_var === 0) {
        echo "<p>✅ Composer encontrado (instalação global).</p>";
        $composer_command = 'composer';
        $composer_exists = true;
    } else {
        echo "<p>❌ Composer não encontrado. Baixando...</p>";
        
        // Tentar baixar o Composer
        $composer_setup = file_get_contents('https://getcomposer.org/installer');
        if ($composer_setup === false) {
            echo "<p>❌ Não foi possível baixar o instalador do Composer. Verifique sua conexão com a internet.</p>";
            echo "<p>Instale o Composer manualmente: <a href='https://getcomposer.org/download/' target='_blank'>https://getcomposer.org/download/</a></p>";
        } else {
            file_put_contents('composer-setup.php', $composer_setup);
            echo "<p>Executando instalador do Composer...</p>";
            exec('php composer-setup.php 2>&1', $output, $return_var);
            if ($return_var === 0) {
                echo "<p>✅ Composer instalado com sucesso.</p>";
                $composer_command = 'php composer.phar';
                $composer_exists = true;
                @unlink('composer-setup.php');
            } else {
                echo "<p>❌ Falha ao instalar o Composer: " . implode("<br>", $output) . "</p>";
                echo "<p>Instale o Composer manualmente: <a href='https://getcomposer.org/download/' target='_blank'>https://getcomposer.org/download/</a></p>";
            }
        }
    }
}

if ($composer_exists) {
    // Verificar composer.json
    echo "<h2>2. Verificando composer.json...</h2>";
    if (file_exists('composer.json')) {
        echo "<p>✅ Arquivo composer.json encontrado.</p>";
        
        // Verificar se PHPMailer está listado como dependência
        $composer_json = json_decode(file_get_contents('composer.json'), true);
        if (isset($composer_json['require']['phpmailer/phpmailer'])) {
            echo "<p>✅ PHPMailer está listado como dependência no composer.json.</p>";
        } else {
            echo "<p>❌ PHPMailer não está listado como dependência. Adicionando...</p>";
            
            if (!isset($composer_json['require'])) {
                $composer_json['require'] = [];
            }
            $composer_json['require']['phpmailer/phpmailer'] = "^6.8";
            file_put_contents('composer.json', json_encode($composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            echo "<p>✅ PHPMailer adicionado ao composer.json.</p>";
        }
    } else {
        echo "<p>❌ Arquivo composer.json não encontrado. Criando um novo...</p>";
        
        $composer_json = [
            'require' => [
                'phpmailer/phpmailer' => '^6.8'
            ]
        ];
        file_put_contents('composer.json', json_encode($composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        echo "<p>✅ Arquivo composer.json criado com PHPMailer como dependência.</p>";
    }
    
    // Instalar dependências
    echo "<h2>3. Instalando dependências...</h2>";
    echo "<p>Executando: {$composer_command} install</p>";
    echo "<pre>";
    passthru("{$composer_command} install 2>&1", $return_var);
    echo "</pre>";
    
    if ($return_var === 0) {
        echo "<p>✅ Dependências instaladas com sucesso.</p>";
    } else {
        echo "<p>❌ Erro ao instalar dependências.</p>";
    }
    
    // Verificar se PHPMailer foi instalado corretamente
    echo "<h2>4. Verificando instalação do PHPMailer...</h2>";
    if (file_exists('vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        echo "<p>✅ PHPMailer encontrado em vendor/phpmailer/phpmailer/src/PHPMailer.php.</p>";
        
        // Testar se a classe pode ser carregada
        require_once 'vendor/autoload.php';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "<p>✅ Classe PHPMailer carregada com sucesso!</p>";
            echo "<h2>✅ PHPMailer está instalado e funcionando corretamente!</h2>";
            
            echo "<div style='margin-top: 20px; padding: 15px; background-color: #d4edda; border-radius: 5px; color: #155724;'>";
            echo "<h3>Verificação de SMTP</h3>";
            
            // Tentar carregar as configurações de e-mail
            if (file_exists('includes/email_config.php')) {
                echo "<p>✅ Arquivo de configuração do SMTP encontrado.</p>";
                
                // Testar a criação de uma instância do PHPMailer
                try {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $email_config = include 'includes/email_config.php';
                    
                    if (!empty($email_config['host']) && !empty($email_config['username']) && !empty($email_config['password'])) {
                        echo "<p>✅ Configurações do SMTP encontradas:</p>";
                        echo "<ul>";
                        echo "<li>Host: " . htmlspecialchars($email_config['host']) . "</li>";
                        echo "<li>Username: " . htmlspecialchars($email_config['username']) . "</li>";
                        echo "<li>Port: " . htmlspecialchars($email_config['port']) . "</li>";
                        echo "</ul>";
                    } else {
                        echo "<p>⚠️ Algumas configurações do SMTP estão faltando. Verifique includes/email_config.php.</p>";
                    }
                    
                    echo "<p>⚠️ <strong>Nota:</strong> O teste de conexão SMTP não foi realizado para evitar tentativas incorretas de login. Teste enviando um e-mail real.</p>";
                } catch (Exception $e) {
                    echo "<p>❌ Erro ao instanciar PHPMailer: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            } else {
                echo "<p>❌ Arquivo de configuração do SMTP não encontrado. Crie o arquivo includes/email_config.php.</p>";
            }
            echo "</div>";
            
            echo "<div style='margin-top: 20px; text-align: center;'>";
            echo "<a href='orcamentos.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voltar para Orçamentos</a>";
            echo "</div>";
        } else {
            echo "<p>❌ Classe PHPMailer não pôde ser carregada. Verifique o autoloader.</p>"; 
        }
    } else {
        echo "<p>❌ PHPMailer não foi encontrado em vendor/phpmailer/phpmailer/src/PHPMailer.php.</p>";
        
        // Tentar instalar especificamente o PHPMailer
        echo "<p>Tentando instalar especificamente o PHPMailer...</p>";
        echo "<pre>";
        passthru("{$composer_command} require phpmailer/phpmailer 2>&1", $return_var);
        echo "</pre>";
        
        if ($return_var === 0) {
            echo "<p>✅ PHPMailer instalado com sucesso.</p>";
            
            // Verificar novamente
            if (file_exists('vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
                echo "<p>✅ PHPMailer encontrado em vendor/phpmailer/phpmailer/src/PHPMailer.php.</p>";
                
                // Testar se a classe pode ser carregada
                require_once 'vendor/autoload.php';
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    echo "<p>✅ Classe PHPMailer carregada com sucesso!</p>";
                    echo "<h2>✅ PHPMailer está instalado e funcionando corretamente!</h2>";
                } else {
                    echo "<p>❌ Classe PHPMailer não pôde ser carregada. Verifique o autoloader.</p>";
                }
            } else {
                echo "<p>❌ PHPMailer ainda não foi encontrado. Pode haver um problema com o Composer ou com as permissões.</p>";
            }
        } else {
            echo "<p>❌ Erro ao instalar o PHPMailer.</p>";
        }
    }
} else {
    echo "<h2>❌ Composer não encontrado ou não pôde ser instalado. Instalação manual necessária.</h2>";
    echo "<p>Para instalar o PHPMailer manualmente:</p>";
    echo "<ol>";
    echo "<li>Baixe o PHPMailer em <a href='https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip'>https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip</a></li>";
    echo "<li>Extraia o conteúdo do arquivo zip</li>";
    echo "<li>Crie um diretório <code>vendor/phpmailer/phpmailer/</code> se não existir</li>";
    echo "<li>Copie a pasta <code>src/</code> do arquivo extraído para <code>vendor/phpmailer/phpmailer/</code></li>";
    echo "<li>Certifique-se de que o caminho <code>vendor/phpmailer/phpmailer/src/PHPMailer.php</code> existe</li>";
    echo "</ol>";
}
?>

<div style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 5px;">    <h3>Instruções para o Servidor de Produção</h3>
    <p>Se você estiver no servidor de produção, execute os seguintes comandos:</p>
    <ol>
        <li><code>cd /home/u182607388/domains/wkprodutosdelimpeza.com.br/public_html/2/system</code></li>
        <li><code>composer require phpmailer/phpmailer</code></li>
    </ol>
    <p>Se o Composer não estiver instalado no servidor, você pode instalá-lo com:</p>
    <pre>curl -sS https://getcomposer.org/installer | php
php composer.phar install</pre>
    <p>Ou entre em contato com seu provedor de hospedagem para instalar o Composer ou o PHPMailer.</p>
    
    <h3>Verificação do Status da Instalação</h3>
    <p>Baseado na verificação realizada:</p>
    <?php
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo '<div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-top: 15px;">';
        echo '<h4 style="margin-top: 0;">✅ PHPMailer está instalado corretamente!</h4>';
        echo '<p>Você pode agora enviar e-mails do sistema usando o PHPMailer.</p>';
        echo '<p><a href="orcamentos.php" style="color: #155724; font-weight: bold;">Voltar para orçamentos</a></p>';
        echo '</div>';
    } else {
        echo '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-top: 15px;">';
        echo '<h4 style="margin-top: 0;">❌ PHPMailer ainda não está instalado corretamente</h4>';
        echo '<p>Siga as instruções acima ou entre em contato com o suporte técnico.</p>';
        echo '</div>';
    }
    ?>
</div>
