<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

// Variáveis para o formulário
$id = $nome = $descricao = $sku = $preco_venda = $percentual_lucro = $quantidade_estoque = $estoque_minimo = $fornecedor = $empresa_id = $imagem_produto = "";
$title = "Cadastrar Novo Produto";
$submit_button_text = "Cadastrar Produto";
$message = '';
$message_type = '';

// Buscar empresas representadas para o dropdown
$sql_empresas = "SELECT id, nome_empresa FROM empresas_representadas WHERE status = 'ativo' ORDER BY nome_empresa ASC";
$result_empresas = $conn->query($sql_empresas);

// Processar formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta e sanitiza os dados do formulário
    $id = trim($_POST["id"] ?? '');
    $nome = trim($_POST["nome"]);
    $descricao = trim($_POST["descricao"]);
    $sku = trim($_POST["sku"]);
    // Usar str_replace para converter vírgulas em pontos para valores decimais
    $preco_venda = str_replace(',', '.', trim($_POST["preco_venda"]));
    $percentual_lucro = str_replace(',', '.', trim($_POST["percentual_lucro"]));
    $quantidade_estoque = trim($_POST["quantidade_estoque"]);
    $estoque_minimo = trim($_POST["estoque_minimo"]);
    $fornecedor = trim($_POST["fornecedor"]);
    $empresa_id = trim($_POST["empresa_id"]);

    // Validação dos campos
    if (empty($nome) || empty($empresa_id) || !is_numeric($preco_venda) || !is_numeric($percentual_lucro) || !is_numeric($quantidade_estoque) || !is_numeric($estoque_minimo)) {
        $message = "Por favor, preencha todos os campos obrigatórios (*) e garanta que os valores numéricos estejam corretos.";
        $message_type = "danger";
    } else {
        if (empty($id)) { // Inserir Novo Produto
            // O campo `percentual_lucro` já existe na sua tabela, então o SQL está correto.
            $sql = "INSERT INTO produtos (nome, descricao, sku, preco_venda, percentual_lucro, quantidade_estoque, estoque_minimo, fornecedor, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                // CORREÇÃO APLICADA: Os tipos de 'preco_venda' e 'percentual_lucro' são 'd' (decimal), para não arredondar.
                $stmt->bind_param("sssddiisi", $nome, $descricao, $sku, $preco_venda, $percentual_lucro, $quantidade_estoque, $estoque_minimo, $fornecedor, $empresa_id);
                if ($stmt->execute()) {
                    $message = "Produto cadastrado com sucesso!";
                    $message_type = "success";
                    $id = $nome = $descricao = $sku = $preco_venda = $percentual_lucro = $quantidade_estoque = $estoque_minimo = $fornecedor = $empresa_id = "";
                } else {
                    $message = "Erro ao cadastrar produto: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
        } else { // Editar Produto Existente
            $sql = "UPDATE produtos SET nome = ?, descricao = ?, sku = ?, preco_venda = ?, percentual_lucro = ?, quantidade_estoque = ?, estoque_minimo = ?, fornecedor = ?, empresa_id = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                // CORREÇÃO APLICADA: Os tipos também foram ajustados para 'd' (decimal) na atualização.
                $stmt->bind_param("sssddiisii", $nome, $descricao, $sku, $preco_venda, $percentual_lucro, $quantidade_estoque, $estoque_minimo, $fornecedor, $empresa_id, $id);
                if ($stmt->execute()) {
                    $message = "Produto atualizado com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao atualizar produto: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            }
        }
    }
}

// Preencher formulário para edição se um ID for passado via GET
if (isset($_GET["id"]) && empty($message)) {
    $id = trim($_GET["id"]);
    $sql_edit = "SELECT id, nome, descricao, sku, preco_venda, percentual_lucro, quantidade_estoque, estoque_minimo, fornecedor, empresa_id, imagem_produto FROM produtos WHERE id = ?";
    if ($stmt_edit = $conn->prepare($sql_edit)) {
        $stmt_edit->bind_param("i", $id);
        if ($stmt_edit->execute()) {
            $result_edit = $stmt_edit->get_result();
            if ($result_edit->num_rows == 1) {
                $row = $result_edit->fetch_assoc();
                $nome = $row['nome'];
                $descricao = $row['descricao'];
                $sku = $row['sku'];
                $preco_venda = number_format($row['preco_venda'], 2, ',', '.');
                $percentual_lucro = number_format($row['percentual_lucro'], 2, ',', '.');
                $quantidade_estoque = $row['quantidade_estoque'];
                $estoque_minimo = $row['estoque_minimo'];
                $fornecedor = $row['fornecedor'];
                $empresa_id = $row['empresa_id'];
                $imagem_produto = $row['imagem_produto'];
                $title = "Editar Produto";
                $submit_button_text = "Atualizar Produto";
            } else {
                $message = "Produto não encontrado.";
                $message_type = "danger";
                $id = "";
            }
        }
        $stmt_edit->close();
    }
}

$conn->close();

include_once 'includes/header.php';
?>

<h2 class="mb-4"><i class="fas fa-<?php echo ($id ? 'edit' : 'plus-circle'); ?> me-2"></i> <?php echo $title; ?></h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="empresa_id" class="form-label">Empresa Representada <span class="text-danger">*</span></label>
                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                        <option value="">Selecione a empresa...</option>
                        <?php
                        if ($result_empresas && $result_empresas->num_rows > 0) {
                            $result_empresas->data_seek(0);
                            while($empresa = $result_empresas->fetch_assoc()) {
                                $selected = ($empresa['id'] == $empresa_id) ? 'selected' : '';
                                echo "<option value='{$empresa['id']}' {$selected}>" . htmlspecialchars($empresa['nome_empresa']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nome" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="sku" class="form-label">SKU (Código)</label>
                    <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($sku); ?>">
                </div>
                <div class="col-md-8 mb-3">
                    <label for="fornecedor" class="form-label">Fornecedor</label>
                    <input type="text" class="form-control" id="fornecedor" name="fornecedor" value="<?php echo htmlspecialchars($fornecedor); ?>" placeholder="Nome do fornecedor original">
                </div>
            </div>

            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($descricao); ?></textarea>
            </div>

            <!-- Seção de Imagem do Produto -->
            <?php if ($id): ?>
            <div class="mb-4">
                <label class="form-label">Imagem do Produto</label>
                <div class="row">
                    <div class="col-md-6">
                        <div class="border rounded p-3 text-center" id="imagemPreview">
                            <?php if ($imagem_produto && file_exists("uploads/produtos/" . $imagem_produto)): ?>
                                <img src="uploads/produtos/<?php echo htmlspecialchars($imagem_produto); ?>"
                                     alt="Imagem do produto"
                                     class="img-fluid rounded"
                                     style="max-height: 200px;">
                                <p class="mt-2 mb-0 text-muted small">Imagem atual</p>
                            <?php else: ?>
                                <div class="text-muted">
                                    <i class="fas fa-image fa-3x mb-2"></i>
                                    <p>Nenhuma imagem cadastrada</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <input type="file" class="form-control" id="imagemProduto" accept="image/*">
                            <div class="form-text">
                                Formatos aceitos: JPEG, PNG, GIF, WebP<br>
                                Tamanho máximo: 5MB
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="uploadImagem" disabled>
                            <i class="fas fa-upload me-2"></i>Enviar Imagem
                        </button>
                        <?php if ($imagem_produto): ?>
                        <button type="button" class="btn btn-outline-danger ms-2" id="removerImagem">
                            <i class="fas fa-trash me-2"></i>Remover
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-4 col-md-6 mb-3">
                    <label for="preco_venda" class="form-label">Preço de Venda (R$) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="preco_venda" name="preco_venda" value="<?php echo htmlspecialchars($preco_venda); ?>" required placeholder="0,00">
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <label for="percentual_lucro" class="form-label">% de Lucro <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="percentual_lucro" name="percentual_lucro" value="<?php echo htmlspecialchars($percentual_lucro); ?>" required placeholder="0,00">
                </div>
                <div class="col-lg-3 col-6 mb-3">
                    <label for="quantidade_estoque" class="form-label">Estoque Atual <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="quantidade_estoque" name="quantidade_estoque" value="<?php echo htmlspecialchars($quantidade_estoque); ?>" required min="0">
                </div>
                <div class="col-lg-3 col-6 mb-3">
                    <label for="estoque_minimo" class="form-label">Estoque Mínimo <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="estoque_minimo" name="estoque_minimo" value="<?php echo htmlspecialchars($estoque_minimo); ?>" required min="0">
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-<?php echo ($id ? 'save' : 'plus'); ?> me-2"></i> <?php echo $submit_button_text; ?>
                </button>
                <a href="produtos.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Voltar para Produtos
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imagemInput = document.getElementById('imagemProduto');
    const uploadBtn = document.getElementById('uploadImagem');
    const removerBtn = document.getElementById('removerImagem');
    const imagemPreview = document.getElementById('imagemPreview');
    const produtoId = <?php echo $id ? $id : 'null'; ?>;

    if (imagemInput) {
        imagemInput.addEventListener('change', function() {
            uploadBtn.disabled = !this.files.length;
        });
    }

    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            const file = imagemInput.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('imagem', file);
            formData.append('produto_id', produtoId);

            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';

            fetch('upload_produto_imagem.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar preview da imagem
                    imagemPreview.innerHTML = `
                        <img src="uploads/produtos/${data.filename}"
                             alt="Imagem do produto"
                             class="img-fluid rounded"
                             style="max-height: 200px;">
                        <p class="mt-2 mb-0 text-muted small">Imagem atual</p>
                    `;

                    // Mostrar botão de remover se não existir
                    if (!removerBtn) {
                        const newRemoverBtn = document.createElement('button');
                        newRemoverBtn.type = 'button';
                        newRemoverBtn.className = 'btn btn-outline-danger ms-2';
                        newRemoverBtn.id = 'removerImagem';
                        newRemoverBtn.innerHTML = '<i class="fas fa-trash me-2"></i>Remover';
                        uploadBtn.parentNode.appendChild(newRemoverBtn);
                    }

                    alert('Imagem enviada com sucesso!');
                    imagemInput.value = '';
                } else {
                    alert('Erro ao enviar imagem: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao enviar imagem');
            })
            .finally(() => {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Enviar Imagem';
            });
        });
    }

    if (removerBtn) {
        removerBtn.addEventListener('click', function() {
            if (!confirm('Tem certeza que deseja remover a imagem?')) return;

            fetch('remover_produto_imagem.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ produto_id: produtoId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    imagemPreview.innerHTML = `
                        <div class="text-muted">
                            <i class="fas fa-image fa-3x mb-2"></i>
                            <p>Nenhuma imagem cadastrada</p>
                        </div>
                    `;
                    removerBtn.remove();
                    alert('Imagem removida com sucesso!');
                } else {
                    alert('Erro ao remover imagem: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao remover imagem');
            });
        });
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>