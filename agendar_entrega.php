<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$agendamento_id = $venda_id = $orcamento_id = $cliente_id = $data_hora_entrega = $endereco_entrega = $status_entrega = $observacoes = "";
$title = "Agendar Nova Entrega";
$submit_button_text = "Agendar Entrega";
$message = '';
$message_type = '';

// Buscar clientes, vendas e orçamentos para os campos SELECT
$clientes_options = $conn->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
$vendas_options = $conn->query("SELECT id, cliente_id FROM vendas ORDER BY id DESC");
$orcamentos_options = $conn->query("SELECT id, cliente_id FROM orcamentos ORDER BY id DESC");

// Processar formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $agendamento_id = trim($_POST["agendamento_id"] ?? '');
    $venda_id = empty(trim($_POST["venda_id"])) ? NULL : trim($_POST["venda_id"]); // Pode ser NULL
    $orcamento_id = empty(trim($_POST["orcamento_id"])) ? NULL : trim($_POST["orcamento_id"]); // Pode ser NULL
    $cliente_id = trim($_POST["cliente_id"]);
    $data_entrega = trim($_POST["data_entrega"]); // Data do formulário
    $hora_entrega = trim($_POST["hora_entrega"]); // Hora do formulário
    $data_hora_entrega = $data_entrega . ' ' . $hora_entrega . ':00'; // Combina para o formato DATETIME
    $endereco_entrega = trim($_POST["endereco_entrega"]);
    $status_entrega = trim($_POST["status_entrega"]);
    $observacoes = trim($_POST["observacoes"]);

    // Validação básica
    if (empty($cliente_id) || empty($data_hora_entrega) || empty($endereco_entrega) || empty($status_entrega)) {
        $message = "Por favor, preencha todos os campos obrigatórios.";
        $message_type = "danger";
    } else {
        if (empty($agendamento_id)) { // Novo Agendamento
            $sql = "INSERT INTO agendamentos_entrega (venda_id, orcamento_id, cliente_id, data_hora_entrega, endereco_entrega, status_entrega, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iiissss", $venda_id, $orcamento_id, $cliente_id, $data_hora_entrega, $endereco_entrega, $status_entrega, $observacoes);
                if ($stmt->execute()) {
                    $message = "Agendamento registrado com sucesso!";
                    $message_type = "success";
                    // Limpa os campos após o cadastro
                    $venda_id = $orcamento_id = $cliente_id = $data_hora_entrega = $endereco_entrega = $status_entrega = $observacoes = "";
                } else {
                    $message = "Erro ao registrar agendamento: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Erro na preparação da query de inserção: " . $conn->error;
                $message_type = "danger";
            }
        } else { // Editar Agendamento Existente
            $sql = "UPDATE agendamentos_entrega SET venda_id = ?, orcamento_id = ?, cliente_id = ?, data_hora_entrega = ?, endereco_entrega = ?, status_entrega = ?, observacoes = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iiissssi", $venda_id, $orcamento_id, $cliente_id, $data_hora_entrega, $endereco_entrega, $status_entrega, $observacoes, $agendamento_id);
                if ($stmt->execute()) {
                    $message = "Agendamento atualizado com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao atualizar agendamento: " . $stmt->error;
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
$agendamento_id_get = $_GET["id"] ?? '';
if (!empty($agendamento_id_get) && empty($message)) {
    $agendamento_id = $agendamento_id_get;
    $title = "Editar Agendamento de Entrega";
    $submit_button_text = "Atualizar Agendamento";

    $sql_agendamento = "SELECT id, venda_id, orcamento_id, cliente_id, data_hora_entrega, endereco_entrega, status_entrega, observacoes FROM agendamentos_entrega WHERE id = ?";
    if ($stmt_agendamento = $conn->prepare($sql_agendamento)) {
        $stmt_agendamento->bind_param("i", $agendamento_id);
        $stmt_agendamento->execute();
        $result_agendamento = $stmt_agendamento->get_result();
        if ($result_agendamento->num_rows == 1) {
            $row_agendamento = $result_agendamento->fetch_assoc();
            $venda_id = $row_agendamento['venda_id'];
            $orcamento_id = $row_agendamento['orcamento_id'];
            $cliente_id = $row_agendamento['cliente_id'];
            $data_hora_entrega = $row_agendamento['data_hora_entrega'];
            $data_entrega = date('Y-m-d', strtotime($data_hora_entrega)); // Formato para input date
            $hora_entrega = date('H:i', strtotime($data_hora_entrega));   // Formato para input time
            $endereco_entrega = $row_agendamento['endereco_entrega'];
            $status_entrega = $row_agendamento['status_entrega'];
            $observacoes = $row_agendamento['observacoes'];
        } else {
            $message = "Agendamento não encontrado.";
            $message_type = "danger";
            $agendamento_id = ""; // Reset para tratar como novo cadastro se ID não encontrado
            $title = "Agendar Nova Entrega";
            $submit_button_text = "Agendar Entrega";
        }
        $stmt_agendamento->close();
    } else {
        $message = "Erro ao buscar agendamento para edição: " . $conn->error;
        $message_type = "danger";
    }
}

// Lógica para pré-selecionar cliente e/ou venda/orçamento se vier de outra página
$from_venda_id = $_GET['from_venda_id'] ?? '';
$from_orcamento_id = $_GET['from_orcamento_id'] ?? '';

if (!empty($from_venda_id) && empty($agendamento_id) && empty($message)) { // Se veio de Vendas e não é edição
    $venda_id = $from_venda_id;
    $sql_get_cliente = "SELECT cliente_id FROM vendas WHERE id = ?";
    if ($stmt_get_cliente = $conn->prepare($sql_get_cliente)) {
        $stmt_get_cliente->bind_param("i", $venda_id);
        $stmt_get_cliente->execute();
        $result_get_cliente = $stmt_get_cliente->get_result();
        if ($row_cliente = $result_get_cliente->fetch_assoc()) {
            $cliente_id = $row_cliente['cliente_id'];
            $message = "Agendando entrega para Venda #" . htmlspecialchars($venda_id) . ".";
            $message_type = "info";
        }
        $stmt_get_cliente->close();
    }
} elseif (!empty($from_orcamento_id) && empty($agendamento_id) && empty($message)) { // Se veio de Orçamentos e não é edição
    $orcamento_id = $from_orcamento_id;
    $sql_get_cliente = "SELECT cliente_id FROM orcamentos WHERE id = ?";
    if ($stmt_get_cliente = $conn->prepare($sql_get_cliente)) {
        $stmt_get_cliente->bind_param("i", $orcamento_id);
        $stmt_get_cliente->execute();
        $result_get_cliente = $stmt_get_cliente->get_result();
        if ($row_cliente = $result_get_cliente->fetch_assoc()) {
            $cliente_id = $row_cliente['cliente_id'];
            $message = "Agendando entrega para Orçamento #" . htmlspecialchars($orcamento_id) . ".";
            $message_type = "info";
        }
        $stmt_get_cliente->close();
    }
}

// Não fechar a conexão aqui pois ainda vamos usar no HTML
// $conn->close();

include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-<?php echo ($agendamento_id ? 'calendar-check' : 'calendar-plus'); ?>"></i>
        <?php echo $title; ?>
    </h1>
    <p class="page-subtitle">
        <?php echo $agendamento_id ? 'Atualize os dados do agendamento' : 'Agende uma nova entrega para seus clientes'; ?>
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
        <i class="fas fa-calendar-alt"></i>
        Dados do Agendamento
    </div>
    <div class="card-body-modern">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="agendamento_id" value="<?php echo htmlspecialchars($agendamento_id); ?>">

            <!-- Informações do Cliente -->
            <div class="row g-4">
                <div class="col-12">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-user me-2"></i>Informações do Cliente
                    </h5>
                </div>

                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente *</label>
                    <select class="form-control" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione um cliente...</option>
                        <?php
                        if ($clientes_options && $clientes_options->num_rows > 0) {
                            $clientes_options->data_seek(0); // Reseta o ponteiro
                            while($cliente = $clientes_options->fetch_assoc()) {
                                $selected = ($cliente['id'] == $cliente_id) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($cliente['id']) . '" ' . $selected . '>' . htmlspecialchars($cliente['nome']) . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>Nenhum cliente cadastrado.</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="venda_id" class="form-label">Vincular à Venda</label>
                    <select class="form-control" id="venda_id" name="venda_id">
                        <option value="">Nenhuma venda</option>
                        <?php
                        if ($vendas_options->num_rows > 0) {
                            $vendas_options->data_seek(0); // Reseta o ponteiro
                            while($venda = $vendas_options->fetch_assoc()) {
                                // Buscar nome do cliente da venda para exibir
                                $venda_cliente_nome = '';
                                $sql_venda_cliente = "SELECT nome FROM clientes WHERE id = ?";
                                $stmt_venda_cliente = $conn->prepare($sql_venda_cliente);
                                $stmt_venda_cliente->bind_param("i", $venda['cliente_id']);
                                $stmt_venda_cliente->execute();
                                $res_venda_cliente = $stmt_venda_cliente->get_result();
                                if ($row_venda_cliente = $res_venda_cliente->fetch_assoc()) {
                                    $venda_cliente_nome = " (" . $row_venda_cliente['nome'] . ")";
                                }
                                $stmt_venda_cliente->close();


                                $selected = ($venda['id'] == $venda_id) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($venda['id']) . '" ' . $selected . '>Venda #' . htmlspecialchars($venda['id']) . $venda_cliente_nome . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="orcamento_id" class="form-label">Vincular ao Orçamento</label>
                    <select class="form-control" id="orcamento_id" name="orcamento_id">
                        <option value="">Nenhum orçamento</option>
                        <?php
                        if ($orcamentos_options->num_rows > 0) {
                            $orcamentos_options->data_seek(0); // Reseta o ponteiro
                             while($orcamento = $orcamentos_options->fetch_assoc()) {
                                // Buscar nome do cliente do orçamento para exibir
                                $orcamento_cliente_nome = '';
                                $sql_orcamento_cliente = "SELECT nome FROM clientes WHERE id = ?";
                                $stmt_orcamento_cliente = $conn->prepare($sql_orcamento_cliente);
                                $stmt_orcamento_cliente->bind_param("i", $orcamento['cliente_id']);
                                $stmt_orcamento_cliente->execute();
                                $res_orcamento_cliente = $stmt_orcamento_cliente->get_result();
                                if ($row_orcamento_cliente = $res_orcamento_cliente->fetch_assoc()) {
                                    $orcamento_cliente_nome = " (" . $row_orcamento_cliente['nome'] . ")";
                                }
                                $stmt_orcamento_cliente->close();

                                $selected = ($orcamento['id'] == $orcamento_id) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($orcamento['id']) . '" ' . $selected . '>Orçamento #' . htmlspecialchars($orcamento['id']) . $orcamento_cliente_nome . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <!-- Data e Hora da Entrega -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-calendar-check me-2"></i>Data e Hora da Entrega
                    </h5>
                </div>

                <div class="col-md-4">
                    <label for="data_entrega" class="form-label">Data da Entrega *</label>
                    <input type="date" class="form-control" id="data_entrega" name="data_entrega" value="<?php echo htmlspecialchars($data_entrega); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="hora_entrega" class="form-label">Hora da Entrega *</label>
                    <input type="time" class="form-control" id="hora_entrega" name="hora_entrega" value="<?php echo htmlspecialchars($hora_entrega); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="status_entrega" class="form-label">Status da Entrega *</label>
                    <select class="form-control" id="status_entrega" name="status_entrega" required>
                        <option value="agendado" <?php echo ($status_entrega == 'agendado' ? 'selected' : ''); ?>>Agendado</option>
                        <option value="em_rota" <?php echo ($status_entrega == 'em_rota' ? 'selected' : ''); ?>>Em Rota</option>
                        <option value="entregue" <?php echo ($status_entrega == 'entregue' ? 'selected' : ''); ?>>Entregue</option>
                        <option value="cancelado" <?php echo ($status_entrega == 'cancelado' ? 'selected' : ''); ?>>Cancelado</option>
                    </select>
                </div>

                <!-- Endereço e Observações -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-map-marker-alt me-2"></i>Endereço e Observações
                    </h5>
                </div>

                <div class="col-12">
                    <label for="endereco_entrega" class="form-label">Endereço de Entrega *</label>
                    <input type="text" class="form-control" id="endereco_entrega" name="endereco_entrega"
                           value="<?php echo htmlspecialchars($endereco_entrega); ?>" required
                           placeholder="Rua, Número, Bairro, Cidade, Estado, CEP">
                </div>

                <div class="col-12">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
                              placeholder="Informações adicionais sobre a entrega..."><?php echo htmlspecialchars($observacoes); ?></textarea>
                </div>

                <!-- Botões -->
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="agendamentos.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-<?php echo ($agendamento_id ? 'save' : 'check'); ?> me-2"></i><?php echo $submit_button_text; ?>
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