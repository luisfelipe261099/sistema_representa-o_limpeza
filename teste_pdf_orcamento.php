<?php
// Arquivo: teste_pdf_orcamento.php
// Descrição: Script para testar a funcionalidade de geração de PDF de orçamentos
// independente do restante do sistema.

session_start();
$_SESSION["loggedin"] = true; // Simulando usuário logado

// Incluir conexão com o banco de dados
require_once 'includes/db_connect.php';

// ID do orçamento para teste (pode ser passado via GET)
$orcamento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Se não foi especificado um ID, tenta encontrar um orçamento existente
if (!$orcamento_id) {
    $sql = "SELECT id FROM orcamentos ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $orcamento_id = $row['id'];
    } else {
        die("Nenhum orçamento encontrado no sistema. Crie um orçamento primeiro.");
    }
}

// Função para exibir informações do orçamento
function exibirInfoOrcamento($conn, $orcamento_id) {
    echo '<div style="font-family: Arial, sans-serif; margin: 20px; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">';
    echo '<h1>Teste de Geração de PDF - Orçamento #' . $orcamento_id . '</h1>';
    
    // Buscar dados do orçamento
    $sql_orcamento = "SELECT o.*, c.nome as cliente_nome 
                      FROM orcamentos o
                      LEFT JOIN clientes c ON o.cliente_id = c.id
                      WHERE o.id = ?";
    $stmt = $conn->prepare($sql_orcamento);
    $stmt->bind_param("i", $orcamento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<p style="color: red;">Orçamento não encontrado!</p>';
        echo '<p>Por favor, especifique um ID de orçamento válido.</p>';
        echo '<form method="GET">';
        echo '<label for="id">ID do Orçamento:</label> ';
        echo '<input type="number" name="id" id="id" min="1"> ';
        echo '<button type="submit">Buscar</button>';
        echo '</form>';
        echo '</div>';
        return;
    }
    
    $orcamento = $result->fetch_assoc();
    
    // Buscar itens do orçamento
    $sql_itens = "SELECT COUNT(*) as total FROM itens_orcamento WHERE orcamento_id = ?";
    $stmt = $conn->prepare($sql_itens);
    $stmt->bind_param("i", $orcamento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_itens = $row['total'];
    
    // Exibir informações
    echo '<p><strong>Cliente:</strong> ' . htmlspecialchars($orcamento['cliente_nome']) . '</p>';
    echo '<p><strong>Data:</strong> ' . date('d/m/Y', strtotime($orcamento['data_orcamento'])) . '</p>';
    echo '<p><strong>Valor Total:</strong> R$ ' . number_format($orcamento['valor_total'], 2, ',', '.') . '</p>';
    echo '<p><strong>Status:</strong> ' . ucfirst($orcamento['status_orcamento']) . '</p>';
    echo '<p><strong>Número de Itens:</strong> ' . $total_itens . '</p>';
    
    // Verificações e alertas
    if ($total_itens == 0) {
        echo '<div style="background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-top: 10px;">';
        echo '<strong>Atenção!</strong> Este orçamento não possui itens, o que pode causar problemas na geração do PDF.';
        echo '</div>';
    }
    
    if (!isset($orcamento['valor_total']) || $orcamento['valor_total'] <= 0) {
        echo '<div style="background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-top: 10px;">';
        echo '<strong>Atenção!</strong> O valor total do orçamento é zero ou não está definido.';
        echo '</div>';
    }
    
    // Links para testes
    echo '<h2>Opções de Teste:</h2>';
    echo '<a href="gerar_pdf_orcamento.php?id=' . $orcamento_id . '" style="display: inline-block; background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin-right: 10px;">Gerar PDF (Método Padrão)</a>';
    
    // Link para voltar
    echo '<p style="margin-top: 20px;"><a href="orcamentos.php">Voltar para a lista de orçamentos</a></p>';
    
    echo '</div>';
}

// Exibir formulário e informações
exibirInfoOrcamento($conn, $orcamento_id);

$conn->close();
?>
