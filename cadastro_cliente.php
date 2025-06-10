<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$id = $nome = $tipo_pessoa = $cpf_cnpj = $email = $telefone = $endereco = $cidade = $estado = $cep = "";
$title = "Cadastrar Novo Cliente";
$submit_button_text = "Cadastrar Cliente";
$message = '';
$message_type = '';

// Processar formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = trim($_POST["id"] ?? ''); // Pode vir vazio para novo cadastro
    $nome = trim($_POST["nome"]);
    $tipo_pessoa = trim($_POST["tipo_pessoa"]);
    $cpf_cnpj = trim($_POST["cpf_cnpj"]); // Sem formatação aqui, armazena apenas números
    $email = trim($_POST["email"]);
    $telefone = trim($_POST["telefone"]); // Sem formatação aqui, armazena apenas números
    $endereco = trim($_POST["endereco"]);
    $cidade = trim($_POST["cidade"]);
    $estado = trim($_POST["estado"]);
    $cep = trim($_POST["cep"]); // Sem formatação aqui, armazena apenas números

    // Validação básica
    if (empty($nome) || empty($tipo_pessoa)) {
        $message = "Nome e Tipo de Pessoa são obrigatórios.";
        $message_type = "danger";
    } else {
        if (empty($id)) { // Novo Cliente
            $sql = "INSERT INTO clientes (nome, tipo_pessoa, cpf_cnpj, email, telefone, endereco, cidade, estado, cep) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssssss", $nome, $tipo_pessoa, $cpf_cnpj, $email, $telefone, $endereco, $cidade, $estado, $cep);
                if ($stmt->execute()) {
                    $message = "Cliente cadastrado com sucesso!";
                    $message_type = "success";
                    // Limpa os campos após o cadastro
                    $nome = $tipo_pessoa = $cpf_cnpj = $email = $telefone = $endereco = $cidade = $estado = $cep = "";
                } else {
                    $message = "Erro ao cadastrar cliente: " . $stmt->error;
                    // Verifique se o erro é de chave duplicada (CPF/CNPJ já existe)
                    if ($conn->errno == 1062) { // MySQL error code for duplicate entry
                        $message .= " (CPF/CNPJ já cadastrado)";
                    }
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Erro na preparação da query de inserção: " . $conn->error;
                $message_type = "danger";
            }
        } else { // Editar Cliente Existente
            $sql = "UPDATE clientes SET nome = ?, tipo_pessoa = ?, cpf_cnpj = ?, email = ?, telefone = ?, endereco = ?, cidade = ?, estado = ?, cep = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssssssi", $nome, $tipo_pessoa, $cpf_cnpj, $email, $telefone, $endereco, $cidade, $estado, $cep, $id);
                if ($stmt->execute()) {
                    $message = "Cliente atualizado com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao atualizar cliente: " . $stmt->error;
                    if ($conn->errno == 1062) {
                        $message .= " (CPF/CNPJ já cadastrado para outro cliente)";
                    }
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Erro na preparação da query de atualização: " . $conn->error;
                $message_type = "danger";
            }
        }
    }
}

// Preencher formulário para edição se um ID for passado via GET
if (isset($_GET["id"]) && empty($message)) {
    $id = trim($_GET["id"]);
    $sql_edit = "SELECT id, nome, tipo_pessoa, cpf_cnpj, email, telefone, endereco, cidade, estado, cep FROM clientes WHERE id = ?";
    if ($stmt_edit = $conn->prepare($sql_edit)) {
        $stmt_edit->bind_param("i", $id);
        if ($stmt_edit->execute()) {
            $result_edit = $stmt_edit->get_result();
            if ($result_edit->num_rows == 1) {
                $row = $result_edit->fetch_assoc();
                $nome = $row['nome'];
                $tipo_pessoa = $row['tipo_pessoa'];
                $cpf_cnpj = $row['cpf_cnpj'];
                $email = $row['email'];
                $telefone = $row['telefone'];
                $endereco = $row['endereco'];
                $cidade = $row['cidade'];
                $estado = $row['estado'];
                $cep = $row['cep'];

                $title = "Editar Cliente";
                $submit_button_text = "Atualizar Cliente";
            } else {
                $message = "Cliente não encontrado.";
                $message_type = "danger";
                $id = "";
            }
        } else {
            $message = "Erro ao buscar cliente para edição: " . $stmt_edit->error;
            $message_type = "danger";
        }
        $stmt_edit->close();
    }
}

$conn->close();

include_once 'includes/header.php';
?>

<h2 class="mb-4"><i class="fas fa-<?php echo ($id ? 'user-edit' : 'user-plus'); ?> me-2"></i> <?php echo $title; ?></h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nome" class="form-label">Nome / Razão Social <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="tipo_pessoa" class="form-label">Tipo de Pessoa <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipo_pessoa" name="tipo_pessoa" required>
                        <option value="">Selecione...</option>
                        <option value="fisica" <?php echo ($tipo_pessoa == 'fisica' ? 'selected' : ''); ?>>Física</option>
                        <option value="juridica" <?php echo ($tipo_pessoa == 'juridica' ? 'selected' : ''); ?>>Jurídica</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="cpf_cnpj" class="form-label">CPF / CNPJ</label>
                    <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo htmlspecialchars($cpf_cnpj); ?>" placeholder="Somente números" maxlength="18">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($telefone); ?>" placeholder="(XX) XXXXX-XXXX" maxlength="15">
                </div>
            </div>

            <hr class="my-4">
            <h4><i class="fas fa-map-marker-alt me-2 text-primary"></i> Endereço</h4>

            <div class="mb-3">
                <label for="endereco" class="form-label">Endereço Completo</label>
                <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco); ?>" placeholder="Rua, Número, Bairro">
            </div>

            <div class="row">
                <div class="col-md-5 mb-3">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="estado" class="form-label">Estado (UF)</label>
                    <input type="text" class="form-control" id="estado" name="estado" value="<?php echo htmlspecialchars($estado); ?>" maxlength="2">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="cep" class="form-label">CEP</label>
                    <input type="text" class="form-control" id="cep" name="cep" value="<?php echo htmlspecialchars($cep); ?>" placeholder="XXXXX-XXX" maxlength="9">
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-<?php echo ($id ? 'save' : 'user-plus'); ?> me-2"></i> <?php echo $submit_button_text; ?>
                </button>
                <a href="clientes.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Voltar para Clientes
                </a>
            </div>
        </form>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>