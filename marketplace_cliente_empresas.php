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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_empresas') {
        $cliente_id = intval($_POST['cliente_id']);
        $empresas_selecionadas = $_POST['empresas'] ?? [];
        
        try {
            $conn->begin_transaction();
            
            // Remover todas as associações existentes do cliente
            $stmt_delete = $conn->prepare("DELETE FROM marketplace_cliente_empresas WHERE cliente_id = ?");
            $stmt_delete->bind_param("i", $cliente_id);
            $stmt_delete->execute();
            
            // Inserir novas associações
            if (!empty($empresas_selecionadas)) {
                $stmt_insert = $conn->prepare("INSERT INTO marketplace_cliente_empresas (cliente_id, empresa_id) VALUES (?, ?)");
                foreach ($empresas_selecionadas as $empresa_id) {
                    $stmt_insert->bind_param("ii", $cliente_id, $empresa_id);
                    $stmt_insert->execute();
                }
            }
            
            $conn->commit();
            $message = "Empresas atualizadas com sucesso para o cliente!";
            $message_type = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Erro ao atualizar empresas: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Buscar clientes
$sql_clientes = "SELECT c.id, c.nome, c.email, 
                        COUNT(mce.empresa_id) as empresas_associadas,
                        GROUP_CONCAT(e.nome_empresa SEPARATOR ', ') as nomes_empresas
                 FROM clientes c
                 LEFT JOIN marketplace_cliente_empresas mce ON c.id = mce.cliente_id AND mce.ativo = 1
                 LEFT JOIN empresas_representadas e ON mce.empresa_id = e.id
                 GROUP BY c.id, c.nome, c.email
                 ORDER BY c.nome ASC";
$result_clientes = $conn->query($sql_clientes);

// Buscar todas as empresas
$sql_empresas = "SELECT id, nome_empresa FROM empresas_representadas ORDER BY nome_empresa ASC";
$result_empresas = $conn->query($sql_empresas);

include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-users-cog"></i>
        Marketplace - Controle de Acesso por Cliente
    </h1>
    <p class="page-subtitle">
        Configure quais empresas cada cliente pode visualizar no marketplace
    </p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Lista de Clientes -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-list"></i>
        Clientes e Empresas Associadas
    </div>
    <div class="card-body-modern">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Empresas Associadas</th>
                        <th>Qtd. Empresas</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_clientes && $result_clientes->num_rows > 0): ?>
                        <?php while ($cliente = $result_clientes->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($cliente['nome']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                <td>
                                    <?php if ($cliente['empresas_associadas'] > 0): ?>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($cliente['nomes_empresas']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Todas as empresas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo $cliente['empresas_associadas']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#empresasModal" 
                                            data-cliente-id="<?php echo $cliente['id']; ?>"
                                            data-cliente-nome="<?php echo htmlspecialchars($cliente['nome']); ?>">
                                        <i class="fas fa-edit"></i> Configurar
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum cliente encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para configurar empresas -->
<div class="modal fade" id="empresasModal" tabindex="-1" aria-labelledby="empresasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="empresasModalLabel">
                        <i class="fas fa-building"></i>
                        Configurar Empresas para <span id="clienteNome"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_empresas">
                    <input type="hidden" name="cliente_id" id="clienteId">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Importante:</strong> Se nenhuma empresa for selecionada, o cliente poderá ver produtos de todas as empresas no marketplace.
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">Selecione as empresas que este cliente pode visualizar:</label>
                            <div class="row" id="empresasCheckboxes">
                                <?php if ($result_empresas && $result_empresas->num_rows > 0): ?>
                                    <?php $result_empresas->data_seek(0); ?>
                                    <?php while ($empresa = $result_empresas->fetch_assoc()): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="empresas[]" 
                                                       value="<?php echo $empresa['id']; ?>" 
                                                       id="empresa_<?php echo $empresa['id']; ?>">
                                                <label class="form-check-label" for="empresa_<?php echo $empresa['id']; ?>">
                                                    <?php echo htmlspecialchars($empresa['nome_empresa']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Salvar Configurações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once 'includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const empresasModal = document.getElementById('empresasModal');
    
    empresasModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const clienteId = button.getAttribute('data-cliente-id');
        const clienteNome = button.getAttribute('data-cliente-nome');
        
        // Atualizar modal
        document.getElementById('clienteId').value = clienteId;
        document.getElementById('clienteNome').textContent = clienteNome;
        
        // Limpar checkboxes
        const checkboxes = document.querySelectorAll('input[name="empresas[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = false);
        
        // Carregar empresas associadas ao cliente
        fetch(`get_cliente_empresas.php?cliente_id=${clienteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.empresas.forEach(empresaId => {
                        const checkbox = document.getElementById(`empresa_${empresaId}`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                }
            })
            .catch(error => console.error('Erro ao carregar empresas:', error));
    });
});
</script>
