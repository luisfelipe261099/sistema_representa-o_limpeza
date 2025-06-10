<?php
// Teste completo de envio de orçamento por email
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui PHPMailer e conexão com o banco
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';
require_once 'includes/db_connect.php';

echo "<h1>Teste Completo de Envio de Orçamento</h1>";

// Função copiada do orcamentos.php
function gerarPDFComoString($orcamento_id, $conn) {
    try {
        $sql_orcamento = "SELECT o.*, c.nome AS nome_cliente, c.email FROM orcamentos o JOIN clientes c ON o.cliente_id = c.id WHERE o.id = ?";
        $stmt = $conn->prepare($sql_orcamento);
        $stmt->bind_param("i", $orcamento_id);
        $stmt->execute();
        $orcamento = $stmt->get_result()->fetch_assoc();
        
        if (!$orcamento) throw new Exception("Orçamento não encontrado");

        $sql_itens = "SELECT i.*, p.nome AS nome_produto FROM itens_orcamento i JOIN produtos p ON i.produto_id = p.id WHERE i.orcamento_id = ?";
        $stmt_itens = $conn->prepare($sql_itens);
        $stmt_itens->bind_param("i", $orcamento_id);
        $stmt_itens->execute();
        $itens = $stmt_itens->get_result();

        require_once('vendor/setasign/fpdf/fpdf.php');
        
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',16);
        $pdf->Cell(0,10,'Orcamento N: ' . $orcamento['id'],0,1,'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'Informacoes do Orcamento',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->Cell(40,7,'Cliente:',0);
        $pdf->Cell(0,7,$orcamento['nome_cliente'],0,1);
        $pdf->Cell(40,7,'Data:',0);
        $pdf->Cell(0,7,date('d/m/Y', strtotime($orcamento['data_orcamento'])),0,1);
        $pdf->Cell(40,7,'Status:',0);
        $pdf->Cell(0,7,ucfirst($orcamento['status_orcamento']),0,1);
        $pdf->Ln(10);
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'Itens do Orcamento',0,1);
        $pdf->SetFillColor(230,230,230);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(90,7,'Produto',1,0,'C',true);
        $pdf->Cell(25,7,'Quantidade',1,0,'C',true);
        $pdf->Cell(35,7,'Valor Unit.',1,0,'C',true);
        $pdf->Cell(40,7,'Subtotal',1,1,'C',true);
        $pdf->SetFont('Arial','',10);
        
        while ($item = $itens->fetch_assoc()) {
            $preco = $item['preco_unitario'] ?? 0;
            $subtotal = $item['quantidade'] * $preco;
            $pdf->Cell(90,7,iconv('UTF-8', 'ISO-8859-1//IGNORE', $item['nome_produto']),1,0);
            $pdf->Cell(25,7,$item['quantidade'],1,0,'C');
            $pdf->Cell(35,7,'R$ '.number_format($preco, 2, ',', '.'),1,0,'R');
            $pdf->Cell(40,7,'R$ '.number_format($subtotal, 2, ',', '.'),1,1,'R');
        }
        
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell(150,7,'Valor Total:',1,0,'R');
        $pdf->Cell(40,7,'R$ '.number_format($orcamento['valor_total'], 2, ',', '.'),1,1,'R');
        
        if (!empty($orcamento['observacoes'])) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial','B',12);
            $pdf->Cell(0,7,'Observacoes:',0,1);
            $pdf->SetFont('Arial','',11);
            $pdf->MultiCell(0,7,iconv('UTF-8', 'ISO-8859-1//IGNORE', $orcamento['observacoes']),0);
        }
        
        return $pdf->Output('S');
    } catch (Exception $e) {
        error_log("Erro ao gerar PDF do orçamento #$orcamento_id: " . $e->getMessage());
        throw $e;
    }
}

// Buscar um orçamento para teste
$sql = "SELECT o.id, c.nome, c.email, o.status_orcamento FROM orcamentos o JOIN clientes c ON o.cliente_id = c.id WHERE c.email IS NOT NULL AND c.email != '' ORDER BY o.id DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $orcamento_id = $data['id'];
    
    echo "<h2>Dados do teste:</h2>";
    echo "<ul>";
    echo "<li><strong>Orçamento ID:</strong> " . htmlspecialchars($orcamento_id) . "</li>";
    echo "<li><strong>Cliente:</strong> " . htmlspecialchars($data['nome']) . "</li>";
    echo "<li><strong>Email:</strong> " . htmlspecialchars($data['email']) . "</li>";
    echo "<li><strong>Status:</strong> " . htmlspecialchars($data['status_orcamento']) . "</li>";
    echo "</ul>";
    
    if (!empty($data['email'])) {
        $mail = new PHPMailer(true);
        try {
            echo "<h3>1. Carregando configurações...</h3>";
            // Carrega configurações do SMTP do arquivo de configuração
            $email_config = include 'includes/email_config.php';
            echo "✅ Configurações carregadas<br>";
            
            echo "<h3>2. Configurando SMTP...</h3>";
            // Configurações do servidor SMTP
            $mail->isSMTP();
            $mail->Host = $email_config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $email_config['username'];
            $mail->Password = $email_config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $email_config['port'];
            $mail->CharSet = 'UTF-8';
            echo "✅ SMTP configurado<br>";

            echo "<h3>3. Configurando remetente e destinatário...</h3>";
            // Configurações do remetente e destinatário
            $mail->setFrom($email_config['from_email'], $email_config['from_name']);
            $mail->addAddress($data['email'], $data['nome']);
            echo "✅ Remetente e destinatário configurados<br>";
            
            echo "<h3>4. Gerando PDF...</h3>";
            // Gerar e anexar PDF
            try {
                $pdf_content = gerarPDFComoString($orcamento_id, $conn);
                $mail->addStringAttachment($pdf_content, 'Orcamento_'.$orcamento_id.'.pdf', 'base64', 'application/pdf');
                echo "✅ PDF gerado e anexado (" . strlen($pdf_content) . " bytes)<br>";
            } catch (Exception $pdf_error) {
                echo "❌ Erro ao gerar PDF: " . htmlspecialchars($pdf_error->getMessage()) . "<br>";
                throw new Exception("Erro ao gerar PDF do orçamento: " . $pdf_error->getMessage());
            }

            echo "<h3>5. Configurando conteúdo do email...</h3>";
            // Configurar conteúdo do email
            $mail->isHTML(true);
            $mail->Subject = 'Seu Orçamento Nº ' . $orcamento_id . ' - LFM Tecnologia';
            
            $mail->Body = "
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h2>LFM Tecnologia</h2>
                </div>
                <div class='content'>
                    <p>Olá, <strong>" . htmlspecialchars($data['nome']) . "</strong>!</p>
                    
                    <p>Esperamos que esteja bem!</p>
                    
                    <p>Segue em anexo o orçamento solicitado (Nº <strong>$orcamento_id</strong>).</p>
                    
                    <p>Caso tenha alguma dúvida ou precise de esclarecimentos, não hesite em entrar em contato conosco.</p>
                    
                    <p>Aguardamos seu retorno!</p>
                    
                    <br>
                    <p>Atenciosamente,<br>
                    <strong>Karla Wollinge</strong><br>
                    LFM Tecnologia</p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático. Por favor, não responda diretamente a este e-mail.</p>
                    <p>Para entrar em contato, utilize: desenvolvimento@lfmtecnologia.com</p>
                </div>
            </body>
            </html>";
            
            $mail->AltBody = "Olá, " . htmlspecialchars($data['nome']) . "!\n\n" .
                           "Segue em anexo o orçamento solicitado (Nº $orcamento_id).\n\n" .
                           "Caso tenha alguma dúvida, entre em contato conosco.\n\n" .
                           "Atenciosamente,\nKarla Wollinge\nLFM Tecnologia";
            
            echo "✅ Conteúdo do email configurado<br>";
            
            echo "<h3>6. Enviando email...</h3>";
            // Enviar email
            if ($mail->send()) {
                echo "<div style='color: green; font-weight: bold; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 15px 0;'>";
                echo "🎉 EMAIL ENVIADO COM SUCESSO!";
                echo "<br>Destinatário: " . htmlspecialchars($data['email']);
                echo "<br>Orçamento: #" . htmlspecialchars($orcamento_id);
                echo "</div>";
            } else {
                throw new Exception("Falha no envio do email");
            }
            
        } catch (Exception $e) {
            echo "<div style='color: red; font-weight: bold; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 15px 0;'>";
            echo "❌ ERRO NO ENVIO: " . htmlspecialchars($e->getMessage());
            echo "</div>";
            
            echo "<h3>Detalhes do erro:</h3>";
            echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
    } else {
        echo "<div style='color: orange; font-weight: bold; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; margin: 15px 0;'>";
        echo "⚠️ Cliente não possui email cadastrado";
        echo "</div>";
    }
    
} else {
    echo "<div style='color: orange; font-weight: bold; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; margin: 15px 0;'>";
    echo "⚠️ Nenhum orçamento com cliente que tenha email encontrado no banco de dados";
    echo "</div>";
}

$conn->close();

echo "<br><br>";
echo "<a href='orcamentos.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voltar para Orçamentos</a>";
echo " ";
echo "<a href='teste_email_simples.php' style='display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Teste Email Simples</a>";
echo " ";
echo "<a href='teste_pdf.php' style='display: inline-block; background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Teste PDF</a>";
?>
