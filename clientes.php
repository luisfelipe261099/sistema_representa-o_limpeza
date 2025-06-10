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

// --- Lógica para Exclusão de Cliente ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $cliente_id_to_delete = $_GET['id'];

    // Prepara a query para exclusão
    $sql_delete = "DELETE FROM clientes WHERE id = ?";

    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $cliente_id_to_delete);
        if ($stmt_delete->execute()) {
            $message = "Cliente excluído com sucesso!";
            $message_type = "success";
        } else {
            $message = "Erro ao excluir o cliente. Pode haver vendas ou orçamentos associados a este cliente: " . $stmt_delete->error;
            $message_type = "danger";
        }
        $stmt_delete->close();
    } else {
        $message = "Erro na preparação da query de exclusão: " . $conn->error;
        $message_type = "danger";
    }
}

// --- Lógica para buscar todos os clientes ---
$sql_select_clientes = "SELECT id, nome, tipo_pessoa, cpf_cnpj, email, telefone, endereco, cidade, estado, cep FROM clientes ORDER BY nome ASC";
$result_clientes = $conn->query($sql_select_clientes);

// Estatísticas dos clientes
$total_clientes = $result_clientes->num_rows;
$clientes_pf = 0;
$clientes_pj = 0;
$cidades = [];

$result_clientes->data_seek(0);
while($row = $result_clientes->fetch_assoc()) {
    if ($row['tipo_pessoa'] == 'fisica') {
        $clientes_pf++;
    } else {
        $clientes_pj++;
    }

    if (!empty($row['cidade']) && !in_array($row['cidade'], $cidades)) {
        $cidades[] = $row['cidade'];
    }
}

$conn->close();

include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-users"></i>
        Gerenciamento de Clientes
    </h1>
    <p class="page-subtitle">Gerencie sua base de clientes</p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stats-card primary fade-in-up">
            <div class="stats-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-value"><?php echo $total_clientes; ?></div>
            <div class="stats-label">Total de Clientes</div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stats-card success fade-in-up">
            <div class="stats-icon success">
                <i class="fas fa-user"></i>
            </div>
            <div class="stats-value"><?php echo $clientes_pf; ?></div>
            <div class="stats-label">Pessoa Física</div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stats-card info fade-in-up">
            <div class="stats-icon info">
                <i class="fas fa-building"></i>
            </div>
            <div class="stats-value"><?php echo $clientes_pj; ?></div>
            <div class="stats-label">Pessoa Jurídica</div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stats-card warning fade-in-up">
            <div class="stats-icon warning">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stats-value"><?php echo count($cidades); ?></div>
            <div class="stats-label">Cidades</div>
        </div>
    </div>
</div>

<!-- Action Bar -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="modern-card fade-in-up">
            <div class="card-body-modern">
                <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-center">
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="cadastro_cliente.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i> Novo Cliente
                        </a>
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Imprimir
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportClients()">
                            <i class="fas fa-download me-2"></i> Exportar
                        </button>
                    </div>
                    <div class="d-flex gap-2 flex-grow-1 flex-md-grow-0">
                        <div class="input-group" style="max-width: 300px;">
                            <input type="text" class="form-control" placeholder="Buscar clientes..." id="searchInput">
                            <button class="btn btn-outline-primary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clients Table -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-list"></i>
        Lista de Clientes
        <div class="ms-auto">
            <span class="badge bg-primary"><?php echo $total_clientes; ?> clientes</span>
        </div>
    </div>
    <div class="card-body-modern">
        <?php if ($result_clientes->num_rows > 0): ?>
            <!-- Desktop Table -->
            <div class="table-modern d-none d-md-block">
                <table class="table table-hover mb-0" id="clientesTable">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Documento</th>
                            <th>Contato</th>
                            <th>Localização</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result_clientes->data_seek(0);
                        while($row = $result_clientes->fetch_assoc()) {
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="client-avatar me-3">
                                            <?php echo strtoupper(substr($row['nome'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($row['nome']); ?></div>
                                            <small class="text-muted">#<?php echo $row['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $row['tipo_pessoa'] == 'fisica' ? 'status-success' : 'status-info'; ?>">
                                        <?php echo $row['tipo_pessoa'] == 'fisica' ? 'PF' : 'PJ'; ?>
                                    </span>
                                </td>
                                <td>
                                    <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($row['cpf_cnpj']); ?></code>
                                </td>
                                <td>
                                    <div>
                                        <?php if (!empty($row['email'])): ?>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($row['email']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['telefone'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['telefone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php if (!empty($row['cidade'])): ?>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($row['cidade']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['estado'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['estado']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="cadastro_cliente.php?id=<?php echo $row['id']; ?>"
                                           class="btn btn-outline-primary btn-sm" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-outline-danger btn-sm"
                                                onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="d-block d-md-none">
                <?php
                $result_clientes->data_seek(0);
                while($row = $result_clientes->fetch_assoc()) {
                    ?>
                    <div class="mobile-client-card mb-3">
                        <div class="d-flex align-items-start mb-3">
                            <div class="client-avatar me-3">
                                <?php echo strtoupper(substr($row['nome'], 0, 1)); ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($row['nome']); ?></h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">#<?php echo $row['id']; ?></small>
                                    <span class="status-badge <?php echo $row['tipo_pessoa'] == 'fisica' ? 'status-success' : 'status-info'; ?>">
                                        <?php echo $row['tipo_pessoa'] == 'fisica' ? 'PF' : 'PJ'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <div class="mobile-info-item">
                                    <small class="text-muted">Documento</small>
                                    <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($row['cpf_cnpj']); ?></code>
                                </div>
                            </div>
                            <?php if (!empty($row['email'])): ?>
                            <div class="col-6">
                                <div class="mobile-info-item">
                                    <small class="text-muted">Email</small>
                                    <div><?php echo htmlspecialchars($row['email']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($row['telefone'])): ?>
                            <div class="col-6">
                                <div class="mobile-info-item">
                                    <small class="text-muted">Telefone</small>
                                    <div><?php echo htmlspecialchars($row['telefone']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($row['cidade'])): ?>
                            <div class="col-6">
                                <div class="mobile-info-item">
                                    <small class="text-muted">Cidade</small>
                                    <div><?php echo htmlspecialchars($row['cidade']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($row['estado'])): ?>
                            <div class="col-6">
                                <div class="mobile-info-item">
                                    <small class="text-muted">Estado</small>
                                    <div><?php echo htmlspecialchars($row['estado']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="cadastro_cliente.php?id=<?php echo $row['id']; ?>"
                               class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="fas fa-edit me-1"></i> Editar
                            </a>
                            <button class="btn btn-outline-danger btn-sm"
                                    onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="stats-icon primary mx-auto mb-3">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h5 class="text-muted mb-2">Nenhum cliente cadastrado</h5>
                <p class="text-muted">Comece adicionando seu primeiro cliente ao sistema.</p>
                <a href="cadastro_cliente.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i> Adicionar Primeiro Cliente
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Tem certeza que deseja excluir este cliente? Isso pode afetar vendas e orçamentos relacionados.')) {
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;

        // Show loading
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        // Simulate loading for better UX
        setTimeout(() => {
            window.location.href = `clientes.php?action=delete&id=${id}`;
        }, 500);
    }
}

function searchClients() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();

    // Search in desktop table
    const table = document.getElementById('clientesTable');
    if (table) {
        const rows = table.getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            let found = false;

            for (let j = 0; j < cells.length - 1; j++) {
                if (cells[j].textContent.toLowerCase().includes(filter)) {
                    found = true;
                    break;
                }
            }

            rows[i].style.display = found ? '' : 'none';
        }
    }

    // Search in mobile cards
    const mobileCards = document.querySelectorAll('.mobile-client-card');
    mobileCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(filter) ? '' : 'none';
    });
}

function exportClients() {
    // Simple CSV export
    const table = document.getElementById('clientesTable');
    if (!table) return;

    let csv = 'ID,Nome,Tipo,Documento,Email,Telefone,Cidade,Estado\n';

    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            const data = [
                cells[0].querySelector('small').textContent.replace('#', ''),
                cells[0].querySelector('.fw-semibold').textContent.trim(),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].querySelector('.fw-semibold') ? cells[3].querySelector('.fw-semibold').textContent.trim() : '',
                cells[3].querySelector('small') ? cells[3].querySelector('small').textContent.trim() : '',
                cells[4].querySelector('.fw-semibold') ? cells[4].querySelector('.fw-semibold').textContent.trim() : '',
                cells[4].querySelector('small') ? cells[4].querySelector('small').textContent.trim() : ''
            ];
            csv += data.map(field => `"${field}"`).join(',') + '\n';
        }
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'clientes.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Real-time search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', searchClients);

        // Clear search on escape
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                searchClients();
            }
        });
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>