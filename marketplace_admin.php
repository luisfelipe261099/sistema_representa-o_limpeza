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
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'gerar_link':
            $cliente_id = (int)$_POST['cliente_id'];
            $data_expiracao = !empty($_POST['data_expiracao']) ? $_POST['data_expiracao'] : null;
            
            // Gerar token único
            $token = bin2hex(random_bytes(32));
            
            // Verificar se já existe link ativo para este cliente
            $sql_check = "SELECT id FROM marketplace_links WHERE cliente_id = ? AND ativo = 1";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("i", $cliente_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                $message = "Cliente já possui um link ativo. Desative o link atual antes de gerar um novo.";
                $message_type = "warning";
            } else {
                // Inserir novo link
                $sql_insert = "INSERT INTO marketplace_links (cliente_id, token_acesso, data_expiracao) VALUES (?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iss", $cliente_id, $token, $data_expiracao);
                
                if ($stmt_insert->execute()) {
                    $message = "Link gerado com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao gerar link: " . $conn->error;
                    $message_type = "danger";
                }
            }
            break;
            
        case 'desativar_link':
            $link_id = (int)$_POST['link_id'];
            $sql_update = "UPDATE marketplace_links SET ativo = 0 WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("i", $link_id);
            
            if ($stmt_update->execute()) {
                $message = "Link desativado com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao desativar link: " . $conn->error;
                $message_type = "danger";
            }
            break;
            
        case 'ativar_link':
            $link_id = (int)$_POST['link_id'];
            $sql_update = "UPDATE marketplace_links SET ativo = 1 WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("i", $link_id);
            
            if ($stmt_update->execute()) {
                $message = "Link ativado com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao ativar link: " . $conn->error;
                $message_type = "danger";
            }
            break;
    }
}

// Buscar todos os links
$sql_links = "SELECT ml.*, c.nome as cliente_nome, c.email as cliente_email 
              FROM marketplace_links ml 
              LEFT JOIN clientes c ON ml.cliente_id = c.id 
              ORDER BY ml.data_criacao DESC";
$result_links = $conn->query($sql_links);

// Buscar clientes para o formulário
$sql_clientes = "SELECT id, nome, email FROM clientes ORDER BY nome ASC";
$result_clientes = $conn->query($sql_clientes);

// Estatísticas
$sql_stats = "SELECT 
    COUNT(*) as total_links,
    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as links_ativos,
    SUM(total_acessos) as total_acessos
    FROM marketplace_links";
$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();

include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-link"></i>
        Marketplace - Links Exclusivos
    </h1>
    <p class="page-subtitle">
        Gere e gerencie links exclusivos para seus clientes acessarem o marketplace
    </p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'info' ? 'info-circle' : 'exclamation-triangle'); ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stats-card primary fade-in-up">
            <div class="stats-icon primary">
                <i class="fas fa-link"></i>
            </div>
            <div class="stats-value"><?php echo $stats['total_links']; ?></div>
            <div class="stats-label">Total de Links</div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stats-card success fade-in-up">
            <div class="stats-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-value"><?php echo $stats['links_ativos']; ?></div>
            <div class="stats-label">Links Ativos</div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stats-card info fade-in-up">
            <div class="stats-icon info">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stats-value"><?php echo $stats['total_acessos']; ?></div>
            <div class="stats-label">Total de Acessos</div>
        </div>
    </div>
</div>

<!-- Formulário para Gerar Novo Link -->
<div class="modern-card fade-in-up mb-4">
    <div class="card-header-modern">
        <i class="fas fa-plus"></i>
        Gerar Novo Link Exclusivo
    </div>
    <div class="card-body-modern">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="acao" value="gerar_link">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente *</label>
                    <select class="form-control" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione um cliente...</option>
                        <?php
                        if ($result_clientes && $result_clientes->num_rows > 0) {
                            $result_clientes->data_seek(0);
                            while($cliente = $result_clientes->fetch_assoc()) {
                                echo '<option value="' . $cliente['id'] . '">' . htmlspecialchars($cliente['nome']) . ' (' . htmlspecialchars($cliente['email']) . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="data_expiracao" class="form-label">Data de Expiração (Opcional)</label>
                    <input type="date" class="form-control" id="data_expiracao" name="data_expiracao" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Gerar Link
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Links -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-list"></i>
        Links Gerados
        <div class="ms-auto">
            <span class="badge bg-primary"><?php echo $stats['total_links']; ?> links</span>
        </div>
    </div>
    <div class="card-body-modern">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Link</th>
                        <th>Status</th>
                        <th>Acessos</th>
                        <th>Criado em</th>
                        <th>Expira em</th>
                        <th>Último Acesso</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_links && $result_links->num_rows > 0) {
                        while($link = $result_links->fetch_assoc()) {
                            $url_marketplace = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/marketplace.php?token=" . $link['token_acesso'];
                            $status_class = $link['ativo'] ? 'success' : 'danger';
                            $status_text = $link['ativo'] ? 'Ativo' : 'Inativo';
                            
                            // Verificar se expirou
                            $expirado = false;
                            if ($link['data_expiracao'] && strtotime($link['data_expiracao']) < time()) {
                                $expirado = true;
                                $status_class = 'warning';
                                $status_text = 'Expirado';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($link['cliente_nome']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($link['cliente_email']); ?></small>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-sm" value="<?php echo $url_marketplace; ?>" readonly id="link_<?php echo $link['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="copiarLink('link_<?php echo $link['id']; ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td><?php echo $link['total_acessos']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($link['data_criacao'])); ?></td>
                                <td>
                                    <?php 
                                    if ($link['data_expiracao']) {
                                        echo date('d/m/Y', strtotime($link['data_expiracao']));
                                    } else {
                                        echo '<span class="text-muted">Sem expiração</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($link['ultimo_acesso']) {
                                        echo date('d/m/Y H:i', strtotime($link['ultimo_acesso']));
                                    } else {
                                        echo '<span class="text-muted">Nunca</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($link['ativo'] && !$expirado): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="desativar_link">
                                            <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" title="Desativar">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="acao" value="ativar_link">
                                            <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm" title="Ativar">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo $url_marketplace; ?>" target="_blank" class="btn btn-info btn-sm" title="Visualizar">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">Nenhum link gerado ainda.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copiarLink(inputId) {
    const input = document.getElementById(inputId);
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    // Feedback visual
    const button = input.nextElementSibling;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i>';
    button.classList.remove('btn-outline-primary');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-primary');
    }, 2000);
}
</script>

<?php
$conn->close();
include_once 'includes/footer.php';
?>
