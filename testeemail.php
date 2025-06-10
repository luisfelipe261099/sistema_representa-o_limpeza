<?php
// Ativar exibição de todos os erros para depuração completa
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir bibliotecas PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Carregar o autoloader do Composer
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// --- Variáveis de Controle ---
$message = '';
$message_type = '';
$smtp_debug_output = '';
$config_loaded = false;
$phpmailer_loaded = class_exists('PHPMailer\PHPMailer\PHPMailer');

// Carregar configurações de e-mail, se o arquivo existir
if (file_exists('includes/email_config.php')) {
    $email_config = include 'includes/email_config.php';
    $config_loaded = true;
} else {
    $email_config = [];
}

// --- Processamento do Formulário ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$phpmailer_loaded) {
        $message = "PHPMailer não está instalado. Execute o composer ou o script de instalação.";
        $message_type = 'danger';
    } elseif (!$config_loaded) {
        $message = "Arquivo de configuração de e-mail (includes/email_config.php) não encontrado.";
        $message_type = 'danger';
    } else {
        // Capturar dados do formulário
        $to_email = $_POST['to_email'];
        $to_name = $_POST['to_name'];
        $subject = $_POST['subject'];
        $body = $_POST['body'];

        $mail = new PHPMailer(true);

        try {
            // Configurações do servidor
            $mail->isSMTP();
            $mail->Host       = $email_config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $email_config['username'];
            $mail->Password   = $email_config['password'];
            $mail->SMTPSecure = $email_config['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $email_config['port'];
            $mail->CharSet    = 'UTF-8';

            // ===================================================================
            // CORREÇÃO APLICADA: Captura de log de forma robusta
            // ===================================================================
            $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) use (&$smtp_debug_output) {
                // Adiciona a string de debug à nossa variável, formatando-a para HTML
                $smtp_debug_output .= htmlspecialchars($str) . "<br>\n";
            };
            // ===================================================================

            // Remetente e Destinatário
            $mail->setFrom($email_config['from_email'], $email_config['from_name']);
            $mail->addAddress($to_email, $to_name);

            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br($body);
            $mail->AltBody = strip_tags($body);

            // Enviar
            $mail->send();
            $message = 'Tentativa de envio concluída! Verifique o log de depuração abaixo para confirmar o status.';
            $message_type = 'success';

        } catch (Exception $e) {
            $message = "O envio falhou. Erro do PHPMailer: {$mail->ErrorInfo}";
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico e Teste de E-mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Poppins', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .debug-output {
            background-color: #212529;
            color: #c7c7c7;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.85rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 400px;
            overflow-y: auto;
        }
        .check-icon { color: #198754; }
        .cross-icon { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="fas fa-cogs me-2"></i> Diagnóstico e Teste de E-mail</h4>
                    </div>
                    <div class="card-body p-4">

                        <div class="mb-4">
                            <h5><i class="fas fa-stethoscope me-2 text-primary"></i>Verificação do Sistema</h5>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Versão do PHP
                                    <span class="badge bg-light text-dark"><?php echo PHP_VERSION; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Biblioteca PHPMailer
                                    <?php echo $phpmailer_loaded ? '<i class="fas fa-check-circle check-icon"></i>' : '<i class="fas fa-times-circle cross-icon"></i>'; ?>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Arquivo de Configuração (email_config.php)
                                    <?php echo $config_loaded ? '<i class="fas fa-check-circle check-icon"></i>' : '<i class="fas fa-times-circle cross-icon"></i>'; ?>
                                </li>
                                <?php if($config_loaded): ?>
                                <li class="list-group-item">
                                    <strong>Configurações Atuais:</strong><br>
                                    <small class="text-muted">
                                        Host: <code><?php echo htmlspecialchars($email_config['host']); ?></code> |
                                        Porta: <code><?php echo htmlspecialchars($email_config['port']); ?></code> |
                                        Usuário: <code><?php echo htmlspecialchars($email_config['username']); ?></code>
                                    </small>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <hr>
                        <div class="mb-4">
                            <h5><i class="fas fa-paper-plane me-2 text-primary"></i>Teste Manual de Envio</h5>
                            <form action="testeemail.php" method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="to_email" class="form-label">E-mail do Destinatário:</label>
                                        <input type="email" class="form-control" id="to_email" name="to_email" required value="luisfelipedasilvamachadoo@gmail.com">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="to_name" class="form-label">Nome do Destinatário:</label>
                                        <input type="text" class="form-control" id="to_name" name="to_name" value="Cliente Teste">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Assunto:</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required value="E-mail de Teste do Sistema - <?php echo date('d/m/Y H:i:s'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="body" class="form-label">Mensagem:</label>
                                    <textarea class="form-control" id="body" name="body" rows="4" required>Este é um e-mail de teste para verificar a funcionalidade de envio.</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2" <?php echo !$phpmailer_loaded || !$config_loaded ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane me-2"></i> Enviar E-mail de Teste
                                </button>
                            </form>
                        </div>

                        <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
                        <hr>
                        <div>
                            <h5><i class="fas fa-poll me-2 text-primary"></i>Resultados do Envio</h5>
                            <?php if (!empty($message)): ?>
                                <div class="alert alert-<?php echo $message_type; ?>">
                                    <?php echo $message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($smtp_debug_output)): ?>
                                <h6 class="text-muted mt-3">Log de Depuração SMTP:</h6>
                                <div class="debug-output">
                                    <?php echo $smtp_debug_output; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>