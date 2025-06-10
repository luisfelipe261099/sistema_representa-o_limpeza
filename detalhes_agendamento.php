<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$agendamento_id = $_GET['id'] ?? null;
$agendamento = null;
$message = '';
$message_type = '';

if ($agendamento_id) {
    // Busca os dados do agendamento
    $sql_agendamento = "SELECT ae.id, c.nome AS nome_cliente, c.cpf_cnpj, c.telefone, c.email,
                               ae.venda_id, ae.orcamento_id, ae.data_hora_entrega, ae.endereco_entrega, ae.status_entrega, ae.observacoes
                        FROM agendamentos_entrega ae
                        JOIN clientes c ON ae.cliente_id = c.id
                        WHERE ae.id = ?";
    if ($stmt_agendamento = $conn->prepare($sql_agendamento)) {
        $stmt_agendamento->bind_param("i", $agendamento_id);
        $stmt_agendamento->execute();
        $result_agendamento = $stmt_agendamento->get_result();
        if ($result_agendamento->num_rows == 1) {
            $agendamento = $result_agendamento->fetch_assoc();
        } else {
            $message = "Agendamento não encontrado.";
            $message_type = "danger";
        }
        $stmt_agendamento->close();
    } else {
        $message = "Erro ao buscar detalhes do agendamento: " . $conn->error;
        $message_type = "danger";
    }
} else {
    $message = "ID do agendamento não especificado.";
    $message_type = "danger";
}

$conn->close();

include_once 'includes/header.php';
?>

<h2 class="mb-4"><i class="fas fa-calendar-check me-2"></i> Detalhes do Agendamento #<?php echo htmlspecialchars($agendamento_id); ?></h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($agendamento):
    // Define a classe de badge para o status
    $status_class = '';
    switch ($agendamento['status_entrega']) {
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
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5>Informações do Agendamento</h5>
            <div>
                <a href="agendar_entrega.php?id=<?php echo htmlspecialchars($agendamento['id']); ?>" class="btn btn-primary btn-sm me-2" title="Editar Agendamento">
                    <i class="fas fa-edit me-1"></i> Editar Agendamento
                </a>
                <a href="agendamentos.php" class="btn btn-secondary btn-sm" title="Voltar para Agendamentos">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Cliente:</strong> <?php echo htmlspecialchars($agendamento['nome_cliente']); ?> (<?php echo htmlspecialchars($agendamento['cpf_cnpj']); ?>)<br>
                    <strong>Contato:</strong> <?php echo htmlspecialchars($agendamento['telefone']); ?> | <?php echo htmlspecialchars($agendamento['email']); ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <strong>Data/Hora da Entrega:</strong> <?php echo date('d/m/Y H:i', strtotime($agendamento['data_hora_entrega'])); ?><br>
                    <?php if ($agendamento['venda_id']): ?>
                        <strong>Vínculo:</strong> <a href="detalhes_venda.php?id=<?php echo htmlspecialchars($agendamento['venda_id']); ?>" class="text-decoration-none">Venda #<?php echo htmlspecialchars($agendamento['venda_id']); ?></a><br>
                    <?php elseif ($agendamento['orcamento_id']): ?>
                        <strong>Vínculo:</strong> <a href="detalhes_orcamento.php?id=<?php echo htmlspecialchars($agendamento['orcamento_id']); ?>" class="text-decoration-none">Orçamento #<?php echo htmlspecialchars($agendamento['orcamento_id']); ?></a><br>
                    <?php else: ?>
                        <strong>Vínculo:</strong> <span class="text-muted">Nenhum</span><br>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <strong>Endereço de Entrega:</strong> <?php echo htmlspecialchars($agendamento['endereco_entrega']); ?>
                </div>
                <div class="col-md-4 text-md-end">
                    <strong>Status:</strong> <span class="badge <?php echo $status_class; ?> fs-6"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $agendamento['status_entrega']))); ?></span>
                </div>
            </div>
            <?php if (!empty($agendamento['observacoes'])): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <strong>Observações:</strong><br>
                    <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($agendamento['observacoes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>