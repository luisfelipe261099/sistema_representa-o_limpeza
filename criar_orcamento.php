<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db_connect.php';

// Verificar se as colunas de pagamento existem
$colunas_pagamento_existem = true;
$result_check = $conn->query("SHOW COLUMNS FROM orcamentos LIKE 'forma_pagamento'");
if (!$result_check || $result_check->num_rows == 0) {
    $colunas_pagamento_existem = false;
}

$orcamento_id = $cliente_id = $data_orcamento = $valor_total = $status_orcamento = $observacoes = "";
$forma_pagamento = $tipo_faturamento = $data_vencimento = "";
$title = "Criar Novo Orçamento";
$submit_button_text = "Criar Orçamento";
$message = '';
$message_type = '';
$itens_do_orcamento = []; // Array para armazenar os produtos do orçamento (para edição)

// Buscar todos os clientes para o campo SELECT
$clientes_options = $conn->query("SELECT id, nome FROM clientes ORDER BY nome ASC");

// Buscar todas as empresas para o filtro
$empresas_options = $conn->query("SELECT id, nome_empresa FROM empresas_representadas ORDER BY nome_empresa ASC");

// Buscar todos os produtos para o campo SELECT na adição de itens (incluindo empresa)
$produtos_options = $conn->query("SELECT p.id, p.nome, p.preco_venda, p.empresa_id, e.nome_empresa
                                  FROM produtos p
                                  LEFT JOIN empresas_representadas e ON p.empresa_id = e.id
                                  ORDER BY e.nome_empresa ASC, p.nome ASC");

// Processar formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $orcamento_id = trim($_POST["orcamento_id"] ?? '');
    $cliente_id = trim($_POST["cliente_id"]);
    $status_orcamento = trim($_POST["status_orcamento"]);
    $observacoes = trim($_POST["observacoes"]);
    $forma_pagamento = trim($_POST["forma_pagamento"]);
    $tipo_faturamento = trim($_POST["tipo_faturamento"]);
    $data_vencimento = trim($_POST["data_vencimento"]);
    $itens_selecionados_json = $_POST["itens_selecionados_json"] ?? '[]'; // Itens do orçamento em JSON

    // Decodifica os itens selecionados
    $itens_do_orcamento_post = json_decode($itens_selecionados_json, true);

    // Recalcula o valor total com base nos itens enviados (segurança)
    $calculated_valor_total = 0;
    foreach ($itens_do_orcamento_post as $item) {
        $calculated_valor_total += ($item['preco_unitario'] * $item['quantidade']);
    }
    $valor_total = $calculated_valor_total; // Usamos o valor recalculado

    // Validação básica
    if (empty($cliente_id) || empty($status_orcamento) || empty($itens_do_orcamento_post)) {
        $message = "Por favor, preencha todos os campos obrigatórios e adicione pelo menos um produto ao orçamento.";
        $message_type = "danger";
    } else {
        $conn->begin_transaction(); // Inicia transação

        try {
            if (empty($orcamento_id)) { // Novo Orçamento
                if ($colunas_pagamento_existem) {
                    $sql = "INSERT INTO orcamentos (cliente_id, valor_total, status_orcamento, observacoes, forma_pagamento, tipo_faturamento, data_vencimento, data_orcamento) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    if ($stmt = $conn->prepare($sql)) {
                        $data_vencimento_param = !empty($data_vencimento) ? $data_vencimento : null;
                        $stmt->bind_param("idsssss", $cliente_id, $valor_total, $status_orcamento, $observacoes, $forma_pagamento, $tipo_faturamento, $data_vencimento_param);
                        if (!$stmt->execute()) {
                            throw new Exception("Erro ao registrar orçamento: " . $stmt->error);
                        }
                        $orcamento_id = $conn->insert_id;
                        $stmt->close();
                    } else {
                        throw new Exception("Erro na preparação da query de inserção do orçamento: " . $conn->error);
                    }
                } else {
                    // Versão sem colunas de pagamento
                    $sql = "INSERT INTO orcamentos (cliente_id, valor_total, status_orcamento, observacoes, data_orcamento) VALUES (?, ?, ?, ?, NOW())";
                    if ($stmt = $conn->prepare($sql)) {
                        $stmt->bind_param("idss", $cliente_id, $valor_total, $status_orcamento, $observacoes);
                        if (!$stmt->execute()) {
                            throw new Exception("Erro ao registrar orçamento: " . $stmt->error);
                        }
                        $orcamento_id = $conn->insert_id;
                        $stmt->close();
                    } else {
                        throw new Exception("Erro na preparação da query de inserção do orçamento: " . $conn->error);
                    }
                }
            } else { // Editar Orçamento Existente
                // Exclui os itens antigos do orçamento para inserir os novos
                $sql_delete_old_items = "DELETE FROM itens_orcamento WHERE orcamento_id = ?";
                $stmt_delete_old_items = $conn->prepare($sql_delete_old_items);
                $stmt_delete_old_items->bind_param("i", $orcamento_id);
                if (!$stmt_delete_old_items->execute()) {
                    throw new Exception("Erro ao deletar itens antigos do orçamento: " . $stmt_delete_old_items->error);
                }
                $stmt_delete_old_items->close();

                // Atualiza os dados do orçamento
                if ($colunas_pagamento_existem) {
                    $sql = "UPDATE orcamentos SET cliente_id = ?, valor_total = ?, status_orcamento = ?, observacoes = ?, forma_pagamento = ?, tipo_faturamento = ?, data_vencimento = ? WHERE id = ?";
                    if ($stmt = $conn->prepare($sql)) {
                        $data_vencimento_param = !empty($data_vencimento) ? $data_vencimento : null;
                        $stmt->bind_param("idssssi", $cliente_id, $valor_total, $status_orcamento, $observacoes, $forma_pagamento, $tipo_faturamento, $data_vencimento_param, $orcamento_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Erro ao atualizar orçamento: " . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        throw new Exception("Erro na preparação da query de atualização do orçamento: " . $conn->error);
                    }
                } else {
                    // Versão sem colunas de pagamento
                    $sql = "UPDATE orcamentos SET cliente_id = ?, valor_total = ?, status_orcamento = ?, observacoes = ? WHERE id = ?";
                    if ($stmt = $conn->prepare($sql)) {
                        $stmt->bind_param("idssi", $cliente_id, $valor_total, $status_orcamento, $observacoes, $orcamento_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Erro ao atualizar orçamento: " . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        throw new Exception("Erro na preparação da query de atualização do orçamento: " . $conn->error);
                    }
                }
            }

            // Inserir/Atualizar Itens do Orçamento
            foreach ($itens_do_orcamento_post as $item) {
                $produto_id = $item['id'];
                $quantidade = $item['quantidade'];
                $preco_unitario = $item['preco_unitario'];

                $sql_item = "INSERT INTO itens_orcamento (orcamento_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
                if ($stmt_item = $conn->prepare($sql_item)) {
                    $stmt_item->bind_param("iiid", $orcamento_id, $produto_id, $quantidade, $preco_unitario);
                    if (!$stmt_item->execute()) {
                        throw new Exception("Erro ao inserir item do orçamento: " . $stmt_item->error);
                    }
                    $stmt_item->close();
                } else {
                    throw new Exception("Erro na preparação da query de item do orçamento: " . $conn->error);
                }
            }

            $conn->commit(); // Confirma todas as operações
            $message = (empty($_GET["id"]) ? "Orçamento criado com sucesso!" : "Orçamento atualizado com sucesso!");
            $message_type = "success";
            header("location: orcamentos.php?message=" . urlencode($message) . "&type=" . $message_type);
            exit;

        } catch (Exception $e) {
            $conn->rollback(); // Reverte todas as operações em caso de erro
            $message = "Erro na transação: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Preencher formulário para edição se um ID for passado via GET
$orcamento_id_get = $_GET["id"] ?? '';
if (!empty($orcamento_id_get) && empty($message)) {
    $orcamento_id = $orcamento_id_get;
    $title = "Editar Orçamento";
    $submit_button_text = "Atualizar Orçamento";

    // Busca os dados do orçamento principal
    if ($colunas_pagamento_existem) {
        $sql_orcamento = "SELECT id, cliente_id, valor_total, status_orcamento, observacoes, forma_pagamento, tipo_faturamento, data_vencimento FROM orcamentos WHERE id = ?";
    } else {
        $sql_orcamento = "SELECT id, cliente_id, valor_total, status_orcamento, observacoes FROM orcamentos WHERE id = ?";
    }

    if ($stmt_orcamento = $conn->prepare($sql_orcamento)) {
        $stmt_orcamento->bind_param("i", $orcamento_id);
        $stmt_orcamento->execute();
        $result_orcamento = $stmt_orcamento->get_result();
        if ($result_orcamento->num_rows == 1) {
            $row_orcamento = $result_orcamento->fetch_assoc();
            $cliente_id = $row_orcamento['cliente_id'];
            $valor_total = $row_orcamento['valor_total'];
            $status_orcamento = $row_orcamento['status_orcamento'];
            $observacoes = $row_orcamento['observacoes'];

            if ($colunas_pagamento_existem) {
                $forma_pagamento = $row_orcamento['forma_pagamento'] ?? '';
                $tipo_faturamento = $row_orcamento['tipo_faturamento'] ?? '';
                $data_vencimento = $row_orcamento['data_vencimento'] ?? '';
            }
        } else {
            $message = "Orçamento não encontrado.";
            $message_type = "danger";
            $orcamento_id = "";
            $title = "Criar Novo Orçamento";
            $submit_button_text = "Criar Orçamento";
        }
        $stmt_orcamento->close();
    } else {
        $message = "Erro ao buscar orçamento para edição: " . $conn->error;
        $message_type = "danger";
    }

    // Busca os itens do orçamento
    $sql_itens = "SELECT io.produto_id, p.nome AS produto_nome, io.quantidade, io.preco_unitario
                  FROM itens_orcamento io
                  JOIN produtos p ON io.produto_id = p.id
                  WHERE io.orcamento_id = ?";
    if ($stmt_itens = $conn->prepare($sql_itens)) {
        $stmt_itens->bind_param("i", $orcamento_id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();
        while ($item = $result_itens->fetch_assoc()) {
            $itens_do_orcamento[] = [
                'id' => $item['produto_id'],
                'nome' => $item['produto_nome'],
                'quantidade' => $item['quantidade'],
                'preco_unitario' => $item['preco_unitario']
            ];
        }
        $stmt_itens->close();
    } else {
        $message = "Erro ao buscar itens do orçamento: " . $conn->error;
        $message_type = "danger";
    }
}

// Lógica para carregar orçamento para Venda (se veio de `orcamentos.php` com `from_orcamento_id`)
$from_orcamento_id = $_GET["from_orcamento_id"] ?? '';
if (!empty($from_orcamento_id) && empty($message)) { // Prioriza edição de venda ou nova venda se id não encontrado
    $sql_orcamento_to_sale = "SELECT o.cliente_id, o.status_orcamento, io.produto_id, p.nome AS produto_nome, io.quantidade, io.preco_unitario
                              FROM orcamentos o
                              JOIN itens_orcamento io ON o.id = io.orcamento_id
                              JOIN produtos p ON io.produto_id = p.id
                              WHERE o.id = ?";
    if ($stmt_orcamento_to_sale = $conn->prepare($sql_orcamento_to_sale)) {
        $stmt_orcamento_to_sale->bind_param("i", $from_orcamento_id);
        $stmt_orcamento_to_sale->execute();
        $result_orcamento_to_sale = $stmt_orcamento_to_sale->get_result();

        if ($result_orcamento_to_sale->num_rows > 0) {
            $itens_do_orcamento_convert = [];
            while ($row = $result_orcamento_to_sale->fetch_assoc()) {
                if (empty($cliente_id)) { // Pega o cliente apenas uma vez
                    $cliente_id = $row['cliente_id'];
                    // O status do orçamento pode influenciar no status inicial da venda
                    $status_venda = 'pendente'; // Começa como pendente
                    if ($row['status_orcamento'] == 'aprovado') {
                         $status_venda = 'concluida'; // Se o orçamento foi aprovado, a venda pode ser concluída
                    }
                }
                $itens_do_orcamento_convert[] = [
                    'id' => $row['produto_id'],
                    'nome' => $row['produto_nome'],
                    'quantidade' => $row['quantidade'],
                    'preco_unitario' => $row['preco_unitario']
                ];
            }
            $itens_do_orcamento = $itens_do_orcamento_convert; // Popula a lista de itens do formulário
            $message = "Itens carregados do Orçamento #" . $from_orcamento_id . ". Verifique as informações antes de registrar a venda.";
            $message_type = "info";

            // Se for carregado de orçamento, muda o título e botão para "Registrar Venda"
            // Isso aqui é para a página registrar_venda.php, então o código de cima estaria lá.
            // Aqui em criar_orcamento.php, o from_orcamento_id é usado para preencher a VENDA, não o ORÇAMENTO.
            // Então, este bloco de código deveria estar em registrar_venda.php, não aqui.
            // Vou manter essa parte para a página criar_orcamento.php focada em CRIAR/EDITAR orçamentos.
            // A lógica de conversão para venda será na página registrar_venda.php

        } else {
            $message = "Orçamento para conversão não encontrado ou sem itens.";
            $message_type = "warning";
        }
        $stmt_orcamento_to_sale->close();
    } else {
        $message = "Erro ao preparar consulta para conversão de orçamento: " . $conn->error;
        $message_type = "danger";
    }
}

// Não fechar a conexão aqui pois ainda vamos usar no HTML
// $conn->close();

include_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-<?php echo ($orcamento_id ? 'edit' : 'plus-circle'); ?>"></i>
        <?php echo $title; ?>
    </h1>
    <p class="page-subtitle">
        <?php echo $orcamento_id ? 'Atualize os dados do orçamento' : 'Crie um novo orçamento para seus clientes'; ?>
    </p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show fade-in-up" role="alert">
        <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'info' ? 'info-circle' : 'exclamation-triangle'); ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Form Card -->
<div class="modern-card fade-in-up">
    <div class="card-header-modern">
        <i class="fas fa-file-invoice-dollar"></i>
        Dados do Orçamento
    </div>
    <div class="card-body-modern">
        <form id="formOrcamento" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="orcamento_id" value="<?php echo htmlspecialchars($orcamento_id); ?>">
            <input type="hidden" name="itens_selecionados_json" id="itens_selecionados_json" value='<?php echo json_encode($itens_do_orcamento); ?>'>

            <!-- Informações Básicas -->
            <div class="row g-4">
                <div class="col-12">
                    <h5 class="text-primary mb-3">
                        <i class="fas fa-info-circle me-2"></i>Informações Básicas
                    </h5>
                </div>

                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente *</label>
                    <select class="form-control" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione um cliente...</option>
                        <?php
                        if ($clientes_options && $clientes_options->num_rows > 0) {
                            $clientes_options->data_seek(0); // Reseta o ponteiro se já foi lido
                            while($cliente = $clientes_options->fetch_assoc()) {
                                $selected = ($cliente['id'] == $cliente_id) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($cliente['id']) . '" ' . $selected . '>' . htmlspecialchars($cliente['nome']) . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>Nenhum cliente cadastrado.</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="status_orcamento" class="form-label">Status do Orçamento *</label>
                    <select class="form-control" id="status_orcamento" name="status_orcamento" required>
                        <option value="pendente" <?php echo ($status_orcamento == 'pendente' ? 'selected' : ''); ?>>Pendente</option>
                        <option value="aprovado" <?php echo ($status_orcamento == 'aprovado' ? 'selected' : ''); ?>>Aprovado</option>
                        <option value="rejeitado" <?php echo ($status_orcamento == 'rejeitado' ? 'selected' : ''); ?>>Rejeitado</option>
                        <option value="convertido_venda" <?php echo ($status_orcamento == 'convertido_venda' ? 'selected' : ''); ?>>Convertido em Venda</option>
                    </select>
                </div>

                <?php if ($colunas_pagamento_existem): ?>
                <!-- Seção de Pagamento -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-credit-card me-2"></i>Informações de Pagamento
                    </h5>
                </div>

                <div class="col-md-4">
                    <label for="forma_pagamento" class="form-label">Forma de Pagamento *</label>
                    <select class="form-control" id="forma_pagamento" name="forma_pagamento" required>
                        <option value="faturamento" <?php echo ($forma_pagamento == 'faturamento' ? 'selected' : ''); ?>>Faturamento</option>
                        <option value="pix" <?php echo ($forma_pagamento == 'pix' ? 'selected' : ''); ?>>PIX</option>
                        <option value="debito" <?php echo ($forma_pagamento == 'debito' ? 'selected' : ''); ?>>Cartão de Débito</option>
                        <option value="credito" <?php echo ($forma_pagamento == 'credito' ? 'selected' : ''); ?>>Cartão de Crédito</option>
                        <option value="dinheiro" <?php echo ($forma_pagamento == 'dinheiro' ? 'selected' : ''); ?>>Dinheiro</option>
                    </select>
                </div>

                <div class="col-md-4" id="tipo_faturamento_container">
                    <label for="tipo_faturamento" class="form-label">Tipo de Faturamento</label>
                    <select class="form-control" id="tipo_faturamento" name="tipo_faturamento">
                        <option value="avista" <?php echo ($tipo_faturamento == 'avista' ? 'selected' : ''); ?>>À Vista</option>
                        <option value="15_dias" <?php echo ($tipo_faturamento == '15_dias' ? 'selected' : ''); ?>>15 dias</option>
                        <option value="20_dias" <?php echo ($tipo_faturamento == '20_dias' ? 'selected' : ''); ?>>20 dias</option>
                        <option value="30_dias" <?php echo ($tipo_faturamento == '30_dias' ? 'selected' : ''); ?>>30 dias</option>
                        <option value="45_dias" <?php echo ($tipo_faturamento == '45_dias' ? 'selected' : ''); ?>>45 dias</option>
                        <option value="60_dias" <?php echo ($tipo_faturamento == '60_dias' ? 'selected' : ''); ?>>60 dias</option>
                        <option value="90_dias" <?php echo ($tipo_faturamento == '90_dias' ? 'selected' : ''); ?>>90 dias</option>
                    </select>
                </div>

                <div class="col-md-4" id="data_vencimento_container">
                    <label for="data_vencimento" class="form-label">Data de Vencimento</label>
                    <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" value="<?php echo htmlspecialchars($data_vencimento); ?>">
                </div>
                <?php else: ?>
                <!-- Aviso sobre funcionalidades de pagamento -->
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Funcionalidades de Pagamento:</strong> Para habilitar as opções de forma de pagamento (PIX, débito, crédito, faturamento),
                        execute o arquivo <code>alteracoes_banco_melhorias.sql</code> no seu banco de dados.
                        <br><br>
                        <a href="verificar_estrutura_banco.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-database me-1"></i>Verificar Estrutura do Banco
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
                              placeholder="Informações adicionais sobre o orçamento..."><?php echo htmlspecialchars($observacoes); ?></textarea>
                </div>

                <!-- Itens do Orçamento -->
                <div class="col-12">
                    <h5 class="text-primary mb-3 mt-4">
                        <i class="fas fa-boxes me-2"></i>Itens do Orçamento
                    </h5>
                </div>
            </div>

            <!-- Adicionar Produtos -->
            <div class="modern-card mt-4">
                <div class="card-header-modern">
                    <i class="fas fa-plus-circle"></i>
                    Adicionar Produtos
                </div>
                <div class="card-body-modern">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="empresa_filter" class="form-label">Filtrar por Empresa</label>
                            <select class="form-control" id="empresa_filter">
                                <option value="">Todas as empresas</option>
                                <?php
                                if ($empresas_options && $empresas_options->num_rows > 0) {
                                    $empresas_options->data_seek(0);
                                    while($empresa = $empresas_options->fetch_assoc()) {
                                        echo '<option value="' . htmlspecialchars($empresa['id']) . '">' . htmlspecialchars($empresa['nome_empresa']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="produto_select" class="form-label">Produto</label>
                            <select class="form-control" id="produto_select">
                                <option value="">Selecione um produto...</option>
                                <?php
                                if ($produtos_options && $produtos_options->num_rows > 0) {
                                    $produtos_options->data_seek(0); // Reseta o ponteiro
                                    while($produto = $produtos_options->fetch_assoc()) {
                                        $empresa_nome = $produto['nome_empresa'] ? ' (' . $produto['nome_empresa'] . ')' : '';
                                        echo '<option value="' . htmlspecialchars($produto['id']) . '" data-preco="' . htmlspecialchars($produto['preco_venda']) . '" data-empresa="' . htmlspecialchars($produto['empresa_id']) . '">' . htmlspecialchars($produto['nome']) . $empresa_nome . '</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>Nenhum produto cadastrado.</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="quantidade_item" class="form-label">Quantidade</label>
                            <input type="number" class="form-control" id="quantidade_item" value="1" min="1">
                        </div>
                        <div class="col-md-2">
                            <label for="preco_unitario_item" class="form-label">Preço Unit.</label>
                            <input type="text" class="form-control" id="preco_unitario_item" value="0,00" placeholder="0,00">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-primary w-100" id="addItemBtn">
                                <i class="fas fa-plus-circle me-1"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Itens -->
            <div class="modern-card mt-4">
                <div class="card-header-modern">
                    <i class="fas fa-list"></i>
                    Itens do Orçamento
                    <div class="ms-auto">
                        <span class="badge bg-primary" id="totalItens">0 itens</span>
                    </div>
                </div>
                <div class="card-body-modern">
                    <div class="table-modern">
                        <table class="table table-hover mb-0" id="tabelaItens">
                            <thead>
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
                                <tr class="table-primary">
                                    <th colspan="3" class="text-end">Total do Orçamento:</th>
                                    <th id="valor_total_display">R$ 0,00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="orcamentos.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-<?php echo ($orcamento_id ? 'save' : 'check'); ?> me-2"></i><?php echo $submit_button_text; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
include_once 'includes/footer.php';
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const produtoSelect = document.getElementById('produto_select');
        const empresaFilter = document.getElementById('empresa_filter');
        const quantidadeInput = document.getElementById('quantidade_item');
        const precoUnitarioInput = document.getElementById('preco_unitario_item');
        const addItemBtn = document.getElementById('addItemBtn');
        const tabelaItensBody = document.querySelector('#tabelaItens tbody');
        const valorTotalDisplay = document.getElementById('valor_total_display');
        const itensSelecionadosJsonInput = document.getElementById('itens_selecionados_json');
        const formaPagamentoSelect = document.getElementById('forma_pagamento');
        const tipoFaturamentoContainer = document.getElementById('tipo_faturamento_container');
        const dataVencimentoContainer = document.getElementById('data_vencimento_container');
        const colunasPagamentoExistem = <?php echo $colunas_pagamento_existem ? 'true' : 'false'; ?>;

        let itensDoOrcamento = JSON.parse(itensSelecionadosJsonInput.value || '[]');
        let todosProdutos = []; // Array para armazenar todos os produtos

        // Carregar todos os produtos no array
        Array.from(produtoSelect.options).forEach(option => {
            if (option.value) {
                todosProdutos.push({
                    value: option.value,
                    text: option.textContent,
                    preco: option.dataset.preco,
                    empresa: option.dataset.empresa || ''
                });
            }
        });

        function formatarMoeda(valor) {
            return parseFloat(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        // Função para controlar visibilidade dos campos de pagamento
        function controlarCamposPagamento() {
            if (!colunasPagamentoExistem || !formaPagamentoSelect) return;

            const formaPagamento = formaPagamentoSelect.value;

            if (formaPagamento === 'faturamento') {
                if (tipoFaturamentoContainer) tipoFaturamentoContainer.style.display = 'block';
                if (dataVencimentoContainer) dataVencimentoContainer.style.display = 'block';
            } else {
                if (tipoFaturamentoContainer) tipoFaturamentoContainer.style.display = 'none';
                if (dataVencimentoContainer) dataVencimentoContainer.style.display = 'none';
            }
        }

        // Função para filtrar produtos por empresa
        function filtrarProdutos() {
            const empresaSelecionada = empresaFilter.value;

            // Limpar o select de produtos
            produtoSelect.innerHTML = '<option value="">Selecione um produto...</option>';

            // Filtrar e adicionar produtos
            todosProdutos.forEach(produto => {
                if (!empresaSelecionada || produto.empresa === empresaSelecionada) {
                    const option = document.createElement('option');
                    option.value = produto.value;
                    option.textContent = produto.text;
                    option.dataset.preco = produto.preco;
                    option.dataset.empresa = produto.empresa;
                    produtoSelect.appendChild(option);
                }
            });
        }

        function calcularTotal() {
            let total = 0;
            itensDoOrcamento.forEach(item => {
                total += item.quantidade * item.preco_unitario;
            });
            valorTotalDisplay.textContent = formatarMoeda(total);
            itensSelecionadosJsonInput.value = JSON.stringify(itensDoOrcamento);
        }

        function renderizarItens() {
            tabelaItensBody.innerHTML = '';
            itensDoOrcamento.forEach((item, index) => {
                const subtotal = item.quantidade * item.preco_unitario;
                const row = `
                    <tr>
                        <td>${item.nome}</td>
                        <td>${item.quantidade}</td>
                        <td>${formatarMoeda(item.preco_unitario)}</td>
                        <td>${formatarMoeda(subtotal)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm remover-item-btn" data-index="${index}" title="Remover">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tabelaItensBody.insertAdjacentHTML('beforeend', row);
            });

            // Atualizar contador de itens
            const totalItensElement = document.getElementById('totalItens');
            if (totalItensElement) {
                totalItensElement.textContent = itensDoOrcamento.length + ' itens';
            }

            calcularTotal();
        }

        produtoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                precoUnitarioInput.value = parseFloat(selectedOption.dataset.preco).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                quantidadeInput.value = 1;
            } else {
                precoUnitarioInput.value = '0,00';
            }
        });

        addItemBtn.addEventListener('click', function() {
            const selectedOption = produtoSelect.options[produtoSelect.selectedIndex];
            const produtoId = selectedOption.value;
            const produtoNome = selectedOption.textContent;
            const quantidade = parseInt(quantidadeInput.value);
            const precoUnitario = parseFloat(precoUnitarioInput.value.replace(',', '.'));

            if (!produtoId || isNaN(quantidade) || quantidade <= 0 || isNaN(precoUnitario) || precoUnitario < 0) {
                alert('Por favor, selecione um produto e insira quantidades e preços válidos.');
                return;
            }

            let itemExistente = itensDoOrcamento.find(item => item.id == produtoId);
            if (itemExistente) {
                itemExistente.quantidade += quantidade;
                itemExistente.preco_unitario = precoUnitario; // Atualiza o preço caso tenha mudado
            } else {
                itensDoOrcamento.push({
                    id: produtoId,
                    nome: produtoNome,
                    quantidade: quantidade,
                    preco_unitario: precoUnitario
                });
            }

            renderizarItens();
            produtoSelect.value = '';
            quantidadeInput.value = '1';
            precoUnitarioInput.value = '0,00';
        });

        tabelaItensBody.addEventListener('click', function(event) {
            if (event.target.classList.contains('remover-item-btn') || event.target.closest('.remover-item-btn')) {
                const button = event.target.classList.contains('remover-item-btn') ? event.target : event.target.closest('.remover-item-btn');
                const indexToRemove = parseInt(button.dataset.index);
                itensDoOrcamento.splice(indexToRemove, 1);
                renderizarItens();
            }
        });

        document.getElementById('formOrcamento').addEventListener('submit', function(event) {
            if (itensDoOrcamento.length === 0) {
                alert('Por favor, adicione pelo menos um produto ao orçamento.');
                event.preventDefault();
            }
        });

        // Event listeners para os novos campos
        if (formaPagamentoSelect) {
            formaPagamentoSelect.addEventListener('change', controlarCamposPagamento);
        }
        if (empresaFilter) {
            empresaFilter.addEventListener('change', filtrarProdutos);
        }

        // Inicializar controles
        controlarCamposPagamento();
        renderizarItens();
    });
</script>