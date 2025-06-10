<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$message = '';
$message_type = '';

// --- LÓGICA DE AÇÕES (POST E GET) ---

// AÇÃO: MUDAR STATUS DA VENDA (VIA MODAL)
if (isset($_POST['action']) && $_POST['action'] == 'change_status') {
    $venda_id = intval($_POST['venda_id']);
    $novo_status = $_POST['novo_status'];

    $conn->begin_transaction();
    try {
        $sql_venda_atual = "SELECT status_venda FROM vendas WHERE id = ?";
        $stmt_venda_atual = $conn->prepare($sql_venda_atual);
        $stmt_venda_atual->bind_param("i", $venda_id);
        $stmt_venda_atual->execute();
        $venda_atual = $stmt_venda_atual->get_result()->fetch_assoc();
        $status_antigo = $venda_atual['status_venda'];
        $stmt_venda_atual->close();

        if ($status_antigo != $novo_status) {
            $sql_itens = "SELECT produto_id, quantidade FROM itens_venda WHERE venda_id = ?";
            $stmt_itens = $conn->prepare($sql_itens);
            $stmt_itens->bind_param("i", $venda_id);
            $stmt_itens->execute();
            $result_itens = $stmt_itens->get_result();
            $itens_da_venda = $result_itens->fetch_all(MYSQLI_ASSOC);
            $stmt_itens->close();
            
            // Reverte ou debita estoque
            if ($status_antigo == 'cancelada' && $novo_status != 'cancelada') { // Saindo do status cancelado
                foreach ($itens_da_venda as $item) {
                    $conn->query("UPDATE produtos SET quantidade_estoque = quantidade_estoque - {$item['quantidade']} WHERE id = {$item['produto_id']}");
                }
            } elseif ($status_antigo != 'cancelada' && $novo_status == 'cancelada') { // Entrando no status cancelado
                 foreach ($itens_da_venda as $item) {
                    $conn->query("UPDATE produtos SET quantidade_estoque = quantidade_estoque + {$item['quantidade']} WHERE id = {$item['produto_id']}");
                }
            }

            // Atualiza transação financeira
            if ($novo_status == 'concluida') {
                $venda_data = $conn->query("SELECT valor_total FROM vendas WHERE id = $venda_id")->fetch_assoc();
                $check_transacao = $conn->query("SELECT id FROM transacoes_financeiras WHERE referencia_id = $venda_id AND tabela_referencia = 'vendas'");
                if ($check_transacao->num_rows == 0) {
                     $conn->query("INSERT INTO transacoes_financeiras (tipo, valor, descricao, categoria, referencia_id, tabela_referencia, data_transacao) VALUES ('entrada', {$venda_data['valor_total']}, 'Receita da Venda #{$venda_id}', 'Vendas', {$venda_id}, 'vendas', NOW())");
                } else {
                     $conn->query("UPDATE transacoes_financeiras SET valor = {$venda_data['valor_total']} WHERE referencia_id = $venda_id AND tabela_referencia = 'vendas'");
                }
            } else {
                $conn->query("DELETE FROM transacoes_financeiras WHERE referencia_id = $venda_id AND tabela_referencia = 'vendas'");
            }

            // Atualiza status da venda
            $sql_update = "UPDATE vendas SET status_venda = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $novo_status, $venda_id);
            $stmt_update->execute();
            $stmt_update->close();
        }

        $conn->commit();
        $message = "Status da venda #{$venda_id} alterado com sucesso!";
        $message_type = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Erro ao alterar o status da venda: " . $e->getMessage();
        $message_type = 'danger';
    }
}


// AÇÃO: EXCLUIR VENDA (GET)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $venda_id_to_delete = intval($_GET['id']);

    $conn->begin_transaction();
    try {
        // 1. Reverter o estoque dos produtos
        $sql_itens = "SELECT produto_id, quantidade FROM itens_venda WHERE venda_id = ?";
        $stmt_itens = $conn->prepare($sql_itens);
        $stmt_itens->bind_param("i", $venda_id_to_delete);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        while ($item = $result_itens->fetch_assoc()) {
            $conn->query("UPDATE produtos SET quantidade_estoque = quantidade_estoque + {$item['quantidade']} WHERE id = {$item['produto_id']}");
        }
        $stmt_itens->close();

        // 2. Excluir a transação financeira associada (se houver)
        $stmt_trans = $conn->prepare("DELETE FROM transacoes_financeiras WHERE referencia_id = ? AND tabela_referencia = 'vendas'");
        $stmt_trans->bind_param("i", $venda_id_to_delete);
        $stmt_trans->execute();
        $stmt_trans->close();
        
        // 3. Excluir os itens da venda
        $stmt_itens_del = $conn->prepare("DELETE FROM itens_venda WHERE venda_id = ?");
        $stmt_itens_del->bind_param("i", $venda_id_to_delete);
        $stmt_itens_del->execute();
        $stmt_itens_del->close();

        // 4. CORREÇÃO: Excluir agendamentos de entrega associados
        $stmt_agend = $conn->prepare("DELETE FROM agendamentos_entrega WHERE venda_id = ?");
        $stmt_agend->bind_param("i", $venda_id_to_delete);
        $stmt_agend->execute();
        $stmt_agend->close();

        // 5. Excluir a venda principal
        $stmt_venda = $conn->prepare("DELETE FROM vendas WHERE id = ?");
        $stmt_venda->bind_param("i", $venda_id_to_delete);
        $stmt_venda->execute();
        $stmt_venda->close();

        $conn->commit();
        $message = "Venda #{$venda_id_to_delete} e todos os seus dados (itens, agendamentos, etc) foram excluídos. O estoque foi revertido.";
        $message_type = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Erro ao excluir a venda: " . $e->getMessage();
        $message_type = "danger";
    }
}

// --- Lógica para buscar todas as vendas ---
$sql_select_vendas = "SELECT v.id, c.nome AS nome_cliente, v.data_venda, v.valor_total, v.forma_pagamento, v.status_venda
                      FROM vendas v
                      LEFT JOIN clientes c ON v.cliente_id = c.id
                      ORDER BY v.data_venda DESC";
$result_vendas = $conn->query($sql_select_vendas);

// Estatísticas das vendas
$total_vendas = $result_vendas->num_rows;
$vendas_concluidas = $vendas_pendentes = $vendas_canceladas = 0;
$valor_total_vendas = $valor_vendas_mes = 0;

if($total_vendas > 0) {
    $vendas_data = $result_vendas->fetch_all(MYSQLI_ASSOC);
    foreach($vendas_data as $row) {
        if ($row['status_venda'] == 'concluida') {
            $vendas_concluidas++;
            $valor_total_vendas += $row['valor_total'];
            if (date('Y-m', strtotime($row['data_venda'])) == date('Y-m')) {
                $valor_vendas_mes += $row['valor_total'];
            }
        } elseif ($row['status_venda'] == 'pendente') {
            $vendas_pendentes++;
        } elseif ($row['status_venda'] == 'cancelada') {
            $vendas_canceladas++;
        }
    }
} else {
    $vendas_data = [];
}

$conn->close();

include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title"><i class="fas fa-shopping-cart"></i> Gerenciamento de Vendas</h1>
    <p class="page-subtitle">Visualize, edite e controle todas as suas vendas registradas.</p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3"><div class="stats-card primary fade-in-up"><div class="stats-icon primary"><i class="fas fa-receipt"></i></div><div class="stats-value"><?php echo $total_vendas; ?></div><div class="stats-label">Total de Vendas</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card success fade-in-up"><div class="stats-icon success"><i class="fas fa-check-circle"></i></div><div class="stats-value"><?php echo $vendas_concluidas; ?></div><div class="stats-label">Concluídas</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card warning fade-in-up"><div class="stats-icon warning"><i class="fas fa-clock"></i></div><div class="stats-value"><?php echo $vendas_pendentes; ?></div><div class="stats-label">Pendentes</div></div></div>
    <div class="col-6 col-lg-3"><div class="stats-card danger fade-in-up"><div class="stats-icon danger"><i class="fas fa-times-circle"></i></div><div class="stats-value"><?php echo $vendas_canceladas; ?></div><div class="stats-label">Canceladas</div></div></div>
</div>

<div class="modern-card fade-in-up">
    <div class="card-header-modern d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Lista de Vendas</span>
        <a href="registrar_venda.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Nova Venda</a>
    </div>
    <div class="card-body-modern">
        <?php if (!empty($vendas_data)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="vendasTable">
                    <thead>
                        <tr>
                            <th>Venda</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($vendas_data as $row): ?>
                            <?php
                            $status_class = '';
                            switch ($row['status_venda']) {
                                case 'concluida': $status_class = 'status-success'; break;
                                case 'pendente': $status_class = 'status-warning'; break;
                                case 'cancelada': $status_class = 'status-danger'; break;
                                default: $status_class = 'status-info';
                            }
                            ?>
                            <tr>
                                <td><span class="fw-semibold">#<?php echo $row['id']; ?></span></td>
                                <td><?php echo htmlspecialchars($row['nome_cliente'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['data_venda'])); ?></td>
                                <td><span class="fw-bold text-success">R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></span></td>
                                <td><?php echo htmlspecialchars(str_replace('_', ' ',$row['forma_pagamento'])); ?></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($row['status_venda'])); ?></span></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton-<?php echo $row['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton-<?php echo $row['id']; ?>">
                                            <li><a class="dropdown-item" href="detalhes_venda.php?id=<?php echo $row['id']; ?>"><i class="fas fa-eye me-2"></i>Ver Detalhes</a></li>
                                            <li><a class="dropdown-item" href="registrar_venda.php?id=<?php echo $row['id']; ?>"><i class="fas fa-edit me-2"></i>Editar</a></li>
                                            <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#statusModal" data-id="<?php echo $row['id']; ?>" data-status="<?php echo $row['status_venda']; ?>"><i class="fas fa-sync-alt me-2"></i>Mudar Status</button></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="vendas.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir esta venda? O estoque será revertido. Esta ação não pode ser desfeita.');"><i class="fas fa-trash-alt me-2"></i>Excluir</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5"><div class="stats-icon primary mx-auto mb-3"><i class="fas fa-shopping-cart"></i></div><h5 class="text-muted mb-2">Nenhuma venda registrada</h5><p class="text-muted">Comece registrando sua primeira venda no sistema.</p><a href="registrar_venda.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Registrar Primeira Venda</a></div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="vendas.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Alterar Status da Venda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="venda_id" id="modal_venda_id">
                    
                    <p>Selecione o novo status para a venda <strong id="modal_venda_id_display">#</strong>.</p>
                    <div class="mb-3">
                        <label for="novo_status" class="form-label">Novo Status</label>
                        <select name="novo_status" id="modal_novo_status" class="form-select" required>
                            <option value="pendente">Pendente</option>
                            <option value="concluida">Concluída</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                     <div class="alert alert-warning small">
                        <strong>Atenção:</strong> Alterar o status para 'Cancelada' reverterá o estoque. Alterar para 'Concluída' registrará a entrada financeira.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alteração</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
        statusModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const vendaId = button.getAttribute('data-id');
            const currentStatus = button.getAttribute('data-status');

            const modalVendaIdInput = statusModal.querySelector('#modal_venda_id');
            const modalVendaIdDisplay = statusModal.querySelector('#modal_venda_id_display');
            const modalStatusSelect = statusModal.querySelector('#modal_novo_status');

            modalVendaIdInput.value = vendaId;
            modalVendaIdDisplay.textContent = '#' + vendaId;
            modalStatusSelect.value = currentStatus;
        });
    }
});
</script>