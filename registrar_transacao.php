<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$id = $tipo = $valor = $data_transacao = $descricao = $categoria = $referencia_id = $tabela_referencia = "";
$title = "Registrar Nova Transação";
$submit_button_text = "Registrar Transação";
$message = '';
$message_type = '';

// Processar formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = trim($_POST["id"] ?? '');
    $tipo = trim($_POST["tipo"]);
    $valor = str_replace(',', '.', trim($_POST["valor"])); // Converte vírgula para ponto
    $data_transacao_str = trim($_POST["data_transacao_date"]) . ' ' . trim($_POST["data_transacao_time"]) . ':00'; // Combina data e hora
    $descricao = trim($_POST["descricao"]);
    $categoria = trim($_POST["categoria"]);
    $referencia_id = empty(trim($_POST["referencia_id"])) ? NULL : trim($_POST["referencia_id"]);
    $tabela_referencia = empty(trim($_POST["tabela_referencia"])) ? NULL : trim($_POST["tabela_referencia"]);

    // Validação básica
    if (empty($tipo) || !is_numeric($valor) || $valor <= 0 || empty($data_transacao_str) || empty($descricao) || empty($categoria)) {
        $message = "Por favor, preencha todos os campos obrigatórios e garanta que o valor seja numérico e positivo.";
        $message_type = "danger";
    } else {
        // Assegura que referencia_id e tabela_referencia sejam NULL se não forem fornecidos
        if (empty($referencia_id) || empty($tabela_referencia)) {
            $referencia_id = NULL;
            $tabela_referencia = NULL;
        }

        if (empty($id)) { // Nova Transação
            $sql = "INSERT INTO transacoes_financeiras (tipo, valor, data_transacao, descricao, categoria, referencia_id, tabela_referencia) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sdsssis", $tipo, $valor, $data_transacao_str, $descricao, $categoria, $referencia_id, $tabela_referencia);
                if ($stmt->execute()) {
                    $message = "Transação registrada com sucesso!";
                    $message_type = "success";
                    // Limpa os campos após o cadastro
                    $tipo = $valor = $data_transacao = $descricao = $categoria = $referencia_id = $tabela_referencia = "";
                } else {
                    $message = "Erro ao registrar transação: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Erro na preparação da query de inserção: " . $conn->error;
                $message_type = "danger";
            }
        } else { // Editar Transação Existente
            $sql = "UPDATE transacoes_financeiras SET tipo = ?, valor = ?, data_transacao = ?, descricao = ?, categoria = ?, referencia_id = ?, tabela_referencia = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sdsssis", $tipo, $valor, $data_transacao_str, $descricao, $categoria, $referencia_id, $tabela_referencia, $id);
                if ($stmt->execute()) {
                    $message = "Transação atualizada com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao atualizar transação: " . $stmt->error;
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
    $title = "Editar Transação";
    $submit_button_text = "Atualizar Transação";

    $sql_edit = "SELECT id, tipo, valor, data_transacao, descricao, categoria, referencia_id, tabela_referencia FROM transacoes_financeiras WHERE id = ?";
    if ($stmt_edit = $conn->prepare($sql_edit)) {
        $stmt_edit->bind_param("i", $id);
        if ($stmt_edit->execute()) {
            $result_edit = $stmt_edit->get_result();
            if ($result_edit->num_rows == 1) {
                $row = $result_edit->fetch_assoc();
                $tipo = $row['tipo'];
                $valor = number_format($row['valor'], 2, ',', '.'); // Formata para exibição
                $data_transacao_datetime = new DateTime($row['data_transacao']);
                $data_transacao_date = $data_transacao_datetime->format('Y-m-d');
                $data_transacao_time = $data_transacao_datetime->format('H:i');
                $descricao = $row['descricao'];
                $categoria = $row['categoria'];
                $referencia_id = $row['referencia_id'];
                $tabela_referencia = $row['tabela_referencia'];
            } else {
                $message = "Transação não encontrada.";
                $message_type = "danger";
                $id = ""; // Reset para tratar como novo cadastro se ID não encontrado
                $title = "Registrar Nova Transação";
                $submit_button_text = "Registrar Transação";
            }
        } else {
            $message = "Erro ao buscar transação para edição: " . $stmt_edit->error;
            $message_type = "danger";
        }
        $stmt_edit->close();
    }
} else { // Valores padrão para nova transação
    $data_transacao_date = date('Y-m-d');
    $data_transacao_time = date('H:i');
    $tipo = 'entrada'; // Padrão para nova transação
}


// Não fechar a conexão aqui pois ainda vamos usar no HTML
// $conn->close();

include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-<?php echo ($id ? 'edit' : 'plus-circle'); ?>"></i>
        <?php echo $title; ?>
    </h1>
    <p class="page-subtitle">
        <?php echo $id ? 'Atualize os dados da transação financeira' : 'Registre uma nova entrada ou saída financeira'; ?>
    </p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'info' ? 'info-circle' : 'exclamation-triangle'); ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Form Card -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-money-bill-wave"></i>
        Dados da Transação
    </div>
    <div class="card-body-modern">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

            <!-- Informações Básicas -->
            <div class="row g-4">
                <div class="col-12">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-info-circle me-2"></i>Informações Básicas
                    </h5>
                </div>

                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo de Transação *</label>
                    <select class="form-control" id="tipo" name="tipo" required>
                        <option value="entrada" <?php echo ($tipo == 'entrada' ? 'selected' : ''); ?>>
                            <i class="fas fa-arrow-up"></i> Entrada (Receita)
                        </option>
                        <option value="saida" <?php echo ($tipo == 'saida' ? 'selected' : ''); ?>>
                            <i class="fas fa-arrow-down"></i> Saída (Despesa)
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="valor" class="form-label">Valor (R$) *</label>
                    <input type="text" class="form-control" id="valor" name="valor" value="<?php echo htmlspecialchars($valor); ?>" required placeholder="0,00">
                </div>
                <div class="col-md-4">
                    <label for="categoria" class="form-label">Categoria *</label>
                    <input type="text" class="form-control" id="categoria" name="categoria" value="<?php echo htmlspecialchars($categoria); ?>" required placeholder="Ex: Vendas, Compras, Frete">
                </div>

                <!-- Data e Hora -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-calendar-alt me-2"></i>Data e Hora
                    </h5>
                </div>

                <div class="col-md-6">
                    <label for="data_transacao_date" class="form-label">Data da Transação *</label>
                    <input type="date" class="form-control" id="data_transacao_date" name="data_transacao_date" value="<?php echo htmlspecialchars($data_transacao_date); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="data_transacao_time" class="form-label">Hora da Transação *</label>
                    <input type="time" class="form-control" id="data_transacao_time" name="data_transacao_time" value="<?php echo htmlspecialchars($data_transacao_time); ?>" required>
                </div>

                <!-- Descrição -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-file-alt me-2"></i>Descrição
                    </h5>
                </div>

                <div class="col-12">
                    <label for="descricao" class="form-label">Descrição *</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3" required placeholder="Detalhes da transação..."><?php echo htmlspecialchars($descricao); ?></textarea>
                </div>
            </div>

                <!-- Vínculo (Opcional) -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-link me-2"></i>Vínculo (Opcional)
                    </h5>
                    <p class="text-muted small">Vincule esta transação a uma venda ou outro registro para rastreabilidade.</p>
                </div>

                <div class="col-md-6">
                    <label for="tabela_referencia" class="form-label">Tipo de Referência</label>
                    <select class="form-control" id="tabela_referencia" name="tabela_referencia">
                        <option value="">Nenhum</option>
                        <option value="vendas" <?php echo ($tabela_referencia == 'vendas' ? 'selected' : ''); ?>>Venda</option>
                        <option value="orcamentos" <?php echo ($tabela_referencia == 'orcamentos' ? 'selected' : ''); ?>>Orçamento</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="referencia_id" class="form-label">ID de Referência</label>
                    <input type="number" class="form-control" id="referencia_id" name="referencia_id" value="<?php echo htmlspecialchars($referencia_id); ?>" placeholder="Ex: ID da Venda, ID do Orçamento">
                </div>

                <!-- Botões -->
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="financeiro.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-<?php echo ($id ? 'save' : 'plus'); ?> me-2"></i><?php echo $submit_button_text; ?>
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>

<?php
$conn->close();
include_once 'includes/footer.php';
?>