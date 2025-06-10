<?php
// Inicia a sessão
session_start();

// Desfaz todas as variáveis de sessão
$_SESSION = array();

// Destroi a sessão.
session_destroy();

// Redireciona para a página de login
header("location: index.php");
exit;
?>