<?php

// Definir as constantes de conexão com o banco de dados
// Certifique-se de que 'localhost' está correto para o seu ambiente.
// Em algumas hospedagens, pode ser um IP ou outro hostname.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u182607388_karla_wollinge'); // SEU NOME DE USUÁRIO DO BANCO DE DADOS
define('DB_PASSWORD', 'T3cn0l0g1a@'); // SUA SENHA DO BANCO DE DADOS
define('DB_NAME', 'u182607388_karla_wollinge'); // O NOME DO SEU BANCO DE DADOS

// Tenta estabelecer a conexão com o banco de dados MySQL
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verifica a conexão
if ($conn->connect_error) {
    // Se houver um erro, exibe uma mensagem e encerra o script.
    // Em produção, você logaria o erro e mostraria uma mensagem genérica ao usuário.
    die("ERRO: Não foi possível conectar ao banco de dados. " . $conn->connect_error);
}

// Opcional, mas boa prática: Define o conjunto de caracteres para UTF-8 para evitar problemas com acentuação.
$conn->set_charset("utf8mb4");

// NOTA IMPORTANTE: A conexão NÃO é fechada aqui. Ela será fechada no final de cada página principal
// (dashboard.php, produtos.php, etc.) após todas as operações com o banco de dados.

?>