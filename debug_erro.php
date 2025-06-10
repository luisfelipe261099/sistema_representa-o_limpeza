<?php
// Arquivo: debug_erro.php
// Crie este arquivo na raiz do projeto para verificar o erro

// Ativar exibição de todos os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>Diagnóstico do Sistema</h2>";

// 1. Verificar PHPMailer
echo "<h3>1. Verificando PHPMailer:</h3>";
if (file_exists('vendor/autoload.php')) {
    echo "✓ Autoload encontrado<br>";
    try {
        require_once 'vendor/autoload.php';
        echo "✓ Autoload carregado<br>";
        
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "✓ PHPMailer disponível<br>";
        } else {
            echo "✗ PHPMailer NÃO encontrado<br>";
        }
    } catch (Exception $e) {
        echo "✗ Erro ao carregar autoload: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ Arquivo vendor/autoload.php não encontrado<br>";
}

// 2. Verificar extensões PHP
echo "<h3>2. Verificando Extensões PHP:</h3>";
$extensoes_necessarias = ['openssl', 'curl', 'mbstring', 'json'];
foreach ($extensoes_necessarias as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext: Disponível<br>";
    } else {
        echo "✗ $ext: NÃO disponível<br>";
    }
}

// 3. Verificar versão PHP
echo "<h3>3. Versão PHP:</h3>";
echo "Versão: " . PHP_VERSION . "<br>";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "✓ Versão compatível<br>";
} else {
    echo "✗ Versão muito antiga (mínimo: 7.4)<br>";
}

// 4. Verificar permissões de escrita
echo "<h3>4. Verificando Permissões:</h3>";
if (is_writable('.')) {
    echo "✓ Diretório atual: Escrita permitida<br>";
} else {
    echo "✗ Diretório atual: SEM permissão de escrita<br>";
}

// 5. Teste básico de sessão
echo "<h3>5. Testando Sessão:</h3>";
try {
    session_start();
    echo "✓ Sessão iniciada com sucesso<br>";
} catch (Exception $e) {
    echo "✗ Erro na sessão: " . $e->getMessage() . "<br>";
}

// 6. Verificar últimos erros
echo "<h3>6. Últimos Erros PHP:</h3>";
$last_error = error_get_last();
if ($last_error) {
    echo "<pre>";
    print_r($last_error);
    echo "</pre>";
} else {
    echo "Nenhum erro recente registrado.<br>";
}

// 7. Verificar se é possível criar arquivos
echo "<h3>7. Teste de Criação de Arquivo:</h3>";
try {
    $test_file = 'test_' . time() . '.txt';
    if (file_put_contents($test_file, 'teste')) {
        echo "✓ Arquivo criado com sucesso<br>";
        unlink($test_file); // Remove o arquivo de teste
    } else {
        echo "✗ Não foi possível criar arquivo<br>";
    }
} catch (Exception $e) {
    echo "✗ Erro ao criar arquivo: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>Execute este arquivo primeiro para identificar o problema.</strong></p>";
echo "<p><a href='teste_email_simples.php'>Depois teste o e-mail simples</a></p>";
?>