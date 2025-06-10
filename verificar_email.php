<?php
// Verificação rápida de email
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

echo "<h1>🔍 Verificação de Email - LFM Tecnologia</h1>";

try {
    echo "<h2>1. Testando conexão SMTP...</h2>";
    
    $mail = new PHPMailer(true);
    
    // Configurações exatas do Hostinger
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'desenvolvimento@lfmtecnologia.com';
    $mail->Password = 'T3cn0l0g1a@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    
    echo "✅ Configurações SMTP definidas<br>";
    
    // Teste de conexão simples
    echo "<h2>2. Testando autenticação...</h2>";
    
    $mail->setFrom('desenvolvimento@lfmtecnologia.com', 'LFM Tecnologia - Teste');
    $mail->addAddress('desenvolvimento@lfmtecnologia.com', 'Teste Interno');
    
    $mail->isHTML(true);
    $mail->Subject = 'Teste de Configuração - ' . date('d/m/Y H:i:s');
    $mail->Body = '<h2>Teste de Email</h2><p>Este é um teste de configuração do sistema de email.</p><p>Data/Hora: ' . date('d/m/Y H:i:s') . '</p>';
    $mail->AltBody = 'Teste de Email - Data/Hora: ' . date('d/m/Y H:i:s');
    
    echo "✅ Email configurado<br>";
    
    echo "<h2>3. Enviando email de teste...</h2>";
    
    if ($mail->send()) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>🎉 SUCESSO!</h3>";
        echo "<p>Email enviado com sucesso!</p>";
        echo "<p><strong>Configurações funcionando corretamente:</strong></p>";
        echo "<ul>";
        echo "<li>Host: smtp.hostinger.com</li>";
        echo "<li>Porta: 465 (SSL)</li>";
        echo "<li>Usuário: desenvolvimento@lfmtecnologia.com</li>";
        echo "<li>Autenticação: OK</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<h2>✅ Sistema de email está funcionando!</h2>";
        echo "<p>Agora você pode usar o botão de enviar email nos orçamentos.</p>";
        
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>❌ FALHA NO ENVIO</h3>";
        echo "<p>O email não foi enviado, mas não houve exceção.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>❌ ERRO</h3>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Diagnósticos específicos
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "<p><strong>Possível causa:</strong> Firewall bloqueando a porta 465</p>";
        echo "<p><strong>Solução:</strong> Verifique se a porta 465 está aberta no servidor</p>";
    } elseif (strpos($e->getMessage(), 'Authentication failed') !== false) {
        echo "<p><strong>Possível causa:</strong> Credenciais incorretas</p>";
        echo "<p><strong>Solução:</strong> Verifique o email e senha</p>";
    } elseif (strpos($e->getMessage(), 'Could not connect to SMTP host') !== false) {
        echo "<p><strong>Possível causa:</strong> Problema de conectividade</p>";
        echo "<p><strong>Solução:</strong> Verifique a conexão com a internet</p>";
    }
    
    echo "</div>";
    
    echo "<h3>Tentando com porta 587 (TLS)...</h3>";
    
    try {
        $mail2 = new PHPMailer(true);
        $mail2->isSMTP();
        $mail2->Host = 'smtp.hostinger.com';
        $mail2->SMTPAuth = true;
        $mail2->Username = 'desenvolvimento@lfmtecnologia.com';
        $mail2->Password = 'T3cn0l0g1a@';
        $mail2->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail2->Port = 587;
        $mail2->CharSet = 'UTF-8';
        
        $mail2->setFrom('desenvolvimento@lfmtecnologia.com', 'LFM Tecnologia - Teste TLS');
        $mail2->addAddress('desenvolvimento@lfmtecnologia.com', 'Teste TLS');
        
        $mail2->isHTML(true);
        $mail2->Subject = 'Teste TLS - ' . date('d/m/Y H:i:s');
        $mail2->Body = '<h2>Teste com TLS</h2><p>Teste usando porta 587 com TLS.</p>';
        
        if ($mail2->send()) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🎉 SUCESSO COM TLS!</h3>";
            echo "<p>Email enviado com sucesso usando porta 587 (TLS)!</p>";
            echo "<p><strong>Use estas configurações:</strong></p>";
            echo "<ul>";
            echo "<li>Porta: 587</li>";
            echo "<li>Encryption: STARTTLS</li>";
            echo "</ul>";
            echo "</div>";
        }
        
    } catch (Exception $e2) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>❌ ERRO TAMBÉM COM TLS</h3>";
        echo "<p>" . htmlspecialchars($e2->getMessage()) . "</p>";
        echo "</div>";
    }
}

echo "<br><br>";
echo "<a href='orcamentos.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voltar para Orçamentos</a>";
?>
