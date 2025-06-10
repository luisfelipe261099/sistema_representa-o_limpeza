<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-file-pdf"></i>
        Sistema de PDF para Orçamentos
    </h1>
    <p class="page-subtitle">
        Demonstração e configuração do sistema de geração de PDFs profissionais
    </p>
</div>

<div class="row">
    <!-- Status do Sistema -->
    <div class="col-lg-8">
        <div class="modern-card fade-in-up mb-4">
            <div class="card-header-modern">
                <i class="fas fa-cogs"></i>
                Status do Sistema de PDF
            </div>
            <div class="card-body-modern">
                <?php
                // Verificar se TCPDF está instalado
                $tcpdf_instalado = false;
                $tcpdf_caminho = '';
                
                if (file_exists('tcpdf/tcpdf.php')) {
                    $tcpdf_caminho = 'tcpdf/tcpdf.php';
                    $tcpdf_instalado = true;
                } elseif (file_exists('TCPDF/tcpdf.php')) {
                    $tcpdf_caminho = 'TCPDF/tcpdf.php';
                    $tcpdf_instalado = true;
                }
                
                if ($tcpdf_instalado) {
                    require_once($tcpdf_caminho);
                    if (class_exists('TCPDF')) {
                        echo "<div class='alert alert-success'>";
                        echo "<h5><i class='fas fa-check-circle me-2'></i>TCPDF Instalado e Funcionando!</h5>";
                        echo "<p>✅ Biblioteca TCPDF encontrada em: <code>$tcpdf_caminho</code></p>";
                        echo "<p>✅ Classe TCPDF carregada com sucesso</p>";
                        echo "<p>✅ PDFs profissionais serão gerados com layout avançado</p>";
                        echo "</div>";
                    } else {
                        echo "<div class='alert alert-warning'>";
                        echo "<h5><i class='fas fa-exclamation-triangle me-2'></i>TCPDF Encontrado mas com Problemas</h5>";
                        echo "<p>⚠️ Arquivo TCPDF encontrado mas classe não carregou</p>";
                        echo "<p>📄 PDFs simples serão gerados (HTML para impressão)</p>";
                        echo "</div>";
                    }
                } else {
                    echo "<div class='alert alert-info'>";
                    echo "<h5><i class='fas fa-info-circle me-2'></i>TCPDF Não Instalado</h5>";
                    echo "<p>📄 PDFs simples serão gerados (HTML para impressão)</p>";
                    echo "<p>💡 Para PDFs profissionais, instale a biblioteca TCPDF</p>";
                    echo "</div>";
                }
                
                // Verificar orçamentos disponíveis
                $sql = "SELECT COUNT(*) as total FROM orcamentos";
                $result = $conn->query($sql);
                $total_orcamentos = 0;
                if ($result) {
                    $row = $result->fetch_assoc();
                    $total_orcamentos = $row['total'];
                }
                
                echo "<h6>Orçamentos Disponíveis:</h6>";
                echo "<p>📊 Total de orçamentos no sistema: <strong>$total_orcamentos</strong></p>";
                
                if ($total_orcamentos > 0) {
                    echo "<p style='color: green;'>✅ Você pode testar a geração de PDF com os orçamentos existentes</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ Crie alguns orçamentos primeiro para testar o PDF</p>";
                }
                ?>
            </div>
        </div>

        <!-- Funcionalidades -->
        <div class="modern-card fade-in-up mb-4">
            <div class="card-header-modern">
                <i class="fas fa-star"></i>
                Funcionalidades do PDF
            </div>
            <div class="card-body-modern">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-palette me-2"></i>Design Profissional</h6>
                        <ul class="small">
                            <li>Layout corporativo com cores personalizadas</li>
                            <li>Logo e informações da empresa</li>
                            <li>Tipografia profissional</li>
                            <li>Tabelas bem formatadas</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle me-2"></i>Informações Completas</h6>
                        <ul class="small">
                            <li>Dados completos do cliente</li>
                            <li>Detalhes do orçamento</li>
                            <li>Lista de produtos/serviços</li>
                            <li>Valores e totais</li>
                        </ul>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6><i class="fas fa-file-contract me-2"></i>Termos e Condições</h6>
                        <ul class="small">
                            <li>Validade do orçamento</li>
                            <li>Condições de pagamento</li>
                            <li>Observações personalizadas</li>
                            <li>Informações legais</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-download me-2"></i>Facilidade de Uso</h6>
                        <ul class="small">
                            <li>Geração com um clique</li>
                            <li>Download automático</li>
                            <li>Compatível com impressão</li>
                            <li>Arquivo pronto para envio</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orçamentos para Teste -->
        <?php if ($total_orcamentos > 0): ?>
        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-test-tube"></i>
                Testar Geração de PDF
            </div>
            <div class="card-body-modern">
                <p>Selecione um orçamento para testar a geração de PDF:</p>
                
                <?php
                $sql = "SELECT o.id, o.valor_total, o.data_orcamento, o.status_orcamento, c.nome as cliente_nome
                        FROM orcamentos o
                        LEFT JOIN clientes c ON o.cliente_id = c.id
                        ORDER BY o.data_orcamento DESC
                        LIMIT 5";
                $result = $conn->query($sql);
                
                if ($result && $result->num_rows > 0) {
                    echo "<div class='table-responsive'>";
                    echo "<table class='table table-sm table-hover'>";
                    echo "<thead><tr><th>ID</th><th>Cliente</th><th>Data</th><th>Valor</th><th>Status</th><th>Ação</th></tr></thead>";
                    echo "<tbody>";
                    
                    while ($row = $result->fetch_assoc()) {
                        $status_class = '';
                        switch ($row['status_orcamento']) {
                            case 'aprovado': $status_class = 'bg-success'; break;
                            case 'pendente': $status_class = 'bg-warning text-dark'; break;
                            case 'rejeitado': $status_class = 'bg-danger'; break;
                            default: $status_class = 'bg-secondary';
                        }
                        
                        echo "<tr>";
                        echo "<td>#" . $row['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($row['cliente_nome']) . "</td>";
                        echo "<td>" . date('d/m/Y', strtotime($row['data_orcamento'])) . "</td>";
                        echo "<td>R$ " . number_format($row['valor_total'], 2, ',', '.') . "</td>";
                        echo "<td><span class='badge $status_class'>" . ucfirst($row['status_orcamento']) . "</span></td>";
                        echo "<td>";
                        echo "<a href='gerar_pdf_orcamento.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm' target='_blank'>";
                        echo "<i class='fas fa-file-pdf me-1'></i>Gerar PDF";
                        echo "</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody></table>";
                    echo "</div>";
                } else {
                    echo "<p class='text-muted'>Nenhum orçamento encontrado.</p>";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Ações e Links -->
    <div class="col-lg-4">
        <div class="modern-card fade-in-up mb-4">
            <div class="card-header-modern">
                <i class="fas fa-tools"></i>
                Ações Rápidas
            </div>
            <div class="card-body-modern">
                <div class="d-grid gap-2">
                    <?php if (!$tcpdf_instalado): ?>
                        <a href="instalar_tcpdf.php" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Instalar TCPDF
                        </a>
                    <?php endif; ?>
                    
                    <a href="orcamentos.php" class="btn btn-info">
                        <i class="fas fa-list me-2"></i>Ver Todos os Orçamentos
                    </a>
                    
                    <a href="criar_orcamento.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Criar Novo Orçamento
                    </a>
                    
                    <?php if ($total_orcamentos > 0): ?>
                        <a href="gerar_pdf_orcamento.php?id=1" class="btn btn-danger" target="_blank">
                            <i class="fas fa-file-pdf me-2"></i>Testar PDF (ID #1)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-question-circle"></i>
                Como Usar
            </div>
            <div class="card-body-modern">
                <ol class="small">
                    <li><strong>Criar Orçamento:</strong> Use o formulário de criação de orçamentos</li>
                    <li><strong>Gerar PDF:</strong> Clique no botão "Gerar PDF" em qualquer orçamento</li>
                    <li><strong>Download:</strong> O PDF será aberto em nova aba para download</li>
                    <li><strong>Enviar:</strong> Use o arquivo PDF para enviar ao cliente</li>
                </ol>
                
                <hr>
                
                <h6>Dicas:</h6>
                <ul class="small">
                    <li>📱 PDFs funcionam em celular e desktop</li>
                    <li>🖨️ Otimizado para impressão</li>
                    <li>📧 Pronto para anexar em emails</li>
                    <li>💼 Layout profissional</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once 'includes/footer.php';
?>
