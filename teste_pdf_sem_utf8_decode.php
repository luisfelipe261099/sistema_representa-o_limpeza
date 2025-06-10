<?php
// Inicia o output buffering para evitar problemas de "headers already sent"
ob_start();
session_start();

// ===================================================================================
// TEST FILE FOR FPDF WITHOUT UTF8_DECODE
// ===================================================================================

// Verificar se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Limpa qualquer saída anterior para evitar "headers already sent"
    if (ob_get_length()) ob_end_clean();
    
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';
require_once 'vendor/setasign/fpdf/fpdf.php';

// Função para converter UTF-8 para ISO-8859-1 (Latin1) sem usar utf8_decode
function utf8_to_latin1($str) {
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

// Verificar se foi passado o ID do orçamento
$orcamento_id = $_GET['id'] ?? 0;

if (!$orcamento_id) {
    die('ID do orçamento não fornecido.');
}

try {
    // Buscar dados do orçamento
    $sql_orcamento = "SELECT o.*, c.nome as cliente_nome, c.email as cliente_email 
                      FROM orcamentos o
                      LEFT JOIN clientes c ON o.cliente_id = c.id
                      WHERE o.id = ?";
    $stmt_orcamento = $conn->prepare($sql_orcamento);
    $stmt_orcamento->bind_param("i", $orcamento_id);
    $stmt_orcamento->execute();
    $result_orcamento = $stmt_orcamento->get_result();

    if ($result_orcamento->num_rows === 0) {
        die('Orçamento não encontrado.');
    }

    $orcamento = $result_orcamento->fetch_assoc();

    // Buscar itens do orçamento
    $sql_itens = "SELECT io.*, p.nome as produto_nome 
                  FROM itens_orcamento io
                  LEFT JOIN produtos p ON io.produto_id = p.id
                  WHERE io.orcamento_id = ?";
    $stmt_itens = $conn->prepare($sql_itens);
    $stmt_itens->bind_param("i", $orcamento_id);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();

    $itens = [];
    while ($item = $result_itens->fetch_assoc()) {
        // Garantir que todos os campos necessários existam
        $item['quantidade'] = isset($item['quantidade']) ? $item['quantidade'] : 1;
        $item['preco_unitario'] = isset($item['preco_unitario']) ? $item['preco_unitario'] : 0;
        $item['produto_nome'] = isset($item['produto_nome']) ? $item['produto_nome'] : 'Produto sem nome';
        
        $itens[] = $item;
    }

    // Gerar o PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,utf8_to_latin1('Orçamento #' . $orcamento_id),0,1,'C');
    
    // Informações do cliente
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_to_latin1('Dados do Cliente'),0,1);
    
    $pdf->SetFont('Arial','',11);
    $pdf->Cell(40,7,utf8_to_latin1('Nome:'),0);
    $pdf->Cell(0,7,utf8_to_latin1($orcamento['cliente_nome']),0,1);
    
    // Tabela de itens
    $pdf->Ln(10);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,utf8_to_latin1('Itens do Orçamento'),0,1);
    
    // Cabeçalho da tabela
    $pdf->SetFillColor(230,230,230);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(90,7,utf8_to_latin1('Produto'),1,0,'C',true);
    $pdf->Cell(25,7,utf8_to_latin1('Quantidade'),1,0,'C',true);
    $pdf->Cell(35,7,utf8_to_latin1('Valor Unit.'),1,0,'C',true);
    $pdf->Cell(40,7,utf8_to_latin1('Subtotal'),1,1,'C',true);
    
    // Dados da tabela
    $pdf->SetFont('Arial','',10);
    $total = 0;
    
    foreach ($itens as $item) {
        $preco = $item['preco_unitario'];
        $subtotal = $item['quantidade'] * $preco;
        $total += $subtotal;
        
        $pdf->Cell(90,7,utf8_to_latin1($item['produto_nome']),1,0);
        $pdf->Cell(25,7,$item['quantidade'],1,0,'C');
        $pdf->Cell(35,7,'R$ '.number_format($preco, 2, ',', '.'),1,0,'R');
        $pdf->Cell(40,7,'R$ '.number_format($subtotal, 2, ',', '.'),1,1,'R');
    }
    
    // Total
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(150,7,utf8_to_latin1('Valor Total:'),1,0,'R');
    $pdf->Cell(40,7,'R$ '.number_format($total, 2, ',', '.'),1,1,'R');
    
    // Saída do PDF
    $pdf->Output('I', 'Orcamento_'.$orcamento_id.'.pdf');
    
} catch (Exception $e) {
    // Limpar buffer de saída
    if (ob_get_length()) ob_end_clean();
    
    echo '<div style="color:red; font-family:Arial; padding:20px;">';
    echo '<h1>Erro ao gerar o PDF</h1>';
    echo '<p>Ocorreu um erro ao gerar o PDF: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="orcamentos.php">Voltar para orçamentos</a></p>';
    echo '</div>';
}

// Limpar qualquer buffer remanescente
if (ob_get_length()) ob_end_clean();
?>
