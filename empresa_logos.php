<?php
// empresa_logos.php - Página para gerenciar logos das empresas representadas
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

// Processar upload de logo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_logo'])) {
    $empresa_id = $_POST['empresa_id'];
    
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['logo_file']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            // Criar diretório se não existir
            $upload_dir = 'uploads/logos_empresas/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Gerar nome único para o arquivo
            $file_extension = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
            $new_filename = 'empresa_' . $empresa_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_path)) {
                // Atualizar banco de dados
                $sql = "UPDATE empresas_representadas SET logo_empresa = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $upload_path, $empresa_id);
                
                if ($stmt->execute()) {
                    $success_message = "Logo da empresa atualizada com sucesso!";
                } else {
                    $error_message = "Erro ao atualizar no banco de dados.";
                }
            } else {
                $error_message = "Erro ao fazer upload do arquivo.";
            }
        } else {
            $error_message = "Tipo de arquivo não permitido. Use apenas: JPG, PNG, GIF ou WEBP.";
        }
    } else {
        $error_message = "Erro no upload do arquivo.";
    }
}

// Buscar todas as empresas
$sql = "SELECT id, nome_empresa, razao_social, logo_empresa FROM empresas_representadas ORDER BY nome_empresa";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Logos das Empresas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .logo-preview {
            max-width: 100px;
            max-height: 60px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #e7f3ff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar ou navegação aqui se necessário -->
            
            <main class="col-md-12 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-building"></i> Gerenciar Logos das Empresas</h1>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-images"></i> Logos das Empresas Representadas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Empresa</th>
                                        <th>Razão Social</th>
                                        <th>Logo Atual</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($empresa = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $empresa['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($empresa['nome_empresa']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($empresa['razao_social'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($empresa['logo_empresa'] && file_exists($empresa['logo_empresa'])): ?>
                                                        <img src="<?php echo $empresa['logo_empresa']; ?>" 
                                                             alt="Logo <?php echo htmlspecialchars($empresa['nome_empresa']); ?>" 
                                                             class="logo-preview">
                                                    <?php else: ?>
                                                        <span class="text-muted">
                                                            <i class="fas fa-image"></i> Sem logo
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#uploadModal<?php echo $empresa['id']; ?>">
                                                        <i class="fas fa-upload"></i> Upload Logo
                                                    </button>
                                                    
                                                    <?php if ($empresa['logo_empresa']): ?>
                                                        <a href="<?php echo $empresa['logo_empresa']; ?>" 
                                                           target="_blank" 
                                                           class="btn btn-outline-info btn-sm">
                                                            <i class="fas fa-eye"></i> Ver
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <!-- Modal para upload de logo -->
                                            <div class="modal fade" id="uploadModal<?php echo $empresa['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                <i class="fas fa-upload"></i> 
                                                                Upload Logo - <?php echo htmlspecialchars($empresa['nome_empresa']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" enctype="multipart/form-data">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="empresa_id" value="<?php echo $empresa['id']; ?>">
                                                                
                                                                <div class="upload-area mb-3">
                                                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                                                    <div class="mb-3">
                                                                        <label for="logo_file<?php echo $empresa['id']; ?>" class="form-label">
                                                                            Selecione o arquivo de logo
                                                                        </label>
                                                                        <input type="file" 
                                                                               class="form-control" 
                                                                               id="logo_file<?php echo $empresa['id']; ?>" 
                                                                               name="logo_file" 
                                                                               accept="image/*" 
                                                                               required>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        Formatos aceitos: JPG, PNG, GIF, WEBP<br>
                                                                        Tamanho recomendado: máximo 2MB
                                                                    </small>
                                                                </div>

                                                                <?php if ($empresa['logo_empresa'] && file_exists($empresa['logo_empresa'])): ?>
                                                                    <div class="alert alert-info">
                                                                        <strong>Logo atual:</strong><br>
                                                                        <img src="<?php echo $empresa['logo_empresa']; ?>" 
                                                                             alt="Logo atual" 
                                                                             class="logo-preview mt-2">
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                    Cancelar
                                                                </button>
                                                                <button type="submit" name="upload_logo" class="btn btn-primary">
                                                                    <i class="fas fa-upload"></i> Fazer Upload
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                <i class="fas fa-info-circle"></i> Nenhuma empresa encontrada
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle"></i> Como funciona
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-upload"></i> Upload de Logos</h6>
                                <ul>
                                    <li>Faça upload de logos para cada empresa representada</li>
                                    <li>Formatos aceitos: JPG, PNG, GIF, WEBP</li>
                                    <li>Tamanho recomendado: máximo 2MB</li>
                                    <li>Resolução recomendada: 300x200px ou similar</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-file-pdf"></i> Uso nos Orçamentos</h6>
                                <ul>
                                    <li>Quando um orçamento contém produtos de uma empresa específica</li>
                                    <li>A logo da empresa aparece ao lado do título "PROPOSTA COMERCIAL"</li>
                                    <li>Se há múltiplas empresas, usa a logo da primeira encontrada</li>
                                    <li>Se não há logo, mantém o layout padrão centralizado</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview da imagem antes do upload
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Criar preview se necessário
                        console.log('Arquivo selecionado:', file.name);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>