<?php
// Inicia a sessão para podermos usar variáveis de sessão para as mensagens
session_start();

// Inclui o autoload do Composer para carregar o PHPMailer
require 'vendor/autoload.php';

// Usa as classes do PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Bloco que processa o envio do formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. VALIDAÇÃO DOS DADOS DO FORMULÁRIO
    // Verifica se os campos não estão vazios
    if (empty($_POST['destinatario']) || empty($_POST['assunto']) || empty($_POST['mensagem'])) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Todos os campos são obrigatórios.'];
        header('Location: teste_email_simples.php');
        exit;
    }

    // Limpa e valida o e-mail do destinatário
    $destinatario = filter_var($_POST['destinatario'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'O e-mail do destinatário parece ser inválido.'];
        header('Location: teste_email_simples.php');
        exit;
    }

    // Limpa os outros campos para segurança
    $assunto = htmlspecialchars($_POST['assunto']);
    $mensagem = htmlspecialchars($_POST['mensagem']);

    // 2. LÓGICA DE ENVIO DE E-MAIL
    $mail = new PHPMailer(true);

    try {
        // Configurações do servidor SMTP (conforme você forneceu)
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'faciencia@lfmtecnologia.com'; // Seu e-mail de envio
        $mail->Password   = 'Faciencai@2025#$T3cn0l0g1a@'; // Sua senha
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Remetente (quem está enviando)
        // O e-mail deve ser o mesmo do Username
        $mail->setFrom('faciencia@lfmtecnologia.com', 'Sistema de Envio'); 

        // Destinatário (quem vai receber)
        // Pego do formulário
        $mail->addAddress($destinatario);

        // Conteúdo do E-mail
        $mail->isHTML(true); // Define o formato do e-mail para HTML
        $mail->Subject = $assunto; // Assunto do e-mail
        
        // Corpo do e-mail em HTML (nl2br converte quebras de linha em <br>)
        $mail->Body = nl2br($mensagem); 
        
        // Corpo alternativo em texto puro para clientes de e-mail que не suportam HTML
        $mail->AltBody = $mensagem;

        $mail->send();
        
        // Define a mensagem de sucesso na sessão
        $_SESSION['message'] = ['type' => 'success', 'text' => 'E-mail enviado com sucesso!'];

    } catch (Exception $e) {
        // Em caso de erro, define a mensagem de erro na sessão
        // A mensagem de erro do PHPMailer é útil para depuração
        $_SESSION['message'] = ['type' => 'error', 'text' => "O e-mail não pôde ser enviado. Erro: {$mail->ErrorInfo}"];
    }
    
    // Redireciona de volta para a mesma página para limpar o formulário
    header('Location: teste_email_simples.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar E-mail</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 600px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input[type="email"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; /* Importante para o padding não afetar a largura */
            font-size: 16px;
        }
        textarea {
            resize: vertical;
            min-height: 150px;
        }
        button {
            width: 100%;
            padding: 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ferramenta de Envio de E-mail</h1>

        <?php
        // Exibe a mensagem de sucesso ou erro, se existir na sessão
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            echo "<div class='message {$message['type']}'>{$message['text']}</div>";
            // Limpa a mensagem da sessão para que não apareça novamente
            unset($_SESSION['message']);
        }
        ?>

        <form action="teste_email_simples.php" method="post">
            <div class="form-group">
                <label for="destinatario">Enviar Para (Destinatário):</label>
                <input type="email" id="destinatario" name="destinatario" required>
            </div>
            <div class="form-group">
                <label for="assunto">Assunto:</label>
                <input type="text" id="assunto" name="assunto" required>
            </div>
            <div class="form-group">
                <label for="mensagem">Mensagem:</label>
                <textarea id="mensagem" name="mensagem" required></textarea>
            </div>
            <button type="submit">Enviar E-mail</button>
        </form>
    </div>
</body>
</html>