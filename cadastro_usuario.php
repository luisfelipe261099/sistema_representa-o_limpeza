<?php
session_start();

// Verifica se o usuário está logado e é admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Verificar se é admin
if ($_SESSION["nivel_acesso"] !== "admin") {
    header("location: dashboard.php");
    exit;
}

require_once 'includes/db_connect.php';

$message = '';
$message_type = '';
$user_id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($user_id);

// Dados do usuário para edição
$user_data = [
    'nome' => '',
    'email' => '',
    'nivel_acesso' => 'colaborador'
];

// Se for edição, buscar dados do usuário
if ($is_edit) {
    $sql_select = "SELECT * FROM usuarios WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $user_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $message = "Usuário não encontrado.";
        $message_type = "danger";
        $is_edit = false;
    }
    $stmt_select->close();
}

// Processar formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);
    $confirmar_senha = trim($_POST['confirmar_senha']);
    $nivel_acesso = $_POST['nivel_acesso'];

    // Validações
    if (empty($nome) || empty($email)) {
        $message = "Nome e email são obrigatórios.";
        $message_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email inválido.";
        $message_type = "danger";
    } elseif (!$is_edit && empty($senha)) {
        $message = "Senha é obrigatória para novos usuários.";
        $message_type = "danger";
    } elseif (!empty($senha) && $senha !== $confirmar_senha) {
        $message = "As senhas não coincidem.";
        $message_type = "danger";
    } elseif (!empty($senha) && strlen($senha) < 6) {
        $message = "A senha deve ter pelo menos 6 caracteres.";
        $message_type = "danger";
    } else {
        // Verificar se email já existe (exceto para o próprio usuário em edição)
        $sql_check = $is_edit ?
            "SELECT id FROM usuarios WHERE email = ? AND id != ?" :
            "SELECT id FROM usuarios WHERE email = ?";

        $stmt_check = $conn->prepare($sql_check);
        if ($is_edit) {
            $stmt_check->bind_param("si", $email, $user_id);
        } else {
            $stmt_check->bind_param("s", $email);
        }
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $message = "Este email já está sendo usado por outro usuário.";
            $message_type = "danger";
        } else {
            if ($is_edit) {
                // Atualizar usuário
                if (!empty($senha)) {
                    // Atualizar com nova senha
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $sql_update = "UPDATE usuarios SET nome = ?, email = ?, senha = ?, nivel_acesso = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql_update);
                    $stmt->bind_param("ssssi", $nome, $email, $senha_hash, $nivel_acesso, $user_id);
                } else {
                    // Atualizar sem alterar senha
                    $sql_update = "UPDATE usuarios SET nome = ?, email = ?, nivel_acesso = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql_update);
                    $stmt->bind_param("sssi", $nome, $email, $nivel_acesso, $user_id);
                }
            } else {
                // Inserir novo usuário
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql_insert = "INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql_insert);
                $stmt->bind_param("ssss", $nome, $email, $senha_hash, $nivel_acesso);
            }

            if ($stmt->execute()) {
                $message = $is_edit ? "Usuário atualizado com sucesso!" : "Usuário cadastrado com sucesso!";
                $message_type = "success";

                if (!$is_edit) {
                    // Redirecionar para a lista após cadastro
                    header("Location: usuarios.php");
                    exit;
                }
            } else {
                $message = "Erro ao " . ($is_edit ? "atualizar" : "cadastrar") . " usuário: " . $stmt->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

$conn->close();
include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-user-plus"></i>
        <?php echo $is_edit ? 'Editar Usuário' : 'Cadastrar Novo Usuário'; ?>
    </h1>
    <p class="page-subtitle">
        <?php echo $is_edit ? 'Atualize os dados do usuário' : 'Adicione um novo usuário ao sistema'; ?>
    </p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Form Card -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-edit"></i>
        Dados do Usuário
    </div>
    <div class="card-body-modern">
        <form method="POST" action="">
            <div class="row g-4">
                <!-- Informações Básicas -->
                <div class="col-12">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-user me-2"></i>Informações Pessoais
                    </h5>
                </div>

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

                <!-- Acesso e Segurança -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-shield-alt me-2"></i>Acesso e Segurança
                    </h5>
                </div>

                <div class="col-md-4">
                    <label for="nivel_acesso" class="form-label">Nível de Acesso *</label>
                    <select class="form-control" id="nivel_acesso" name="nivel_acesso" required>
                        <option value="colaborador" <?php echo $user_data['nivel_acesso'] == 'colaborador' ? 'selected' : ''; ?>>
                            Colaborador
                        </option>
                        <option value="admin" <?php echo $user_data['nivel_acesso'] == 'admin' ? 'selected' : ''; ?>>
                            Administrador
                        </option>
                    </select>
                    <small class="text-muted">
                        Administradores têm acesso total ao sistema
                    </small>
                </div>

                <div class="col-md-4">
                    <label for="senha" class="form-label">
                        <?php echo $is_edit ? 'Nova Senha (deixe vazio para manter)' : 'Senha *'; ?>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="senha" name="senha"
                               <?php echo !$is_edit ? 'required' : ''; ?> minlength="6">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('senha')">
                            <i class="fas fa-eye" id="senha-icon"></i>
                        </button>
                    </div>
                    <small class="text-muted">Mínimo 6 caracteres</small>
                </div>

                <div class="col-md-4">
                    <label for="confirmar_senha" class="form-label">
                        <?php echo $is_edit ? 'Confirmar Nova Senha' : 'Confirmar Senha *'; ?>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha"
                               <?php echo !$is_edit ? 'required' : ''; ?> minlength="6">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmar_senha')">
                            <i class="fas fa-eye" id="confirmar_senha-icon"></i>
                        </button>
                    </div>
                </div>

                <?php if ($is_edit): ?>
                <!-- Informações do Sistema -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-info-circle me-2"></i>Informações do Sistema
                    </h5>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Data de Cadastro</label>
                    <input type="text" class="form-control"
                           value="<?php echo date('d/m/Y H:i', strtotime($user_data['data_cadastro'])); ?>" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status da Conta</label>
                    <input type="text" class="form-control"
                           value="<?php echo (isset($user_data['ativo']) && $user_data['ativo']) ? 'Ativa' : 'Inativa'; ?>" readonly>
                </div>
                <?php endif; ?>

                <!-- Botões -->
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="usuarios.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Atualizar' : 'Cadastrar'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

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
    const senha = document.getElementById('senha').value;
    const confirmarSenha = this.value;

    if (senha !== confirmarSenha && confirmarSenha.length > 0) {
        this.setCustomValidity('As senhas não coincidem');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
    }
});

document.getElementById('senha').addEventListener('input', function() {
    const confirmarSenha = document.getElementById('confirmar_senha');
    if (confirmarSenha.value.length > 0) {
        confirmarSenha.dispatchEvent(new Event('input'));
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>
