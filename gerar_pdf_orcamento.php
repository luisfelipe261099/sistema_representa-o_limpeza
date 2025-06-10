<?php
// Inicia o output buffering para evitar problemas de "headers already sent"
ob_start();
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    if (ob_get_length()) ob_end_clean();
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';
require_once 'includes/PDFHelper.php';

// Verificar se foi passado o ID do orçamento
$orcamento_id = $_GET['id'] ?? 0;

if (!$orcamento_id) {
    die('ID do orçamento não fornecido.');
}

try {
    // Verificar se FPDF está disponível
    if (!file_exists('vendor/setasign/fpdf/fpdf.php')) {
        throw new Exception("FPDF library not found. Please install FPDF.");
    }
    
    // Buscar dados do orçamento, incluindo CPF/CNPJ e tipo de pessoa do cliente
    $sql_orcamento = "SELECT o.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone, c.endereco, c.cidade, c.estado, c.cep, c.cpf_cnpj, c.tipo_pessoa
                      FROM orcamentos o
                      LEFT JOIN clientes c ON o.cliente_id = c.id
                      WHERE o.id = ?";
    $stmt_orcamento = $conn->prepare($sql_orcamento);
    if (!$stmt_orcamento) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt_orcamento->bind_param("i", $orcamento_id);
    if (!$stmt_orcamento->execute()) {
        throw new Exception("Database execution error: " . $stmt_orcamento->error);
    }
    
    $result_orcamento = $stmt_orcamento->get_result();
    if ($result_orcamento->num_rows === 0) {
        throw new Exception('Orçamento #' . $orcamento_id . ' não encontrado.');
    }

    $orcamento = $result_orcamento->fetch_assoc();
    
    // Buscar itens do orçamento com informações da empresa
    $sql_itens = "SELECT io.*, p.nome as produto_nome, p.sku as codigo, p.descricao, 
                         e.nome_empresa, e.logo_empresa
                  FROM itens_orcamento io
                  LEFT JOIN produtos p ON io.produto_id = p.id
                  LEFT JOIN empresas_representadas e ON p.empresa_id = e.id
                  WHERE io.orcamento_id = ?";
    $stmt_itens = $conn->prepare($sql_itens);
    if (!$stmt_itens) {
        throw new Exception("Database error (itens): " . $conn->error);
    }
    
    $stmt_itens->bind_param("i", $orcamento_id);
    if (!$stmt_itens->execute()) {
        throw new Exception("Database execution error (itens): " . $stmt_itens->error);
    }
    
    $result_itens = $stmt_itens->get_result();

    $itens = [];
    $empresas_logos = [];
    
    while ($item = $result_itens->fetch_assoc()) {
        $item['quantidade'] = $item['quantidade'] ?? 1;
        $item['preco_unitario'] = $item['preco_unitario'] ?? 0;
        $item['produto_nome'] = $item['produto_nome'] ?? 'Produto sem nome';
        $item['codigo'] = $item['codigo'] ?? '';
        $item['descricao'] = $item['descricao'] ?? '';
        $item['nome_empresa'] = $item['nome_empresa'] ?? '';
        $item['logo_empresa'] = $item['logo_empresa'] ?? '';
        
        if (!empty($item['logo_empresa']) && !in_array($item['logo_empresa'], $empresas_logos)) {
            $empresas_logos[] = $item['logo_empresa'];
        }
        
        $itens[] = $item;
    }
    
    if (!isset($orcamento['valor_total'])) {
        $orcamento['valor_total'] = 0;
        foreach ($itens as $item) {
            $orcamento['valor_total'] += $item['quantidade'] * $item['preco_unitario'];
        }
    }

    // Gerar o PDF
    gerarPDFModerno($orcamento, $itens, $empresas_logos);
    
} catch (Exception $e) {
    error_log("Erro ao gerar PDF do orçamento #$orcamento_id: " . $e->getMessage());
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; border-radius:5px; font-family:Arial,sans-serif; padding:20px; margin:20px; box-shadow:0 0 10px rgba(0,0,0,0.1);">';
    echo '<h1 style="color:#721c24; margin-top:0;">Erro ao gerar o PDF</h1>';
    echo '<p>Desculpe, ocorreu um erro ao tentar gerar o PDF do orçamento.</p>';
    echo '<p>Detalhes técnicos: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="orcamentos.php" style="background-color:#0275d8; color:white; padding:10px 15px; text-decoration:none; border-radius:3px;">Voltar para a lista de orçamentos</a></p>';
    echo '</div>';
    exit;
}

function gerarPDFModerno($orcamento, $itens, $empresas_logos = []) {
    try {
        require_once 'vendor/setasign/fpdf/fpdf.php';

        class ModernPDF extends FPDF {
            private $primaryColor = [28, 79, 140];
            private $accentColor = [13, 110, 253];
            private $lightGray = [248, 249, 250];
            private $darkGray = [52, 58, 64];
            private $successColor = [25, 135, 84];
            
            function convertToLatin1($text) {
                return iconv('UTF-8', 'ISO-8859-1//IGNORE', $text);
            }
            
            function Header() {
                $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
                $this->Rect(0, 0, 210, 45, 'F');
                $this->SetFillColor($this->accentColor[0], $this->accentColor[1], $this->accentColor[2]);
                $this->Rect(0, 40, 210, 5, 'F');
                
                $logo_width = 0;
                if (file_exists('logo.jpeg')) {
                    $this->Image('logo.jpeg', 15, 8, 30, 0);
                    $logo_width = 35;
                }
                
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('Arial', 'B', 20);
                $this->SetXY(15 + $logo_width, 12);
                $this->Cell(0, 8, $this->convertToLatin1('Karla Wollinger'), 0, 1, 'L');
                
                $this->SetFont('Arial', '', 10);
                $this->SetXY(15 + $logo_width, 22);
                $this->Cell(0, 5, $this->convertToLatin1('Representação Comercial'), 0, 1, 'L');
                
                $this->SetFont('Arial', '', 9);
                $this->SetXY(120, 10);
                $this->Cell(0, 4, 'karlawollinger2@gmail.com', 0, 1, 'R');
                $this->SetXY(120, 16);
                $this->Cell(0, 4, '(41) 99859-3242', 0, 1, 'R');
                $this->SetXY(120, 22);
                $this->Cell(0, 4, 'CNPJ : 30.459.625/0001-87', 0, 1, 'R');
                
                $this->SetTextColor(0, 0, 0);
                $this->Ln(25);
            }

            function Footer() {
                $this->SetY(-20);
                $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
                $this->SetLineWidth(0.5);
                $this->Line(15, $this->GetY(), 195, $this->GetY());
                
                $this->Ln(3);
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor($this->darkGray[0], $this->darkGray[1], $this->darkGray[2]);
                
                $this->SetX(15);
                $this->Cell(90, 4, $this->convertToLatin1('karla wollinger - Todos os direitos reservados'), 0, 0, 'L');
                $this->Cell(90, 4, $this->convertToLatin1('Página ') . $this->PageNo() . ' de {nb}', 0, 1, 'R');
                
                $this->Ln(1);
                $this->SetX(15);
                $this->Cell(0, 4, $this->convertToLatin1('Documento gerado em: ') . date('d/m/Y H:i'), 0, 0, 'L');
                
                $this->SetDrawColor(0, 0, 0);
                $this->SetTextColor(0, 0, 0);
                $this->SetLineWidth(0.2);
            }
            
            function ShadowBox($x, $y, $w, $h, $title, $content, $bgColor = null) {
                $this->SetFillColor(200, 200, 200);
                $this->SetDrawColor(200, 200, 200);
                $this->Rect($x + 1, $y + 1, $w, $h, 'F');
                
                if ($bgColor) {
                    $this->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
                } else {
                    $this->SetFillColor(255, 255, 255);
                }
                $this->SetDrawColor(220, 220, 220);
                $this->Rect($x, $y, $w, $h, 'DF');
                
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
                $this->SetXY($x + 3, $y + 3);
                $this->Cell($w - 6, 6, $this->convertToLatin1($title), 0, 1, 'L');
                
                $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
                $this->Line($x + 3, $y + 8, $x + $w - 3, $y + 8);
                
                $this->SetFont('Arial', '', 9);
                $this->SetTextColor(0, 0, 0);
                $this->SetXY($x + 3, $y + 11);
                $this->MultiCell($w - 6, 4, $this->convertToLatin1($content), 0, 'L');
                
                $this->SetDrawColor(0, 0, 0);
            }
            
            function ModernTable($headers, $data, $widths) {
                $start_x = 15;
                
                $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('Arial', 'B', 9);
                $this->SetDrawColor(255, 255, 255);
                
                $current_x = $start_x;
                for ($i = 0; $i < count($headers); $i++) {
                    $this->SetXY($current_x, $this->GetY());
                    $this->Cell($widths[$i], 10, $this->convertToLatin1($headers[$i]), 1, 0, 'C', true);
                    $current_x += $widths[$i];
                }
                $this->Ln();
                
                $this->SetTextColor(0, 0, 0);
                $this->SetFont('Arial', '', 8);
                $this->SetDrawColor(220, 220, 220);
                $fill = false;
                
                foreach ($data as $row) {
                    if ($fill) {
                        $this->SetFillColor($this->lightGray[0], $this->lightGray[1], $this->lightGray[2]);
                    } else {
                        $this->SetFillColor(255, 255, 255);
                    }
                    
                    $cell_height = 8;
                    if (isset($row[1]) && strpos($row[1], "\n") !== false) {
                        $lines = substr_count($row[1], "\n") + 1;
                        $cell_height = max(8, $lines * 4);
                    }
                    
                    if ($this->GetY() + $cell_height > 260) {
                        $this->AddPage();
                    }
                    
                    $start_y = $this->GetY();
                    $current_x = $start_x;
                    
                    for ($i = 0; $i < count($row); $i++) {
                        $this->SetXY($current_x, $start_y);
                        $align = ($i == 0 || $i == 3) ? 'C' : (($i >= 4) ? 'R' : 'L');
                        
                        if ($i == 1) {
                            $this->MultiCell($widths[$i], 4, $this->convertToLatin1($row[$i]), 1, $align, true);
                        } else {
                            $this->Cell($widths[$i], $cell_height, $this->convertToLatin1($row[$i]), 1, 0, $align, true);
                        }
                        
                        $current_x += $widths[$i];
                    }
                    
                    $this->SetY($start_y + $cell_height);
                    $fill = !$fill;
                }
                
                $this->SetDrawColor(0, 0, 0);
                $this->SetFillColor(255, 255, 255);
            }
        }

        PDFHelper::startPdfOutput('Proposta_Comercial_' . str_pad($orcamento['id'], 6, '0', STR_PAD_LEFT) . '.pdf');
        
        $pdf = new ModernPDF('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(28, 79, 140);
        
        $logo_empresa = null;
        if (!empty($empresas_logos)) {
            foreach ($empresas_logos as $logo_path) {
                if (file_exists($logo_path)) {
                    $logo_empresa = $logo_path;
                    break;
                }
            }
        }
        
        if ($logo_empresa) {
            $pdf->SetXY(15, $pdf->GetY());
            $pdf->Cell(140, 12, $pdf->convertToLatin1('PROPOSTA COMERCIAL'), 0, 0, 'L');
            $pdf->Image($logo_empresa, 160, $pdf->GetY() - 5, 30, 0);
            $pdf->Ln(12);
        } else {
            $pdf->Cell(0, 12, $pdf->convertToLatin1('PROPOSTA COMERCIAL'), 0, 1, 'C');
        }
        
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 8, $pdf->convertToLatin1('Pedido #') . str_pad($orcamento['id'], 6, '0', STR_PAD_LEFT), 0, 1, 'C');
        
        $pdf->SetDrawColor(28, 79, 140);
        $pdf->SetLineWidth(1);
        $pdf->Line(80, $pdf->GetY() + 2, 130, $pdf->GetY() + 2);
        $pdf->Ln(15);

        $currentY = $pdf->GetY();
        
        // --- INÍCIO DA MODIFICAÇÃO ---
        
        // Dados do cliente, agora exibindo o tipo de pessoa
        $cliente_nome = $orcamento['cliente_nome'] ?? 'Cliente não especificado';
        $client_details = "Cliente: " . $cliente_nome . "\n";

        // Adiciona a linha do Tipo de Pessoa se existir
        if (!empty($orcamento['tipo_pessoa'])) {
            $tipo_pessoa_label = ($orcamento['tipo_pessoa'] === 'fisica') ? 'Pessoa Física' : 'Pessoa Jurídica';
            $client_details .= "Tipo: " . $tipo_pessoa_label . "\n";
        }

        // Adiciona a linha de CPF/CNPJ
        if (!empty($orcamento['cpf_cnpj'])) {
            $label = ($orcamento['tipo_pessoa'] === 'juridica') ? 'CNPJ' : 'CPF';
            $client_details .= $label . ": " . $orcamento['cpf_cnpj'] . "\n";
        }

        if (!empty($orcamento['cliente_email'])) {
            $client_details .= "Email: " . $orcamento['cliente_email'] . "\n";
        }
        if (!empty($orcamento['telefone'])) {
            $client_details .= "Telefone: " . $orcamento['telefone'] . "\n";
        }
        if (!empty($orcamento['endereco'])) {
            $client_details .= "Endereco: " . $orcamento['endereco'];
            if (!empty($orcamento['cidade'])) {
                $client_details .= "\n" . $orcamento['cidade'] . ' - ' . $orcamento['estado'];
                if (!empty($orcamento['cep'])) {
                    $client_details .= " - CEP: " . $orcamento['cep'];
                }
            }
        }
        
        // Dados do orçamento
        $data_orcamento = isset($orcamento['data_orcamento']) ? date('d/m/Y', strtotime($orcamento['data_orcamento'])) : date('d/m/Y');
        $status = isset($orcamento['status_orcamento']) ? ucfirst($orcamento['status_orcamento']) : 'Pendente';
        $budget_details = "Data de Emissao: " . $data_orcamento . "\n";
        $budget_details .= "Status: " . $status . "\n";
        $budget_details .= "Validade: 30 dias\n";
        $budget_details .= "Condicoes: A combinar\n";
        $budget_details .= "Responsavel: Equipe Comercial";
        
        // Criar caixas de informação (altura da caixa do cliente aumentada para 45)
        $pdf->ShadowBox(15, $currentY, 85, 45, 'DADOS DO CLIENTE', $client_details);
        $pdf->ShadowBox(110, $currentY, 85, 45, 'DADOS DO ORCAMENTO', $budget_details);
        
        $pdf->SetY($currentY + 55); // Ajuste na posição Y para o conteúdo seguinte

        // --- FIM DA MODIFICAÇÃO ---

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(28, 79, 140);
        $pdf->Cell(0, 10, $pdf->convertToLatin1('ITENS DA PROPOSTA'), 0, 1, 'L');
        $pdf->Ln(5);
        
        $headers = ['Item', 'Produto/Servico', 'Empresa', 'Qtd', 'Valor Unit.', 'Subtotal'];
        $widths = [15, 65, 30, 15, 25, 30];
        
        $table_data = [];
        $total = 0;
        $item_num = 1;
        
        foreach ($itens as $item) {
            $quantidade = $item['quantidade'] ?? 1;
            $preco_unitario = $item['preco_unitario'] ?? 0;
            $subtotal = $quantidade * $preco_unitario;
            $total += $subtotal;
            
            $produto_info = $item['produto_nome'] ?? 'Produto sem nome';
            if (!empty($item['codigo'])) {
                $produto_info .= "\n(Ref: " . $item['codigo'] . ")";
            }
            
            $table_data[] = [
                $item_num,
                $produto_info,
                $item['nome_empresa'] ?? '',
                $quantidade,
                'R$ ' . number_format($preco_unitario, 2, ',', '.'),
                'R$ ' . number_format($subtotal, 2, ',', '.')
            ];
            $item_num++;
        }
        
        if (!empty($table_data)) {
            $pdf->ModernTable($headers, $table_data, $widths);
        }
        
        $pdf->Ln(10);
        $total_y = $pdf->GetY();
        $valor_total = $orcamento['valor_total'] ?? $total;
        
        $pdf->SetFillColor(25, 135, 84);
        $pdf->Rect(115, $total_y, 80, 25, 'F');
        $pdf->SetDrawColor(25, 135, 84);
        $pdf->SetLineWidth(1);
        $pdf->Rect(115, $total_y, 80, 25, 'D');
        
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetXY(120, $total_y + 5);
        $pdf->Cell(70, 6, $pdf->convertToLatin1('VALOR TOTAL DA PROPOSTA'), 0, 1, 'C');
        
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetXY(120, $total_y + 13);
        $pdf->Cell(70, 8, 'R$ ' . number_format($valor_total, 2, ',', '.'), 0, 1, 'C');
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY($total_y + 30);

        if (!empty($orcamento['observacoes'])) {
            $pdf->Ln(5);
            $pdf->ShadowBox(15, $pdf->GetY(), 180, 30, 'OBSERVACOES IMPORTANTES', $orcamento['observacoes'], [255, 251, 235]);
            $pdf->Ln(35);
        }
        
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(28, 79, 140);
        $pdf->Cell(0, 8, $pdf->convertToLatin1('TERMOS E CONDICOES'), 0, 1, 'L');
        
        $pdf->SetDrawColor(28, 79, 140);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        
        $termos = [
            'Este orcamento tem validade de 30 dias a partir da data de emissao.',
            'Os precos estao sujeitos a alteracao sem aviso previo.',
            'O prazo de entrega sera informado apos confirmacao do pedido.',
            'Condicoes de pagamento: a combinar.',
            'Produtos sujeitos a disponibilidade de estoque.',
            'Frete por conta do cliente, salvo acordo em contrario.',
            'Garantia conforme especificacao do fabricante.',
            'Instalacao e treinamento conforme necessidade.'
        ];
        
        foreach ($termos as $termo) {
            $pdf->Cell(5, 5, chr(149), 0, 0, 'C');
            $pdf->Cell(0, 5, $pdf->convertToLatin1($termo), 0, 1, 'L');
        }
        
        $pdf->Ln(15);
        
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }
        
        $contact_y = $pdf->GetY();
        
        $contact_info = "Equipe Comercial\n";
        $contact_info .= "Email: karlawollinger2@gmail.com\n";
        $contact_info .= "Telefone: (41) 99859-3242\n";
        $contact_info .= "Atendimento: Seg a Sex, 8h as 18h";
        
        $signature_info = "Assinatura:\n\n";
        $signature_info .= "_________________________________\n\n";
        $signature_info .= "Data: ____/____/______\n\n";
        $signature_info .= "Nome:\n";
        $signature_info .= "_________________________________\n\n";
        $signature_info .= "CPF/CNPJ:\n";
        $signature_info .= "_________________________________";
        
        $pdf->ShadowBox(15, $contact_y, 85, 55, 'CONTATO PARA DUVIDAS', $contact_info);
        $pdf->ShadowBox(110, $contact_y, 85, 55, 'ACEITE DA PROPOSTA', $signature_info);
        
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(0, 0, 0);
        
        $filename = 'Proposta_Comercial_' . str_pad($orcamento['id'], 6, '0', STR_PAD_LEFT) . '_' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'I');
        
    } catch (Exception $e) {
        error_log("Erro ao gerar PDF moderno: " . $e->getMessage());
        
        echo '<div style="color:#721c24; background-color:#f8d7da; border:1px solid #f5c6cb; border-radius:5px; font-family:Arial,sans-serif; padding:20px; margin:20px; box-shadow:0 0 10px rgba(0,0,0,0.1);">';
        echo '<h2>Erro ao gerar o PDF</h2>';
        echo '<p>Ocorreu um erro ao tentar gerar o PDF do orçamento.</p>';
        echo '<p>Detalhes técnicos: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><a href="javascript:history.back()" style="background-color:#0275d8; color:white; padding:10px 15px; text-decoration:none; border-radius:3px;">Voltar</a></p>';
        echo '</div>';
    }
}

$conn->close();
?>
