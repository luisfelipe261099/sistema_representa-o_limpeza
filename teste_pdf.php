<?php
// Teste de geração de PDF
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db_connect.php';

echo "<h1>Teste de Geração de PDF</h1>";

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
$sql = "SELECT id FROM orcamentos ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $orcamento = $result->fetch_assoc();
    $orcamento_id = $orcamento['id'];
    
    echo "<p>Testando geração de PDF para orçamento ID: <strong>$orcamento_id</strong></p>";
    
    try {
        $pdf_content = gerarPDFComoString($orcamento_id, $conn);
        
        if ($pdf_content) {
            echo "<div style='color: green; font-weight: bold; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "✅ PDF gerado com sucesso! Tamanho: " . strlen($pdf_content) . " bytes";
            echo "</div>";
            
            echo "<p><a href='data:application/pdf;base64," . base64_encode($pdf_content) . "' target='_blank' style='background-color: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>📄 Visualizar PDF</a></p>";
        } else {
            echo "<div style='color: red; font-weight: bold; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ PDF não foi gerado (conteúdo vazio)";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red; font-weight: bold; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ Erro ao gerar PDF: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
} else {
    echo "<div style='color: orange; font-weight: bold; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
    echo "⚠️ Nenhum orçamento encontrado no banco de dados para teste";
    echo "</div>";
}

$conn->close();

echo "<br><br>";
echo "<a href='orcamentos.php' style='display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voltar para Orçamentos</a>";
echo " ";
echo "<a href='teste_email_simples.php' style='display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Testar Email</a>";
?>
