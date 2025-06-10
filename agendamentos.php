<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';
// --- Lógica para Alterar Status do Agendamento via Modal ---
if (isset($_POST['alterar_status'])) {
    $agendamento_id_update = trim($_POST['agendamento_id_status']);
    $novo_status = trim($_POST['novo_status_agendamento']);

    if (!empty($agendamento_id_update) && !empty($novo_status)) {
        $sql_update_status = "UPDATE agendamentos_entrega SET status_entrega = ? WHERE id = ?";
        if ($stmt_update_status = $conn->prepare($sql_update_status)) {
            $stmt_update_status->bind_param("si", $novo_status, $agendamento_id_update);
            if ($stmt_update_status->execute()) {
                $message = "Status do agendamento #" . htmlspecialchars($agendamento_id_update) . " atualizado para " . htmlspecialchars(ucfirst(str_replace('_', ' ', $novo_status))) . " com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao atualizar status do agendamento: " . $stmt_update_status->error;
                $message_type = "danger";
            }
            $stmt_update_status->close();
        } else {
            $message = "Erro na preparação da query de atualização de status: " . $conn->error;
            $message_type = "danger";
        }
    } else {
        $message = "ID do agendamento ou novo status inválido.";
        $message_type = "danger";
    }
}


$message = '';
$message_type = '';

// Lógica para buscar todos os agendamentos
$sql_select_agendamentos = "SELECT ae.id, c.nome AS nome_cliente, ae.data_hora_entrega, ae.endereco_entrega, ae.status_entrega, ae.venda_id, ae.orcamento_id
                            FROM agendamentos_entrega ae
                            JOIN clientes c ON ae.cliente_id = c.id
                            ORDER BY ae.data_hora_entrega DESC";
$result_agendamentos = $conn->query($sql_select_agendamentos);

$conn->close();

include_once 'includes/header.php';
?>

<h2 class="mb-4"><i class="fas fa-calendar-alt me-2"></i> Gerenciamento de Agendamentos</h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>Lista de Agendamentos</span>
        <a href="agendar_entrega.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Agendar Nova Entrega
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID Agendamento</th>
                        <th>Cliente</th>
                        <th>Data/Hora Entrega</th>
                        <th>Endereço</th>
                        <th>Venda/Orçamento</th>
                        <th>Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_agendamentos->num_rows > 0) {
                        while($row = $result_agendamentos->fetch_assoc()) {
                            // Define a classe de badge para o status
                            $status_class = '';
                            switch ($row['status_entrega']) {
                                case 'agendado':
                                    $status_class = 'bg-primary';
                                    break;
                                case 'em_rota':
                                    $status_class = 'bg-info';
                                    break;
                                case 'entregue':
                                    $status_class = 'bg-success';
                                    break;
                                case 'cancelado':
                                    $status_class = 'bg-danger';
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                            }

                            $referencia = 'N/A';
                            if ($row['venda_id']) {
                                $referencia = '<a href="detalhes_venda.php?id=' . htmlspecialchars($row['venda_id']) . '" class="text-decoration-none">Venda #' . htmlspecialchars($row['venda_id']) . '</a>';
                            } elseif ($row['orcamento_id']) {
                                $referencia = '<a href="detalhes_orcamento.php?id=' . htmlspecialchars($row['orcamento_id']) . '" class="text-decoration-none">Orçamento #' . htmlspecialchars($row['orcamento_id']) . '</a>';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['nome_cliente']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['data_hora_entrega'])); ?></td>
                                <td><?php echo htmlspecialchars($row['endereco_entrega']); ?></td>
                                <td><?php echo $referencia; ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status_entrega']))); ?></span></td>
                                <td class="text-center">
                                    <a href="detalhes_agendamento.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm me-1" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="agendar_entrega.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm me-1" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-warning btn-sm" title="Alterar Status" data-bs-toggle="modal" data-bs-target="#modalStatusAgendamento" data-id="<?php echo $row['id']; ?>" data-current-status="<?php echo $row['status_entrega']; ?>">
                                        <i class="fas fa-sync-alt"></i> Status
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center">Nenhum agendamento de entrega registrado ainda.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalStatusAgendamento" tabindex="-1" aria-labelledby="modalStatusAgendamentoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalStatusAgendamentoLabel">Alterar Status do Agendamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="agendamentos.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="agendamento_id_status" id="agendamento_id_status">
                    <div class="mb-3">
                        <label for="novo_status_agendamento" class="form-label">Novo Status:</label>
                        <select class="form-select" id="novo_status_agendamento" name="novo_status_agendamento" required>
                            <option value="agendado">Agendado</option>
                            <option value="em_rota">Em Rota</option>
                            <option value="entregue">Entregue</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="alterar_status" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
    // Script para preencher o modal com os dados do agendamento
    var modalStatusAgendamento = document.getElementById('modalStatusAgendamento');
    modalStatusAgendamento.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Botão que acionou o modal
        var agendamentoId = button.getAttribute('data-id');
        var currentStatus = button.getAttribute('data-current-status');

        var modalIdInput = modalStatusAgendamento.querySelector('#agendamento_id_status');
        var modalStatusSelect = modalStatusAgendamento.querySelector('#novo_status_agendamento');

        modalIdInput.value = agendamentoId;
        modalStatusSelect.value = currentStatus; // Pré-seleciona o status atual
    });
</script>