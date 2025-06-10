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
$empresa_id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($empresa_id);

// Dados da empresa para edição
$empresa_data = [
    'nome_empresa' => '',
    'razao_social' => '',
    'cnpj' => '',
    'endereco' => '',
    'cidade' => '',
    'estado' => '',
    'cep' => '',
    'telefone' => '',
    'email' => '',
    'contato_responsavel' => '',
    'telefone_responsavel' => '',
    'email_responsavel' => '',
    'comissao_padrao' => '5.00',
    'observacoes' => '',
    'status' => 'ativo',
    'data_inicio_representacao' => date('Y-m-d'),
    'logo_empresa' => ''
];

// Se for edição, buscar dados da empresa
if ($is_edit) {
    $sql_select = "SELECT * FROM empresas_representadas WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $empresa_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    
    if ($result->num_rows > 0) {
        $empresa_data = $result->fetch_assoc();
    } else {
        $message = "Empresa não encontrada.";
        $message_type = "danger";
        $is_edit = false;
    }
    $stmt_select->close();
}

// Processar formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_empresa = trim($_POST['nome_empresa']);
    $razao_social = trim($_POST['razao_social']);
    $cnpj = trim($_POST['cnpj']);
    $endereco = trim($_POST['endereco']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $cep = trim($_POST['cep']);
    $telefone = trim($_POST['telefone']);
    $email = trim($_POST['email']);
    $contato_responsavel = trim($_POST['contato_responsavel']);
    $telefone_responsavel = trim($_POST['telefone_responsavel']);
    $email_responsavel = trim($_POST['email_responsavel']);
    $comissao_padrao = floatval($_POST['comissao_padrao']);
    $observacoes = trim($_POST['observacoes']);
    $status = $_POST['status'];
    $data_inicio_representacao = $_POST['data_inicio_representacao'];
    
    // Manter logo atual se não houver novo upload
    $logo_empresa = $empresa_data['logo_empresa'];

    // Validações
    if (empty($nome_empresa)) {
        $message = "Nome da empresa é obrigatório.";
        $message_type = "danger";
    } elseif ($comissao_padrao < 0 || $comissao_padrao > 100) {
        $message = "Comissão deve estar entre 0% e 100%.";
        $message_type = "danger";
    } else {
        // Processar upload da logo se houver
        if (isset($_FILES['logo_empresa']) && $_FILES['logo_empresa']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['logo_empresa']['type'];
            $file_size = $_FILES['logo_empresa']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_type, $allowed_types)) {
                $message = "Tipo de arquivo não permitido. Use apenas: JPG, PNG, GIF ou WEBP.";
                $message_type = "danger";
            } elseif ($file_size > $max_size) {
                $message = "Arquivo muito grande. Tamanho máximo: 5MB.";
                $message_type = "danger";
            } else {
                // Criar diretório se não existir
                $upload_dir = 'uploads/logos_empresas/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Remover logo anterior se existir
                if (!empty($empresa_data['logo_empresa']) && file_exists($empresa_data['logo_empresa'])) {
                    unlink($empresa_data['logo_empresa']);
                }
                
                // Gerar nome único para o arquivo
                $file_extension = pathinfo($_FILES['logo_empresa']['name'], PATHINFO_EXTENSION);
                $new_filename = 'empresa_' . ($is_edit ? $empresa_id : 'new') . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['logo_empresa']['tmp_name'], $upload_path)) {
                    $logo_empresa = $upload_path;
                } else {
                    $message = "Erro ao fazer upload da logo.";
                    $message_type = "danger";
                }
            }
        }
        
        // Se não houve erro no upload (ou não houve upload), continuar com o cadastro/edição
        if (empty($message)) {
            if ($is_edit) {
                // Atualizar empresa
                $sql_update = "UPDATE empresas_representadas SET 
                    nome_empresa = ?, razao_social = ?, cnpj = ?, endereco = ?, 
                    cidade = ?, estado = ?, cep = ?, telefone = ?, email = ?,
                    contato_responsavel = ?, telefone_responsavel = ?, email_responsavel = ?,
                    comissao_padrao = ?, observacoes = ?, status = ?, data_inicio_representacao = ?, logo_empresa = ?
                    WHERE id = ?";
                
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("ssssssssssssdssssi", 
                    $nome_empresa, $razao_social, $cnpj, $endereco, $cidade, $estado, $cep,
                    $telefone, $email, $contato_responsavel, $telefone_responsavel, $email_responsavel,
                    $comissao_padrao, $observacoes, $status, $data_inicio_representacao, $logo_empresa, $empresa_id
                );
            } else {
                // Inserir nova empresa
                $sql_insert = "INSERT INTO empresas_representadas (
                    nome_empresa, razao_social, cnpj, endereco, cidade, estado, cep,
                    telefone, email, contato_responsavel, telefone_responsavel, email_responsavel,
                    comissao_padrao, observacoes, status, data_inicio_representacao, logo_empresa
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql_insert);
                $stmt->bind_param("ssssssssssssdssss", 
                    $nome_empresa, $razao_social, $cnpj, $endereco, $cidade, $estado, $cep,
                    $telefone, $email, $contato_responsavel, $telefone_responsavel, $email_responsavel,
                    $comissao_padrao, $observacoes, $status, $data_inicio_representacao, $logo_empresa
                );
            }

            if ($stmt->execute()) {
                $message = $is_edit ? "Empresa atualizada com sucesso!" : "Empresa cadastrada com sucesso!";
                $message_type = "success";
                
                // Atualizar dados para mostrar a logo recém-carregada
                if (!$is_edit) {
                    $empresa_id = $stmt->insert_id;
                }
                $empresa_data['logo_empresa'] = $logo_empresa;
                
                if (!$is_edit) {
                    // Redirecionar para a lista após cadastro
                    header("Location: empresas_representadas.php");
                    exit;
                }
            } else {
                $message = "Erro ao " . ($is_edit ? "atualizar" : "cadastrar") . " empresa: " . $stmt->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}

$conn->close();
include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-building"></i>
        <?php echo $is_edit ? 'Editar Empresa' : 'Cadastrar Nova Empresa'; ?>
    </h1>
    <p class="page-subtitle">
        <?php echo $is_edit ? 'Atualize os dados da empresa representada' : 'Adicione uma nova empresa que você representa'; ?>
    </p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Form Card -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-edit"></i>
        Dados da Empresa
    </div>
    <div class="card-body-modern">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row g-4">
                <!-- Informações Básicas -->
                <div class="col-12">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-info-circle me-2"></i>Informações Básicas
                    </h5>
                </div>
                
                <div class="col-md-6">
                    <label for="nome_empresa" class="form-label">Nome da Empresa *</label>
                    <input type="text" class="form-control" id="nome_empresa" name="nome_empresa" 
                           value="<?php echo htmlspecialchars($empresa_data['nome_empresa']); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="razao_social" class="form-label">Razão Social</label>
                    <input type="text" class="form-control" id="razao_social" name="razao_social" 
                           value="<?php echo htmlspecialchars($empresa_data['razao_social']); ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="cnpj" class="form-label">CNPJ</label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" 
                           value="<?php echo htmlspecialchars($empresa_data['cnpj']); ?>" 
                           placeholder="00.000.000/0000-00">
                </div>
                
                <div class="col-md-3">
                    <label for="comissao_padrao" class="form-label">Comissão Padrão (%)</label>
                    <input type="number" class="form-control" id="comissao_padrao" name="comissao_padrao" 
                           value="<?php echo $empresa_data['comissao_padrao']; ?>" 
                           min="0" max="100" step="0.01" required>
                </div>
                
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="ativo" <?php echo $empresa_data['status'] == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo $empresa_data['status'] == 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>

                <!-- Logo da Empresa -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-image me-2"></i>Logo da Empresa
                    </h5>
                </div>
                
                <div class="col-md-6">
                    <label for="logo_empresa" class="form-label">Logo da Empresa</label>
                    <input type="file" class="form-control" id="logo_empresa" name="logo_empresa" 
                           accept="image/*" onchange="previewLogo(this)">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> 
                        Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB.
                        <br>Dimensões recomendadas: 300x200px ou similar.
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Preview da Logo</label>
                    <div class="logo-preview-container" style="border: 2px dashed #ddd; border-radius: 8px; padding: 20px; text-align: center; background: #f8f9fa; min-height: 120px; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($empresa_data['logo_empresa']) && file_exists($empresa_data['logo_empresa'])): ?>
                            <img id="logo-preview" 
                                 src="<?php echo $empresa_data['logo_empresa']; ?>" 
                                 alt="Logo da empresa" 
                                 style="max-width: 200px; max-height: 100px; object-fit: contain; border-radius: 4px;">
                        <?php else: ?>
                            <div id="logo-preview" style="color: #6c757d;">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <br>Nenhuma logo selecionada
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($empresa_data['logo_empresa']) && file_exists($empresa_data['logo_empresa'])): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-file"></i> 
                                Arquivo atual: <?php echo basename($empresa_data['logo_empresa']); ?>
                            </small>
                            <br>
                            <small class="text-info">
                                <i class="fas fa-info-circle"></i> 
                                Selecione um novo arquivo para substituir a logo atual.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Endereço -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-map-marker-alt me-2"></i>Endereço
                    </h5>
                </div>
                
                <div class="col-12">
                    <label for="endereco" class="form-label">Endereço Completo</label>
                    <input type="text" class="form-control" id="endereco" name="endereco" 
                           value="<?php echo htmlspecialchars($empresa_data['endereco']); ?>">
                </div>
                
                <div class="col-md-4">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" class="form-control" id="cidade" name="cidade" 
                           value="<?php echo htmlspecialchars($empresa_data['cidade']); ?>">
                </div>
                
                <div class="col-md-4">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-control" id="estado" name="estado">
                        <option value="">Selecione...</option>
                        <option value="AC" <?php echo $empresa_data['estado'] == 'AC' ? 'selected' : ''; ?>>Acre</option>
                        <option value="AL" <?php echo $empresa_data['estado'] == 'AL' ? 'selected' : ''; ?>>Alagoas</option>
                        <option value="AP" <?php echo $empresa_data['estado'] == 'AP' ? 'selected' : ''; ?>>Amapá</option>
                        <option value="AM" <?php echo $empresa_data['estado'] == 'AM' ? 'selected' : ''; ?>>Amazonas</option>
                        <option value="BA" <?php echo $empresa_data['estado'] == 'BA' ? 'selected' : ''; ?>>Bahia</option>
                        <option value="CE" <?php echo $empresa_data['estado'] == 'CE' ? 'selected' : ''; ?>>Ceará</option>
                        <option value="DF" <?php echo $empresa_data['estado'] == 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                        <option value="ES" <?php echo $empresa_data['estado'] == 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                        <option value="GO" <?php echo $empresa_data['estado'] == 'GO' ? 'selected' : ''; ?>>Goiás</option>
                        <option value="MA" <?php echo $empresa_data['estado'] == 'MA' ? 'selected' : ''; ?>>Maranhão</option>
                        <option value="MT" <?php echo $empresa_data['estado'] == 'MT' ? 'selected' : ''; ?>>Mato Grosso</option>
                        <option value="MS" <?php echo $empresa_data['estado'] == 'MS' ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                        <option value="MG" <?php echo $empresa_data['estado'] == 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                        <option value="PA" <?php echo $empresa_data['estado'] == 'PA' ? 'selected' : ''; ?>>Pará</option>
                        <option value="PB" <?php echo $empresa_data['estado'] == 'PB' ? 'selected' : ''; ?>>Paraíba</option>
                        <option value="PR" <?php echo $empresa_data['estado'] == 'PR' ? 'selected' : ''; ?>>Paraná</option>
                        <option value="PE" <?php echo $empresa_data['estado'] == 'PE' ? 'selected' : ''; ?>>Pernambuco</option>
                        <option value="PI" <?php echo $empresa_data['estado'] == 'PI' ? 'selected' : ''; ?>>Piauí</option>
                        <option value="RJ" <?php echo $empresa_data['estado'] == 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                        <option value="RN" <?php echo $empresa_data['estado'] == 'RN' ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                        <option value="RS" <?php echo $empresa_data['estado'] == 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                        <option value="RO" <?php echo $empresa_data['estado'] == 'RO' ? 'selected' : ''; ?>>Rondônia</option>
                        <option value="RR" <?php echo $empresa_data['estado'] == 'RR' ? 'selected' : ''; ?>>Roraima</option>
                        <option value="SC" <?php echo $empresa_data['estado'] == 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                        <option value="SP" <?php echo $empresa_data['estado'] == 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                        <option value="SE" <?php echo $empresa_data['estado'] == 'SE' ? 'selected' : ''; ?>>Sergipe</option>
                        <option value="TO" <?php echo $empresa_data['estado'] == 'TO' ? 'selected' : ''; ?>>Tocantins</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="cep" class="form-label">CEP</label>
                    <input type="text" class="form-control" id="cep" name="cep" 
                           value="<?php echo htmlspecialchars($empresa_data['cep']); ?>" 
                           placeholder="00000-000">
                </div>

                <!-- Contato da Empresa -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-phone me-2"></i>Contato da Empresa
                    </h5>
                </div>
                
                <div class="col-md-6">
                    <label for="telefone" class="form-label">Telefone da Empresa</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" 
                           value="<?php echo htmlspecialchars($empresa_data['telefone']); ?>" 
                           placeholder="(00) 0000-0000">
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">Email da Empresa</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($empresa_data['email']); ?>">
                </div>

                <!-- Contato Responsável -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-user-tie me-2"></i>Contato Responsável
                    </h5>
                </div>
                
                <div class="col-md-4">
                    <label for="contato_responsavel" class="form-label">Nome do Responsável</label>
                    <input type="text" class="form-control" id="contato_responsavel" name="contato_responsavel" 
                           value="<?php echo htmlspecialchars($empresa_data['contato_responsavel']); ?>">
                </div>
                
                <div class="col-md-4">
                    <label for="telefone_responsavel" class="form-label">Telefone do Responsável</label>
                    <input type="text" class="form-control" id="telefone_responsavel" name="telefone_responsavel" 
                           value="<?php echo htmlspecialchars($empresa_data['telefone_responsavel']); ?>" 
                           placeholder="(00) 00000-0000">
                </div>
                
                <div class="col-md-4">
                    <label for="email_responsavel" class="form-label">Email do Responsável</label>
                    <input type="email" class="form-control" id="email_responsavel" name="email_responsavel" 
                           value="<?php echo htmlspecialchars($empresa_data['email_responsavel']); ?>">
                </div>

                <!-- Informações Adicionais -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-calendar me-2"></i>Informações Adicionais
                    </h5>
                </div>
                
                <div class="col-md-6">
                    <label for="data_inicio_representacao" class="form-label">Data de Início da Representação</label>
                    <input type="date" class="form-control" id="data_inicio_representacao" name="data_inicio_representacao" 
                           value="<?php echo $empresa_data['data_inicio_representacao']; ?>">
                </div>
                
                <div class="col-12">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" 
                              placeholder="Informações adicionais sobre a empresa..."><?php echo htmlspecialchars($empresa_data['observacoes']); ?></textarea>
                </div>

                <!-- Botões -->
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="empresas_representadas.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Atualizar' : 'Cadastrar'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Função para preview da logo
function previewLogo(input) {
    const preview = document.getElementById('logo-preview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview da logo" style="max-width: 200px; max-height: 100px; object-fit: contain; border-radius: 4px;">';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Máscara para CNPJ
document.getElementById('cnpj').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
    value = value.replace(/(\d{4})(\d)/, '$1-$2');
    e.target.value = value;
});

// Máscara para CEP
document.getElementById('cep').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    value = value.replace(/^(\d{5})(\d)/, '$1-$2');
    e.target.value = value;
});

// Máscara para telefones
function phoneMask(input) {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 10) {
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
        }
        e.target.value = value;
    });
}

phoneMask(document.getElementById('telefone'));
phoneMask(document.getElementById('telefone_responsavel'));

// Validação de arquivo
document.getElementById('logo_empresa').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!allowedTypes.includes(file.type)) {
            alert('Tipo de arquivo não permitido. Use apenas: JPG, PNG, GIF ou WEBP.');
            e.target.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            alert('Arquivo muito grande. Tamanho máximo: 5MB.');
            e.target.value = '';
            return;
        }
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>