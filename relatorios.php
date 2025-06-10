<?php
// Habilita a exibição de erros para encontrar problemas facilmente. REMOVER em ambiente de produção.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

// --- FUNÇÕES DE GERAÇÃO DE RELATÓRIO (COM CORREÇÃO DE COMPATIBILIDADE) ---

function gerarRelatorioVendasGeral($conn, $di, $df, $cid, $status) {
    $sql = "SELECT v.id, v.data_venda, v.valor_total, v.status_venda, c.nome as cliente_nome,
                   COALESCE(SUM(iv.quantidade * iv.preco_unitario * (p.percentual_lucro / 100)), 0) as lucro_total
            FROM vendas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN itens_venda iv ON v.id = iv.venda_id
            LEFT JOIN produtos p ON iv.produto_id = p.id
            WHERE v.data_venda BETWEEN ? AND ?";
    $params = [$di . ' 00:00:00', $df . ' 23:59:59'];
    $types = "ss";

    if ($cid) { $sql .= " AND v.cliente_id = ?"; $params[] = $cid; $types .= "i"; }
    if ($status) { $sql .= " AND v.status_venda = ?"; $params[] = $status; $types .= "s"; }

    $sql .= " GROUP BY v.id, c.nome ORDER BY v.data_venda DESC";
    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $dados = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $vendas_concluidas = array_filter($dados, function($v) { return $v['status_venda'] == 'concluida'; });
    $total_vendas = count($dados);
    $total_valor = array_sum(array_column($vendas_concluidas, 'valor_total'));
    $total_lucro = array_sum(array_column($vendas_concluidas, 'lucro_total'));

    return ['dados' => $dados, 'resumo' => ['total_vendas' => $total_vendas, 'faturamento' => $total_valor, 'lucro_bruto' => $total_lucro, 'margem_media' => $total_valor > 0 ? ($total_lucro / $total_valor) * 100 : 0]];
}

function gerarRelatorioProdutosVendidos($conn, $di, $df, $eid, $pid) {
    $sql = "SELECT p.id, p.nome as produto_nome, e.nome_empresa as empresa_nome,
                   SUM(iv.quantidade) as total_vendido,
                   SUM(iv.quantidade * iv.preco_unitario) as total_faturado,
                   SUM(iv.quantidade * iv.preco_unitario * (p.percentual_lucro / 100)) as total_lucro
            FROM itens_venda iv
            JOIN produtos p ON iv.produto_id = p.id
            LEFT JOIN empresas_representadas e ON p.empresa_id = e.id
            JOIN vendas v ON iv.venda_id = v.id
            WHERE v.data_venda BETWEEN ? AND ? AND v.status_venda = 'concluida'";
    $params = [$di . ' 00:00:00', $df . ' 23:59:59'];
    $types = "ss";

    if ($eid) { $sql .= " AND p.empresa_id = ?"; $params[] = $eid; $types .= "i"; }
    if ($pid) { $sql .= " AND p.id = ?"; $params[] = $pid; $types .= "i"; }
    
    $sql .= " GROUP BY p.id, p.nome, e.nome_empresa ORDER BY total_faturado DESC";
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $dados = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_faturamento = array_sum(array_column($dados, 'total_faturado'));
    $total_lucro = array_sum(array_column($dados, 'total_lucro'));

    return ['dados' => $dados, 'resumo' => ['total_faturamento' => $total_faturamento, 'total_lucro' => $total_lucro, 'margem_geral' => $total_faturamento > 0 ? ($total_lucro / $total_faturamento) * 100 : 0, 'total_produtos' => count($dados)]];
}

// ===== FUNÇÃO DE LUCRATIVIDADE CORRIGIDA =====
function gerarRelatorioLucratividade($conn, $di, $df) {
    $data_inicio_completa = $di . ' 00:00:00';
    $data_fim_completa = $df . ' 23:59:59';

    // 1. Receita Bruta
    $receita_bruta = 0;
    $sql_receita = "SELECT COALESCE(SUM(valor_total), 0) FROM vendas WHERE status_venda = 'concluida' AND data_venda BETWEEN ? AND ?";
    $stmt_receita = $conn->prepare($sql_receita);
    // CORREÇÃO: Passando variáveis em vez de valores temporários
    $stmt_receita->bind_param("ss", $data_inicio_completa, $data_fim_completa);
    $stmt_receita->execute();
    $stmt_receita->bind_result($receita_bruta);
    $stmt_receita->fetch();
    $stmt_receita->close();

    // 2. Lucro Bruto
    $lucro_bruto = 0;
    $sql_lucro = "SELECT COALESCE(SUM(iv.quantidade * iv.preco_unitario * (p.percentual_lucro / 100)), 0)
                  FROM vendas v
                  JOIN itens_venda iv ON v.id = iv.venda_id
                  JOIN produtos p ON iv.produto_id = p.id
                  WHERE v.status_venda = 'concluida' AND v.data_venda BETWEEN ? AND ?";
    $stmt_lucro = $conn->prepare($sql_lucro);
    // CORREÇÃO: Passando variáveis em vez de valores temporários
    $stmt_lucro->bind_param("ss", $data_inicio_completa, $data_fim_completa);
    $stmt_lucro->execute();
    $stmt_lucro->bind_result($lucro_bruto);
    $stmt_lucro->fetch();
    $stmt_lucro->close();

    // 3. Transações Manuais
    $outras_entradas = 0;
    $despesas = 0;
    $sql_transacoes = "SELECT tipo, SUM(valor) as total FROM transacoes_financeiras WHERE data_transacao BETWEEN ? AND ? GROUP BY tipo";
    $stmt_transacoes = $conn->prepare($sql_transacoes);
    // CORREÇÃO: Passando variáveis em vez de valores temporários
    $stmt_transacoes->bind_param("ss", $data_inicio_completa, $data_fim_completa);
    $stmt_transacoes->execute();
    $result_transacoes = $stmt_transacoes->get_result();
    while($row = $result_transacoes->fetch_assoc()) {
        if ($row['tipo'] == 'entrada') {
            $outras_entradas = $row['total'];
        } else {
            $despesas = $row['total'];
        }
    }
    $stmt_transacoes->close();

    // 4. Consolidação dos Resultados
    $lucro_liquido = $lucro_bruto + $outras_entradas - $despesas;
    $dados = [
        ['item' => 'Receita Bruta de Vendas', 'valor' => $receita_bruta, 'tipo' => 'entrada'],
        ['item' => 'Lucro Bruto das Vendas', 'valor' => $lucro_bruto, 'tipo' => 'entrada'],
        ['item' => 'Outras Entradas (Manuais)', 'valor' => $outras_entradas, 'tipo' => 'entrada'],
        ['item' => 'Despesas (Saídas Manuais)', 'valor' => $despesas, 'tipo' => 'saida'],
        ['item' => 'LUCRO LÍQUIDO', 'valor' => $lucro_liquido, 'tipo' => 'saldo']
    ];
    $resumo = ['lucro_liquido' => $lucro_liquido, 'margem_liquida' => $receita_bruta > 0 ? ($lucro_liquido / $receita_bruta) * 100 : 0, 'lucro_bruto' => $lucro_bruto, 'receita_bruta' => $receita_bruta];
    
    return ['dados' => $dados, 'resumo' => $resumo];
}


// --- Bloco Principal de Processamento ---
$relatorio_gerado = false;
$dados_relatorio = [];
$titulo_relatorio = '';
$tipo_relatorio = $_POST["tipo_relatorio"] ?? '';
$data_inicio = $_POST["data_inicio"] ?? date('Y-m-01');
$data_fim = $_POST["data_fim"] ?? date('Y-m-d');
$cliente_id = $_POST["cliente_id"] ?? null;
$status_filtro = $_POST["status_filtro"] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($tipo_relatorio)) {
    // Definir $empresa_id e $produto_id aqui, mesmo que não estejam no formulário principal, para evitar erros
    $empresa_id = $_POST["empresa_id"] ?? null;
    $produto_id = $_POST["produto_id"] ?? null;
    
    switch ($tipo_relatorio) {
        case 'vendas_geral':
            $dados_relatorio = gerarRelatorioVendasGeral($conn, $data_inicio, $data_fim, $cliente_id, $status_filtro);
            $titulo_relatorio = "Relatório de Vendas Detalhadas";
            break;
        case 'produtos_vendidos':
            $dados_relatorio = gerarRelatorioProdutosVendidos($conn, $data_inicio, $data_fim, $empresa_id, $produto_id);
            $titulo_relatorio = "Relatório de Produtos Vendidos";
            break;
        case 'lucratividade':
            $dados_relatorio = gerarRelatorioLucratividade($conn, $data_inicio, $data_fim);
            $titulo_relatorio = "Relatório de Lucratividade";
            break;
    }
    $relatorio_gerado = true;
}

// Buscar dados para os filtros
$clientes_options = $conn->query("SELECT id, nome FROM clientes ORDER BY nome ASC");

$conn->close();
include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title"><i class="fas fa-chart-bar"></i> Central de Relatórios</h1>
    <p class="page-subtitle">Analise a performance do seu negócio com relatórios detalhados.</p>
</div>

<div class="modern-card fade-in-up">
    <div class="card-body-modern">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-6 col-lg-3">
                    <label for="tipo_relatorio" class="form-label">1. Tipo de Relatório</label>
                    <select class="form-select" id="tipo_relatorio" name="tipo_relatorio" required>
                        <option value="lucratividade" <?php echo ($tipo_relatorio == 'lucratividade' ? 'selected' : ''); ?>>💰 Lucratividade (Financeiro)</option>
                        <option value="vendas_geral" <?php echo ($tipo_relatorio == 'vendas_geral' ? 'selected' : ''); ?>>📊 Vendas Detalhadas</option>
                        <option value="produtos_vendidos" <?php echo ($tipo_relatorio == 'produtos_vendidos' ? 'selected' : ''); ?>>📦 Produtos Vendidos</option>
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="data_inicio" class="form-label">2. Data Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" required>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="data_fim" class="form-label">3. Data Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" required>
                </div>
                <div class="col-md-4 col-lg-2">
                     <label for="cliente_id" class="form-label">Filtro (Opcional)</label>
                    <select class="form-select" id="cliente_id" name="cliente_id"><option value="">Todos os Clientes</option><?php if($clientes_options) { $clientes_options->data_seek(0); while($c = $clientes_options->fetch_assoc()) echo "<option value='{$c['id']}' ".($cliente_id==$c['id']?'selected':'').">".htmlspecialchars($c['nome'])."</option>"; } ?></select>
                </div>
                 <div class="col-md-4 col-lg-2">
                    <select class="form-select" name="status_filtro" aria-label="Filtro de Status">
                        <option value="">Todos os Status</option>
                        <option value="concluida" <?php echo ($status_filtro == 'concluida' ? 'selected' : ''); ?>>Concluída</option>
                        <option value="pendente" <?php echo ($status_filtro == 'pendente' ? 'selected' : ''); ?>>Pendente</option>
                        <option value="cancelada" <?php echo ($status_filtro == 'cancelada' ? 'selected' : ''); ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-4 col-lg-1 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($relatorio_gerado && !empty($dados_relatorio)): ?>
<div class="modern-card fade-in-up mt-4">
    <div class="card-header-modern"><i class="fas fa-file-alt"></i> <?php echo $titulo_relatorio; ?><div class="ms-auto"><button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="fas fa-print me-1"></i>Imprimir</button></div></div>
    <div class="card-body-modern">
        <div class="row g-4 mb-4">
            <?php if ($tipo_relatorio == 'lucratividade'): ?>
                <div class="col-6 col-lg-3"><div class="stats-card success"><div class="stats-icon"><i class="fas fa-dollar-sign"></i></div><div class="stats-value">R$ <?php echo number_format($dados_relatorio['resumo']['lucro_liquido'], 2, ',', '.'); ?></div><div class="stats-label">Lucro Líquido</div></div></div>
                <div class="col-6 col-lg-3"><div class="stats-card info"><div class="stats-icon"><i class="fas fa-percentage"></i></div><div class="stats-value"><?php echo number_format($dados_relatorio['resumo']['margem_liquida'], 2, ',', '.'); ?>%</div><div class="stats-label">Margem Líquida</div></div></div>
                <div class="col-6 col-lg-3"><div class="stats-card primary"><div class="stats-icon"><i class="fas fa-chart-line"></i></div><div class="stats-value">R$ <?php echo number_format($dados_relatorio['resumo']['receita_bruta'], 2, ',', '.'); ?></div><div class="stats-label">Receita Bruta</div></div></div>
                <div class="col-6 col-lg-3"><div class="stats-card warning"><div class="stats-icon"><i class="fas fa-chart-pie"></i></div><div class="stats-value">R$ <?php echo number_format($dados_relatorio['resumo']['lucro_bruto'], 2, ',', '.'); ?></div><div class="stats-label">Lucro Bruto</div></div></div>
            <?php elseif ($tipo_relatorio == 'vendas_geral'): ?>
                <div class="col-6 col-lg-3"><div class="stats-card primary"><div class="stats-icon"><i class="fas fa-shopping-cart"></i></div><div class="stats-value"><?php echo $dados_relatorio['resumo']['total_vendas']; ?></div><div class="stats-label">Vendas no Período</div></div></div>
                <div class="col-6 col-lg-3"><div class="stats-card success"><div class="stats-icon"><i class="fas fa-dollar-sign"></i></div><div class="stats-value">R$ <?php echo number_format($dados_relatorio['resumo']['faturamento'], 2, ',', '.'); ?></div><div class="stats-label">Faturamento</div></div></div>
                <div class="col-6 col-lg-3"><div class="stats-card info"><div class="stats-icon"><i class="fas fa-chart-pie"></i></div><div class="stats-value">R$ <?php echo number_format($dados_relatorio['resumo']['lucro_bruto'], 2, ',', '.'); ?></div><div class="stats-label">Lucro Bruto</div></div></div>
                <div class="col-6 col-lg-3"><div class="stats-card warning"><div class="stats-icon"><i class="fas fa-percentage"></i></div><div class="stats-value"><?php echo number_format($dados_relatorio['resumo']['margem_media'], 1, ',', '.'); ?>%</div><div class="stats-label">Margem Média</div></div></div>
            <?php elseif ($tipo_relatorio == 'produtos_vendidos'): ?>
                 <div class="col-6 col-lg-3"><div class="stats-card success"><div class="stats-icon"><i class="fas fa-dollar-sign"></i></div><div class="stats-value">R$ <?php echo number_format($dados_relatorio['resumo']['total_faturamento'], 2, ',', '.'); ?></div><div class="stats-label">Faturamento Total</div></div></div>
                 <div class="col-6 col-lg-3"><div class="stats-card info"><div class="stats-icon"><i class="fas fa-chart-pie"></i></div><div class="stats-value">R$ <?php echo number_format($dados_relatorio['resumo']['total_lucro'], 2, ',', '.'); ?></div><div class="stats-label">Lucro Total</div></div></div>
                 <div class="col-6 col-lg-3"><div class="stats-card warning"><div class="stats-icon"><i class="fas fa-percentage"></i></div><div class="stats-value"><?php echo number_format($dados_relatorio['resumo']['margem_geral'], 1, ',', '.'); ?>%</div><div class="stats-label">Margem Geral</div></div></div>
                 <div class="col-6 col-lg-3"><div class="stats-card primary"><div class="stats-icon"><i class="fas fa-boxes"></i></div><div class="stats-value"><?php echo $dados_relatorio['resumo']['total_produtos']; ?></div><div class="stats-label">Produtos Distintos</div></div></div>
            <?php endif; ?>
        </div>
        
        <div class="table-responsive d-none d-lg-block">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <?php if($tipo_relatorio == 'lucratividade'): ?><th>Item Financeiro</th><th class="text-end">Valor</th><?php endif; ?>
                        <?php if($tipo_relatorio == 'vendas_geral'): ?><th>Venda</th><th>Data</th><th>Cliente</th><th class="text-end">Faturamento</th><th class="text-end">Lucro</th><th class="text-end">Margem</th><th>Status</th><?php endif; ?>
                        <?php if($tipo_relatorio == 'produtos_vendidos'): ?><th>Produto</th><th class="text-end">Qtd.</th><th class="text-end">Faturamento</th><th class="text-end">Lucro</th><th class="text-end">Margem</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados_relatorio['dados'] as $row): ?>
                        <tr>
                        <?php if($tipo_relatorio == 'lucratividade'): ?>
                            <td><i class="fas fa-<?php echo $row['tipo'] == 'entrada' ? 'plus-circle text-success' : ($row['tipo'] == 'saida' ? 'minus-circle text-danger' : 'equals text-primary'); ?> me-2"></i><?php echo $row['item']; ?></td>
                            <td class="text-end fw-bold <?php echo $row['tipo'] == 'saldo' ? 'text-primary' : ''; ?>">R$ <?php echo number_format($row['valor'], 2, ',', '.'); ?></td>
                        <?php elseif($tipo_relatorio == 'vendas_geral'): ?>
                            <td>#<?php echo $row['id']; ?></td><td><?php echo date('d/m/Y', strtotime($row['data_venda'])); ?></td><td><?php echo htmlspecialchars($row['cliente_nome']); ?></td>
                            <td class="text-end">R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                            <td class="text-end text-success">R$ <?php echo number_format($row['lucro_total'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo $row['valor_total'] > 0 ? number_format(($row['lucro_total'] / $row['valor_total']) * 100, 1) : 0; ?>%</td>
                            <td><span class="badge bg-<?php echo ($row['status_venda'] == 'concluida') ? 'success' : (($row['status_venda'] == 'pendente') ? 'warning text-dark' : 'danger'); ?>"><?php echo ucfirst($row['status_venda']); ?></span></td>
                        <?php elseif($tipo_relatorio == 'produtos_vendidos'): ?>
                            <td><?php echo htmlspecialchars($row['produto_nome']); ?></td><td class="text-end fw-bold"><?php echo $row['total_vendido']; ?></td>
                            <td class="text-end">R$ <?php echo number_format($row['total_faturado'], 2, ',', '.'); ?></td>
                            <td class="text-end text-success">R$ <?php echo number_format($row['total_lucro'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo $row['total_faturado'] > 0 ? number_format(($row['total_lucro'] / $row['total_faturado']) * 100, 1) : 0; ?>%</td>
                        <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="d-lg-none">
             <ul class="list-group list-group-flush">
                <?php foreach ($dados_relatorio['dados'] as $row): ?>
                    <li class="list-group-item px-0 py-3">
                    <?php if($tipo_relatorio == 'lucratividade'): ?>
                        <div class="d-flex w-100 justify-content-between"><h6 class="mb-1"><?php echo $row['item']; ?></h6><span class="fw-bold text-<?php echo $row['tipo'] == 'entrada' ? 'success' : ($row['tipo'] == 'saida' ? 'danger' : 'primary'); ?>">R$ <?php echo number_format($row['valor'], 2, ',', '.'); ?></span></div>
                    <?php elseif($tipo_relatorio == 'vendas_geral'): ?>
                        <div class="d-flex w-100 justify-content-between"><h6 class="mb-1">Venda #<?php echo $row['id']; ?></h6><span class="text-success fw-bold">R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></span></div>
                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($row['cliente_nome']); ?> - <?php echo date('d/m/Y', strtotime($row['data_venda'])); ?></p>
                        <small>Lucro: <span class="text-info">R$ <?php echo number_format($row['lucro_total'], 2, ',', '.'); ?></span> (<span class="text-muted"><?php echo $row['valor_total'] > 0 ? number_format(($row['lucro_total'] / $row['valor_total']) * 100, 1) : 0; ?>%</span>)</small>
                    <?php elseif($tipo_relatorio == 'produtos_vendidos'): ?>
                        <div class="d-flex w-100 justify-content-between"><h6 class="mb-1"><?php echo htmlspecialchars($row['produto_nome']); ?></h6><span class="text-success fw-bold">R$ <?php echo number_format($row['total_faturado'], 2, ',', '.'); ?></span></div>
                        <p class="mb-1 text-muted">Qtd: <?php echo $row['total_vendido']; ?> | Lucro: <span class="text-info">R$ <?php echo number_format($row['total_lucro'], 2, ',', '.'); ?></span></p>
                    <?php endif; ?>
                    </li>
                <?php endforeach; ?>
             </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>