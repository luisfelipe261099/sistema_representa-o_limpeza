<?php
// Inicia a sessão no início do script. Isso é fundamental para gerenciar o estado do usuário.
session_start();

// Inclui o arquivo de conexão com o banco de dados.
// Usamos 'require_once' para garantir que a conexão seja estabelecida e para exibir um erro fatal se o arquivo não for encontrado.
require_once 'includes/db_connect.php';

// Inicializa a variável para mensagens de erro de login.
$login_err = "";

// Verifica se o formulário de login foi enviado via método POST.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta e sanitiza os dados do formulário, removendo espaços em branco extras.
    $email = trim($_POST["email"]);
    $senha = trim($_POST["senha"]);

    // Validação básica: verifica se os campos não estão vazios.
    if (empty($email) || empty($senha)) {
        $login_err = "Por favor, preencha todos os campos.";
    } else {
        // Prepara a query SQL para buscar o usuário pelo email.
        // Usamos prepared statements para prevenir SQL Injection.
        $sql = "SELECT id, nome, email, senha, nivel_acesso FROM usuarios WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Vincula o parâmetro (email) à declaração preparada. 's' indica que é um string.
            $stmt->bind_param("s", $param_email);

            // Define o valor do parâmetro.
            $param_email = $email;

            // Tenta executar a declaração preparada.
            if ($stmt->execute()) {
                // Armazena o resultado da query.
                $stmt->store_result();

                // Verifica se um usuário com o email fornecido foi encontrado.
                if ($stmt->num_rows == 1) {
                    // Vincula as colunas do resultado às variáveis PHP.
                    $stmt->bind_result($id, $nome, $email_db, $senha_hash, $nivel_acesso);
                    // Pega o resultado (apenas uma linha, como sabemos pelo num_rows == 1).
                    if ($stmt->fetch()) {
                        // Verifica se a senha fornecida corresponde ao hash armazenado no banco de dados.
                        // password_verify() é a função correta para comparar senhas hashificadas com password_hash().
                        if (password_verify($senha, $senha_hash)) {
                            // Senha correta. Inicia/rege gera a sessão.
                            // session_regenerate_id(true) aumenta a segurança contra ataques de fixação de sessão.
                            session_regenerate_id(true); 
                            
                            // Armazena as informações do usuário na sessão.
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["nome"] = $nome;
                            $_SESSION["nivel_acesso"] = $nivel_acesso;

                            // Redireciona o usuário para a página do dashboard.
                            header("location: dashboard.php");
                            // Garante que o script pare de executar após o redirecionamento.
                            exit; 
                        } else {
                            // Senha inválida.
                            $login_err = "Email ou senha inválidos.";
                        }
                    }
                } else {
                    // Email não encontrado.
                    $login_err = "Email ou senha inválidos.";
                }
            } else {
                // Erro na execução da query.
                $login_err = "Ops! Algo deu errado. Por favor, tente novamente mais tarde.";
                // Para depuração: echo "Erro na execução: " . $stmt->error;
            }
            // Fecha a declaração preparada.
            $stmt->close();
        } else {
            // Erro na preparação da query.
            $login_err = "Erro interno no servidor ao preparar a autenticação.";
            // Para depuração: echo "Erro na preparação: " . $conn->error;
        }
    }
}

// Fecha a conexão com o banco de dados.
$conn->close();

// O HTML da página de login começa aqui.
// Esta página não inclui o header.php ou footer.php como as outras páginas,
// pois tem um layout de página cheia específico para o login.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karla Wollinger - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>
<body class="bg-light-gray d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5 col-xl-4">
                <div class="card shadow-lg p-4 rounded-3 login-card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="fas fa-cubes fa-3x text-primary mb-3"></i>
                            <h3 class="fw-bold text-primary">Karla Wollinger</h3>
                            <p class="text-muted">Sistema de Gestão Comercial</p>
                        </div>
                        <h4 class="text-center mb-4">Acesse sua conta</h4>

                        <?php
                        // Exibe a mensagem de erro de login, se houver.
                        if (!empty($login_err)) {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    ' . $login_err . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
                        }
                        ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" id="email" class="form-control" placeholder="seuemail@exemplo.com" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="senha" class="form-label">Senha:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="senha" id="senha" class="form-control" placeholder="********" required>
                                </div>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt me-2"></i> Entrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>