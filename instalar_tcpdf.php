<?php
// Script para baixar e instalar TCPDF automaticamente
echo "<h2>📄 Instalação da Biblioteca TCPDF</h2>";
echo "<p>Este script irá baixar e instalar a biblioteca TCPDF para geração de PDFs profissionais.</p>";

$tcpdf_dir = 'tcpdf';
$tcpdf_url = 'https://github.com/tecnickcom/TCPDF/archive/refs/heads/main.zip';
$zip_file = 'tcpdf.zip';

try {
    // Verificar se já existe
    if (is_dir($tcpdf_dir)) {
        echo "<p style='color: green;'>✅ TCPDF já está instalado!</p>";
        echo "<p><a href='gerar_pdf_orcamento.php?id=1' class='btn btn-success'>Testar Geração de PDF</a></p>";
        exit;
    }
    
    echo "<h3>1. Baixando TCPDF...</h3>";
    
    // Verificar se curl está disponível
    if (!function_exists('curl_init')) {
        throw new Exception('cURL não está disponível. Instale manualmente o TCPDF.');
    }
    
    // Baixar TCPDF
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tcpdf_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $zip_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$zip_content) {
        throw new Exception('Erro ao baixar TCPDF. Código HTTP: ' . $http_code);
    }
    
    echo "<p style='color: green;'>✅ Download concluído!</p>";
    
    echo "<h3>2. Extraindo arquivos...</h3>";
    
    // Salvar arquivo ZIP
    file_put_contents($zip_file, $zip_content);
    
    // Extrair ZIP
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        $zip->extractTo('./');
        $zip->close();
        
        // Renomear diretório
        if (is_dir('TCPDF-main')) {
            rename('TCPDF-main', $tcpdf_dir);
        }
        
        // Remover arquivo ZIP
        unlink($zip_file);
        
        echo "<p style='color: green;'>✅ Extração concluída!</p>";
    } else {
        throw new Exception('Erro ao extrair arquivo ZIP');
    }
    
    echo "<h3>3. Configurando TCPDF...</h3>";
    
    // Criar arquivo de configuração personalizado
    $config_content = '<?php
// Configuração personalizada do TCPDF para o sistema
define("K_TCPDF_EXTERNAL_CONFIG", true);

// Configurações de caminho
define("K_PATH_MAIN", dirname(__FILE__)."/");
define("K_PATH_URL", K_PATH_MAIN);
define("K_PATH_FONTS", K_PATH_MAIN."fonts/");
define("K_PATH_CACHE", K_PATH_MAIN."cache/");
define("K_PATH_URL_CACHE", K_PATH_URL."cache/");
define("K_PATH_IMAGES", K_PATH_MAIN."images/");

// Configurações gerais
define("PDF_PAGE_FORMAT", "A4");
define("PDF_PAGE_ORIENTATION", "P");
define("PDF_CREATOR", "Karla Wollinger Sistema");
define("PDF_AUTHOR", "Karla Wollinger");
define("PDF_HEADER_TITLE", "Karla Wollinger");
define("PDF_HEADER_STRING", "Representante Comercial");
define("PDF_UNIT", "mm");
define("PDF_MARGIN_HEADER", 5);
define("PDF_MARGIN_FOOTER", 10);
define("PDF_MARGIN_TOP", 27);
define("PDF_MARGIN_BOTTOM", 25);
define("PDF_MARGIN_LEFT", 15);
define("PDF_MARGIN_RIGHT", 15);
define("PDF_FONT_NAME_MAIN", "helvetica");
define("PDF_FONT_SIZE_MAIN", 10);
define("PDF_FONT_NAME_DATA", "helvetica");
define("PDF_FONT_SIZE_DATA", 8);
define("PDF_FONT_MONOSPACED", "courier");
define("PDF_IMAGE_SCALE_RATIO", 1.25);
define("HEAD_MAGNIFICATION", 1.1);
define("K_CELL_HEIGHT_RATIO", 1.25);
define("K_TITLE_MAGNIFICATION", 1.3);
define("K_SMALL_RATIO", 2/3);
define("K_THAI_TOPCHARS", true);
define("K_TCPDF_CALLS_IN_HTML", true);
define("K_TCPDF_THROW_EXCEPTION_ERROR", false);
define("K_TIMEZONE", "America/Sao_Paulo");
?>';
    
    file_put_contents($tcpdf_dir . '/config/tcpdf_config.php', $config_content);
    
    // Criar diretórios necessários
    if (!is_dir($tcpdf_dir . '/cache')) {
        mkdir($tcpdf_dir . '/cache', 0755, true);
    }
    
    if (!is_dir($tcpdf_dir . '/images')) {
        mkdir($tcpdf_dir . '/images', 0755, true);
    }
    
    echo "<p style='color: green;'>✅ Configuração concluída!</p>";
    
    echo "<h3>4. Testando instalação...</h3>";
    
    // Testar se TCPDF funciona
    require_once($tcpdf_dir . '/tcpdf.php');
    
    if (class_exists('TCPDF')) {
        echo "<p style='color: green;'>✅ TCPDF instalado e funcionando!</p>";
    } else {
        throw new Exception('TCPDF não foi carregado corretamente');
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>🎉 INSTALAÇÃO CONCLUÍDA COM SUCESSO!</h4>";
    echo "<p>A biblioteca TCPDF foi instalada e configurada.</p>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Agora você pode gerar PDFs profissionais dos orçamentos</li>";
    echo "<li>✅ Acesse qualquer orçamento e clique no botão 'Gerar PDF'</li>";
    echo "<li>✅ O PDF será gerado com layout profissional</li>";
    echo "</ul>";
    echo "<p><a href='orcamentos.php' class='btn btn-primary'>Ver Orçamentos</a> ";
    echo "<a href='gerar_pdf_orcamento.php?id=1' class='btn btn-success'>Testar PDF</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>❌ ERRO NA INSTALAÇÃO</h4>";
    echo "<p>Erro: " . $e->getMessage() . "</p>";
    echo "<p><strong>Instalação Manual:</strong></p>";
    echo "<ol>";
    echo "<li>Baixe TCPDF de: <a href='https://tcpdf.org/' target='_blank'>https://tcpdf.org/</a></li>";
    echo "<li>Extraia na pasta 'tcpdf' do seu projeto</li>";
    echo "<li>O PDF simples ainda funcionará sem TCPDF</li>";
    echo "</ol>";
    echo "</div>";
}
?>
