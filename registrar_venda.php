<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

$venda_id = $cliente_id = $data_venda = $valor_total = $forma_pagamento = $status_venda = "";
$title = "Registrar Nova Venda";
$submit_button_text = "Registrar Venda";
$message = '';
$message_type = '';
$itens_da_venda = [];

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $venda_id_posted = trim($_POST["venda_id"] ?? '');
    $cliente_id = trim($_POST["cliente_id"]);
    $forma_pagamento = trim($_POST["forma_pagamento"]);
    $status_venda = trim($_POST["status_venda"]);
    $itens_selecionados_json = $_POST["itens_selecionados_json"] ?? '[]';
    $itens_da_venda_post = json_decode($itens_selecionados_json, true);

    $calculated_valor_total = 0;
    foreach ($itens_da_venda_post as $item) {
        $calculated_valor_total += ($item['preco_unitario'] * $item['quantidade']);
    }
    $valor_total = $calculated_valor_total;

    if (empty($cliente_id) || empty($forma_pagamento) || empty($status_venda) || empty($itens_da_venda_post)) {
        $message = "Por favor, preencha todos os campos obrigatórios e adicione pelo menos um produto à venda.";
        $message_type = "danger";
    } else {
        $conn->begin_transaction();
        try {
            $is_new_sale = empty($venda_id_posted);
            $current_venda_id = $venda_id_posted;

            if ($is_new_sale) {
                $sql = "INSERT INTO vendas (cliente_id, valor_total, forma_pagamento, status_venda) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("idss", $cliente_id, $valor_total, $forma_pagamento, $status_venda);
                if (!$stmt->execute()) throw new Exception("Erro ao registrar venda: " . $stmt->error);
                $current_venda_id = $conn->insert_id;
                $stmt->close();
            } else {
                // Lógica para reverter estoque ao editar
                $sql_old_items = "SELECT produto_id, quantidade FROM itens_venda WHERE venda_id = ?";
                $stmt_old_items = $conn->prepare($sql_old_items);
                $stmt_old_items->bind_param("i", $current_venda_id);
                $stmt_old_items->execute();
                $result_old_items = $stmt_old_items->get_result();
                while ($old_item = $result_old_items->fetch_assoc()) {
                    $conn->query("UPDATE produtos SET quantidade_estoque = quantidade_estoque + {$old_item['quantidade']} WHERE id = {$old_item['produto_id']}");
                }
                $stmt_old_items->close();
                
                $conn->query("DELETE FROM itens_venda WHERE venda_id = {$current_venda_id}");

                $sql = "UPDATE vendas SET cliente_id = ?, valor_total = ?, forma_pagamento = ?, status_venda = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("idssi", $cliente_id, $valor_total, $forma_pagamento, $status_venda, $current_venda_id);
                if (!$stmt->execute()) throw new Exception("Erro ao atualizar venda: " . $stmt->error);
                $stmt->close();
            }

            foreach ($itens_da_venda_post as $item) {
                $produto_id = $item['id'];
                $quantidade = $item['quantidade'];
                $preco_unitario = $item['preco_unitario'];

                // Verifica estoque antes de inserir o item e dar baixa
                $stock_row = $conn->query("SELECT quantidade_estoque FROM produtos WHERE id = {$produto_id}")->fetch_assoc();
                if ($stock_row['quantidade_estoque'] < $quantidade) {
                    throw new Exception("Estoque insuficiente para o produto ID {$produto_id}. Disponível: {$stock_row['quantidade_estoque']}");
                }

                $sql_item = "INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
                $stmt_item = $conn->prepare($sql_item);
                $stmt_item->bind_param("iiid", $current_venda_id, $produto_id, $quantidade, $preco_unitario);
                if (!$stmt_item->execute()) throw new Exception("Erro ao inserir item da venda: " . $stmt_item->error);
                $stmt_item->close();

                $conn->query("UPDATE produtos SET quantidade_estoque = quantidade_estoque - {$quantidade} WHERE id = {$produto_id}");
            }

            if ($status_venda == 'concluida') {
                $check_transacao = $conn->query("SELECT id FROM transacoes_financeiras WHERE referencia_id = {$current_venda_id} AND tabela_referencia = 'vendas'");
                if ($check_transacao->num_rows == 0) {
                    $sql_transacao = "INSERT INTO transacoes_financeiras (tipo, valor, descricao, categoria, referencia_id, tabela_referencia, data_transacao) VALUES ('entrada', ?, ?, 'Vendas', ?, 'vendas', NOW())";
                    $stmt_transacao = $conn->prepare($sql_transacao);
                    $descricao_transacao = "Receita da Venda #" . $current_venda_id;
                    $stmt_transacao->bind_param("dsi", $valor_total, $descricao_transacao, $current_venda_id);
                    if (!$stmt_transacao->execute()) throw new Exception("Erro ao registrar transação: " . $stmt_transacao->error);
                } else {
                    $conn->query("UPDATE transacoes_financeiras SET valor = {$valor_total}, data_transacao = NOW() WHERE referencia_id = {$current_venda_id} AND tabela_referencia = 'vendas'");
                }
            } else {
                $conn->query("DELETE FROM transacoes_financeiras WHERE referencia_id = {$current_venda_id} AND tabela_referencia = 'vendas'");
            }

            $conn->commit();
            $message = $is_new_sale ? "Venda registrada com sucesso!" : "Venda atualizada com sucesso!";
            header("location: vendas.php?message=" . urlencode($message) . "&type=success");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Erro na transação: " . $e->getMessage();
            $message_type = "danger";
        }
    }
} else {
    // --- LÓGICA DE CARREGAMENTO DE DADOS (GET) ---
    $venda_id_get = $_GET["id"] ?? '';
    $from_orcamento_id = $_GET["from_orcamento_id"] ?? '';

    if (!empty($venda_id_get)) { // Edição de Venda
        $venda_id = $venda_id_get;
        $title = "Editar Venda #" . htmlspecialchars($venda_id);
        $submit_button_text = "Atualizar Venda";
        
        $sql_venda = "SELECT id, cliente_id, valor_total, forma_pagamento, status_venda FROM vendas WHERE id = ?";
        $stmt_venda = $conn->prepare($sql_venda);
        $stmt_venda->bind_param("i", $venda_id);
        $stmt_venda->execute();
        $row_venda = $stmt_venda->get_result()->fetch_assoc();
        if ($row_venda) {
            $cliente_id = $row_venda['cliente_id'];
            $forma_pagamento = $row_venda['forma_pagamento'];
            $status_venda = $row_venda['status_venda'];
        }
        
        $sql_itens = "SELECT iv.produto_id, p.nome, iv.quantidade, iv.preco_unitario FROM itens_venda iv JOIN produtos p ON iv.produto_id = p.id WHERE iv.venda_id = ?";
        $stmt_itens = $conn->prepare($sql_itens);
        $stmt_itens->bind_param("i", $venda_id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        while ($item = $result_itens->fetch_assoc()) {
            $itens_da_venda[] = ['id' => $item['produto_id'], 'nome' => $item['nome'], 'quantidade' => $item['quantidade'], 'preco_unitario' => $item['preco_unitario']];
        }

    } elseif (!empty($from_orcamento_id)) { // Conversão de Orçamento
        $title = "Registrar Venda (do Orçamento #" . htmlspecialchars($from_orcamento_id) . ")";
        $sql = "SELECT o.cliente_id, io.produto_id, p.nome, io.quantidade, io.preco_unitario, p.quantidade_estoque
                FROM orcamentos o
                JOIN itens_orcamento io ON o.id = io.orcamento_id
                JOIN produtos p ON io.produto_id = p.id
                WHERE o.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $from_orcamento_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $estoque_ok = true;
        while ($row = $result->fetch_assoc()) {
            if (empty($cliente_id)) {
                $cliente_id = $row['cliente_id'];
                // AJUSTE REALIZADO AQUI: O status já começa como 'concluida'
                $status_venda = 'concluida'; 
            }
            if ($row['quantidade_estoque'] < $row['quantidade']) {
                $estoque_ok = false;
                $message .= "Atenção: Estoque insuficiente para '{$row['nome']}'. Pedido: {$row['quantidade']}, Disponível: {$row['quantidade_estoque']}.<br>";
            }
            $itens_da_venda[] = ['id' => $row['produto_id'], 'nome' => $row['nome'], 'quantidade' => $row['quantidade'], 'preco_unitario' => $row['preco_unitario']];
        }
        $message_type = $estoque_ok ? "info" : "warning";
        $message = ($estoque_ok ? "Itens carregados do orçamento. Verifique os dados." : $message);
    }
}

// Buscar clientes e produtos para os dropdowns
$clientes_options = $conn->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
$produtos_options = $conn->query("SELECT id, nome, preco_venda, quantidade_estoque FROM produtos WHERE ativo_marketplace = 1 ORDER BY nome ASC");

// Fecha a conexão com o banco de dados APÓS todas as operações de leitura
$conn->close();

include_once 'includes/header.php';
?>

<h2 class="mb-4"><i class="fas fa-<?php echo ($venda_id ? 'edit' : 'plus-circle'); ?> me-2"></i> <?php echo $title; ?></h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form id="formVenda" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="venda_id" value="<?php echo htmlspecialchars($venda_id); ?>">
            <input type="hidden" name="itens_selecionados_json" id="itens_selecionados_json" value='<?php echo json_encode($itens_da_venda, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione um cliente...</option>
                        <?php
                        if ($clientes_options->num_rows > 0) {
                            while($cliente = $clientes_options->fetch_assoc()) {
                                $selected = ($cliente['id'] == $cliente_id) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($cliente['id']) . '" ' . $selected . '>' . htmlspecialchars($cliente['nome']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="forma_pagamento" class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                    <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                        <option value="Pix" <?php echo ($forma_pagamento == 'Pix' ? 'selected' : ''); ?>>Pix</option>
                        <option value="Cartao_Credito" <?php echo ($forma_pagamento == 'Cartao_Credito' ? 'selected' : ''); ?>>Cartão de Crédito</option>
                        <option value="Boleto" <?php echo ($forma_pagamento == 'Boleto' ? 'selected' : ''); ?>>Boleto</option>
                        <option value="Dinheiro" <?php echo ($forma_pagamento == 'Dinheiro' ? 'selected' : ''); ?>>Dinheiro</option>
                        <option value="Outro" <?php echo ($forma_pagamento == 'Outro' ? 'selected' : ''); ?>>Outro</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status_venda" class="form-label">Status da Venda <span class="text-danger">*</span></label>
                    <select class="form-select" id="status_venda" name="status_venda" required>
                        <option value="pendente" <?php echo ($status_venda == 'pendente' ? 'selected' : ''); ?>>Pendente</option>
                        <option value="concluida" <?php echo ($status_venda == 'concluida' ? 'selected' : ''); ?>>Concluída</option>
                        <option value="cancelada" <?php echo ($status_venda == 'cancelada' ? 'selected' : ''); ?>>Cancelada</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">
            <h4><i class="fas fa-boxes me-2 text-primary"></i> Itens da Venda</h4>

            <div class="row mb-3 align-items-end">
                <div class="col-md-6 mb-3">
                    <label for="produto_select" class="form-label">Adicionar Produto</label>
                    <select class="form-select" id="produto_select">
                        <option value="">Selecione um produto...</option>
                        <?php
                        if ($produtos_options->num_rows > 0) {
                            while($produto = $produtos_options->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($produto['id']) . '" data-preco="' . htmlspecialchars($produto['preco_venda']) . '" data-estoque="' . htmlspecialchars($produto['quantidade_estoque']) . '">' . htmlspecialchars($produto['nome']) . ' (Estoque: ' . htmlspecialchars($produto['quantidade_estoque']) . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="quantidade_item" class="form-label">Qtd.</label>
                    <input type="number" class="form-control" id="quantidade_item" value="1" min="1">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="preco_unitario_item" class="form-label">Preço Unit.</label>
                    <input type="text" class="form-control" id="preco_unitario_item" placeholder="0,00">
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" class="btn btn-info w-100" id="addItemBtn">
                        <i class="fas fa-plus-circle me-1"></i> Adicionar
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="tabelaItens">
                    <thead class="table-light">
                        <tr>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Preço Unit.</th>
                            <th>Subtotal</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end fs-5">Total da Venda:</th>
                            <th id="valor_total_display" class="fs-5">R$ 0,00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-<?php echo ($venda_id ? 'save' : 'check'); ?> me-2"></i> <?php echo $submit_button_text; ?>
                </button>
                <a href="vendas.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Voltar
                </a>
            </div>
        </form>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const produtoSelect = document.getElementById('produto_select');
    const quantidadeInput = document.getElementById('quantidade_item');
    const precoUnitarioInput = document.getElementById('preco_unitario_item');
    const addItemBtn = document.getElementById('addItemBtn');
    const tabelaItensBody = document.querySelector('#tabelaItens tbody');
    const valorTotalDisplay = document.getElementById('valor_total_display');
    const itensSelecionadosJsonInput = document.getElementById('itens_selecionados_json');

    let itensDaVenda = JSON.parse(itensSelecionadosJsonInput.value.replace(/&quot;/g, '"'));

    function formatarMoeda(valor) {
        return parseFloat(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function calcularTotal() {
        let total = itensDaVenda.reduce((sum, item) => sum + (item.quantidade * item.preco_unitario), 0);
        valorTotalDisplay.textContent = formatarMoeda(total);
        itensSelecionadosJsonInput.value = JSON.stringify(itensDaVenda);
    }

    function renderizarItens() {
        tabelaItensBody.innerHTML = '';
        if(itensDaVenda.length === 0){
            tabelaItensBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum item adicionado à venda.</td></tr>';
        } else {
            itensDaVenda.forEach((item, index) => {
                const subtotal = item.quantidade * item.preco_unitario;
                const row = `
                    <tr>
                        <td>${item.nome}</td>
                        <td><input type="number" class="form-control form-control-sm item-quantidade" value="${item.quantidade}" data-index="${index}" min="1"></td>
                        <td><input type="text" class="form-control form-control-sm item-preco" value="${parseFloat(item.preco_unitario).toLocaleString('pt-BR', {minimumFractionDigits: 2})}" data-index="${index}"></td>
                        <td>${formatarMoeda(subtotal)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm remover-item-btn" data-index="${index}" title="Remover"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                `;
                tabelaItensBody.insertAdjacentHTML('beforeend', row);
            });
        }
        calcularTotal();
    }

    produtoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            precoUnitarioInput.value = parseFloat(selectedOption.dataset.preco).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            quantidadeInput.value = 1;
        } else {
            precoUnitarioInput.value = '';
        }
    });

    addItemBtn.addEventListener('click', function() {
        const selectedOption = produtoSelect.options[produtoSelect.selectedIndex];
        if (!selectedOption.value) { alert('Selecione um produto.'); return; }

        const produtoId = selectedOption.value;
        const produtoNome = selectedOption.text.split(' (Estoque:')[0];
        const quantidade = parseInt(quantidadeInput.value);
        const precoUnitario = parseFloat(precoUnitarioInput.value.replace(/\./g, '').replace(',', '.'));
        const estoqueDisponivel = parseInt(selectedOption.dataset.estoque);

        if (isNaN(quantidade) || quantidade <= 0 || isNaN(precoUnitario) || precoUnitario < 0) { alert('Quantidade e preço devem ser válidos.'); return; }
        if (quantidade > estoqueDisponivel) { alert(`Estoque insuficiente para ${produtoNome}. Disponível: ${estoqueDisponivel}.`); return; }

        const itemExistente = itensDaVenda.find(item => item.id == produtoId);
        if (itemExistente) {
            itemExistente.quantidade += quantidade;
        } else {
            itensDaVenda.push({ id: produtoId, nome: produtoNome, quantidade: quantidade, preco_unitario: precoUnitario });
        }

        renderizarItens();
        produtoSelect.value = '';
        quantidadeInput.value = '1';
        precoUnitarioInput.value = '';
    });

    tabelaItensBody.addEventListener('click', function(e) {
        if (e.target.closest('.remover-item-btn')) {
            const indexToRemove = parseInt(e.target.closest('.remover-item-btn').dataset.index);
            itensDaVenda.splice(indexToRemove, 1);
            renderizarItens();
        }
    });

    tabelaItensBody.addEventListener('change', function(e) {
        const index = e.target.dataset.index;
        if(e.target.classList.contains('item-quantidade')){
            itensDaVenda[index].quantidade = parseInt(e.target.value);
        }
        if(e.target.classList.contains('item-preco')){
            itensDaVenda[index].preco_unitario = parseFloat(e.target.value.replace(/\./g, '').replace(',', '.'));
        }
        renderizarItens();
    });

    document.getElementById('formVenda').addEventListener('submit', function(e) {
        if (itensDaVenda.length === 0) {
            alert('A venda deve conter pelo menos um item.');
            e.preventDefault();
        }
    });

    renderizarItens();
});
</script>