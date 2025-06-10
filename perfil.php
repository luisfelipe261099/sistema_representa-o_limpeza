<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'includes/db_connect.php';

$message = '';
$message_type = '';
$user_id = $_SESSION['id'];

// Buscar dados do usuário
$sql_select = "SELECT * FROM usuarios WHERE id = ?";
$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("i", $user_id);
$stmt_select->execute();
$result = $stmt_select->get_result();
$user_data = $result->fetch_assoc();
$stmt_select->close();

// Processar formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action == 'update_profile') {
        // Atualizar perfil
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);

        // Validações
        if (empty($nome) || empty($email)) {
            $message = "Nome e email são obrigatórios.";
            $message_type = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Email inválido.";
            $message_type = "danger";
        } else {
            // Verificar se email já existe (exceto para o próprio usuário)
            $sql_check = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("si", $email, $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $message = "Este email já está sendo usado por outro usuário.";
                $message_type = "danger";
            } else {
                // Atualizar dados
                $sql_update = "UPDATE usuarios SET nome = ?, email = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("ssi", $nome, $email, $user_id);

                if ($stmt_update->execute()) {
                    $message = "Perfil atualizado com sucesso!";
                    $message_type = "success";

                    // Atualizar dados na sessão
                    $_SESSION['nome'] = $nome;
                    $_SESSION['email'] = $email;

                    // Atualizar dados locais
                    $user_data['nome'] = $nome;
                    $user_data['email'] = $email;
                } else {
                    $message = "Erro ao atualizar perfil: " . $stmt_update->error;
                    $message_type = "danger";
                }
                $stmt_update->close();
            }
            $stmt_check->close();
        }
    } elseif ($action == 'change_password') {
        // Alterar senha
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        // Validações
        if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
            $message = "Todos os campos de senha são obrigatórios.";
            $message_type = "danger";
        } elseif ($nova_senha !== $confirmar_senha) {
            $message = "A nova senha e a confirmação não coincidem.";
            $message_type = "danger";
        } elseif (strlen($nova_senha) < 6) {
            $message = "A nova senha deve ter pelo menos 6 caracteres.";
            $message_type = "danger";
        } elseif (!password_verify($senha_atual, $user_data['senha'])) {
            $message = "Senha atual incorreta.";
            $message_type = "danger";
        } else {
            // Atualizar senha
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $sql_update_senha = "UPDATE usuarios SET senha = ? WHERE id = ?";
            $stmt_update_senha = $conn->prepare($sql_update_senha);
            $stmt_update_senha->bind_param("si", $nova_senha_hash, $user_id);

            if ($stmt_update_senha->execute()) {
                $message = "Senha alterada com sucesso!";
                $message_type = "success";

                // Atualizar dados locais
                $user_data['senha'] = $nova_senha_hash;
            } else {
                $message = "Erro ao alterar senha: " . $stmt_update_senha->error;
                $message_type = "danger";
            }
            $stmt_update_senha->close();
        }
    }
}

$conn->close();
include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-user-circle"></i>
        Meu Perfil
    </h1>
    <p class="page-subtitle">Gerencie suas informações pessoais e configurações</p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Informações do Perfil -->
    <div class="col-lg-4">
        <div class="modern-card fade-in-up">
            <div class="card-body-modern text-center">
                <div class="profile-avatar mx-auto mb-3">
                    <?php echo strtoupper(substr($user_data['nome'], 0, 1)); ?>
                </div>
                <h5 class="mb-1"><?php echo htmlspecialchars($user_data['nome']); ?></h5>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user_data['email']); ?></p>
                <span class="status-badge <?php echo $user_data['nivel_acesso'] == 'admin' ? 'status-success' : 'status-info'; ?>">
                    <?php echo $user_data['nivel_acesso'] == 'admin' ? 'Administrador' : 'Colaborador'; ?>
                </span>

                <hr class="my-4">

                <div class="row g-3 text-start">
                    <div class="col-12">
                        <small class="text-muted">Membro desde</small>
                        <div class="fw-semibold"><?php echo date('d/m/Y', strtotime($user_data['data_cadastro'])); ?></div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Status da conta</small>
                        <div class="fw-semibold">
                            <?php echo (isset($user_data['ativo']) && $user_data['ativo']) ? 'Ativa' : 'Inativa'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulários -->
    <div class="col-lg-8">
        <!-- Atualizar Perfil -->
        <div class="modern-card fade-in-up mb-4">
            <div class="card-header-modern">
                <i class="fas fa-user-edit"></i>
                Informações Pessoais
            </div>
            <div class="card-body-modern">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" id="nome" name="nome"
                                   value="<?php echo htmlspecialchars($user_data['nome']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salvar Alterações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Alterar Senha -->
        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-lock"></i>
                Alterar Senha
            </div>
            <div class="card-body-modern">
                <form method="POST" action="" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="senha_atual" class="form-label">Senha Atual *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('senha_atual')">
                                    <i class="fas fa-eye" id="senha_atual-icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="nova_senha" class="form-label">Nova Senha *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha"
                                       required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('nova_senha')">
                                    <i class="fas fa-eye" id="nova_senha-icon"></i>
                                </button>
                            </div>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha"
                                       required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmar_senha')">
                                    <i class="fas fa-eye" id="confirmar_senha-icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Alterar Senha
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Atividade Recente (se for admin) -->
<?php if ($user_data['nivel_acesso'] == 'admin'): ?>
<div class="modern-card fade-in-up mt-4">
    <div class="card-header-modern">
        <i class="fas fa-chart-line"></i>
        Acesso Rápido - Administrador
    </div>
    <div class="card-body-modern">
        <div class="row g-3">
            <div class="col-md-3">
                <a href="usuarios.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-users-cog d-block mb-2"></i>
                    Gerenciar Usuários
                </a>
            </div>
            <div class="col-md-3">
                <a href="empresas_representadas.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-building d-block mb-2"></i>
                    Empresas
                </a>
            </div>
            <div class="col-md-3">
                <a href="dashboard.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-chart-bar d-block mb-2"></i>
                    Dashboard
                </a>
            </div>
            <div class="col-md-3">
                <a href="logout.php" class="btn btn-outline-danger w-100">
                    <i class="fas fa-sign-out-alt d-block mb-2"></i>
                    Sair do Sistema
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Validação de senhas em tempo real
document.getElementById('confirmar_senha').addEventListener('input', function() {
    const novaSenha = document.getElementById('nova_senha').value;
    const confirmarSenha = this.value;

    if (novaSenha !== confirmarSenha && confirmarSenha.length > 0) {
        this.setCustomValidity('As senhas não coincidem');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
    }
});

document.getElementById('nova_senha').addEventListener('input', function() {
    const confirmarSenha = document.getElementById('confirmar_senha');
    if (confirmarSenha.value.length > 0) {
        confirmarSenha.dispatchEvent(new Event('input'));
    }
});

// Limpar formulário de senha após envio bem-sucedido
<?php if ($message_type == 'success' && strpos($message, 'Senha') !== false): ?>
document.getElementById('passwordForm').reset();
<?php endif; ?>
</script>

<?php include_once 'includes/footer.php'; ?>
