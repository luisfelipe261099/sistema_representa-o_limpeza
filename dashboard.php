<?php
// Inicia a sessão. É o primeiro passo para gerenciar o login do usuário.
session_start();

// Verifica se o usuário está logado. Se não estiver, redireciona para a página de login (index.php).
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Inclui o arquivo de conexão com o banco de dados.
require_once 'includes/db_connect.php';

// --- Lógica para buscar dados dinâmicos para o Dashboard ---

// 1. Total de Vendas Concluídas Hoje
$total_vendas_hoje = 0;
$sql_vendas_hoje = "SELECT SUM(valor_total) FROM vendas WHERE status_venda = 'concluida' AND DATE(data_venda) = CURDATE()";
if ($result = $conn->query($sql_vendas_hoje)) {
    $total_vendas_hoje = $result->fetch_row()[0] ?? 0;
}

// 2. Total de Vendas do Mês
$total_vendas_mes = 0;
$sql_vendas_mes = "SELECT SUM(valor_total) FROM vendas WHERE status_venda = 'concluida' AND MONTH(data_venda) = MONTH(CURDATE()) AND YEAR(data_venda) = YEAR(CURDATE())";
if ($result = $conn->query($sql_vendas_mes)) {
    $total_vendas_mes = $result->fetch_row()[0] ?? 0;
}

// --- NOVOS CÁLCULOS DE LUCRO ---

// 3. Lucro Total de Hoje
$lucro_hoje = 0;
$sql_lucro_hoje = "SELECT SUM(iv.preco_unitario * iv.quantidade * (p.percentual_lucro / 100))
                   FROM vendas v
                   JOIN itens_venda iv ON v.id = iv.venda_id
                   JOIN produtos p ON iv.produto_id = p.id
                   WHERE v.status_venda = 'concluida' AND DATE(v.data_venda) = CURDATE()";
if ($result = $conn->query($sql_lucro_hoje)) {
    $lucro_hoje = $result->fetch_row()[0] ?? 0;
}

// 4. Lucro Total do Mês
$lucro_mes = 0;
$sql_lucro_mes = "SELECT SUM(iv.preco_unitario * iv.quantidade * (p.percentual_lucro / 100))
                  FROM vendas v
                  JOIN itens_venda iv ON v.id = iv.venda_id
                  JOIN produtos p ON iv.produto_id = p.id
                  WHERE v.status_venda = 'concluida' AND MONTH(v.data_venda) = MONTH(CURDATE()) AND YEAR(v.data_venda) = YEAR(CURDATE())";
if ($result = $conn->query($sql_lucro_mes)) {
    $lucro_mes = $result->fetch_row()[0] ?? 0;
}

// 5. Número de Produtos com Estoque Crítico
$produtos_criticos = 0;
$sql_produtos_criticos = "SELECT COUNT(*) FROM produtos WHERE quantidade_estoque <= estoque_minimo";
if ($result = $conn->query($sql_produtos_criticos)) {
    $produtos_criticos = $result->fetch_row()[0] ?? 0;
}

// 6. Total de Produtos
$total_produtos = 0;
$sql_total_produtos = "SELECT COUNT(*) FROM produtos";
if ($result = $conn->query($sql_total_produtos)) {
    $total_produtos = $result->fetch_row()[0] ?? 0;
}

// 7. Número de Agendamentos Próximos
$agendamentos_proximos = 0;
$sql_agendamentos_proximos = "SELECT COUNT(*) FROM agendamentos_entrega WHERE data_hora_entrega BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND (status_entrega = 'agendado' OR status_entrega = 'em_rota')";
if ($result = $conn->query($sql_agendamentos_proximos)) {
    $agendamentos_proximos = $result->fetch_row()[0] ?? 0;
}

// 8. Total de Clientes
$total_clientes = 0;
$sql_total_clientes = "SELECT COUNT(*) FROM clientes";
if ($result = $conn->query($sql_total_clientes)) {
    $total_clientes = $result->fetch_row()[0] ?? 0;
}

// 9. Orçamentos Pendentes
$orcamentos_pendentes = 0;
$sql_orcamentos_pendentes = "SELECT COUNT(*) FROM orcamentos WHERE status_orcamento = 'pendente'";
if ($result = $conn->query($sql_orcamentos_pendentes)) {
    $orcamentos_pendentes = $result->fetch_row()[0] ?? 0;
}

// 10. Vendas e Lucro dos últimos 7 dias para gráfico (SQL ATUALIZADO)
$dados_grafico = [];
$sql_grafico = "SELECT
                    DATE(v.data_venda) as data,
                    SUM(v.valor_total) as total_vendas,
                    SUM(iv.preco_unitario * iv.quantidade * (p.percentual_lucro / 100)) as total_lucro
                FROM vendas v
                JOIN itens_venda iv ON v.id = iv.venda_id
                JOIN produtos p ON iv.produto_id = p.id
                WHERE v.status_venda = 'concluida' AND v.data_venda >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(v.data_venda)
                ORDER BY data ASC";
if ($result = $conn->query($sql_grafico)) {
    while($row = $result->fetch_assoc()) {
        $dados_grafico[] = $row;
    }
}

// 11. Últimas vendas
$ultimas_vendas = [];
$sql_ultimas_vendas = "SELECT v.id, v.data_venda, v.valor_total, c.nome as cliente_nome, v.status_venda
                       FROM vendas v
                       LEFT JOIN clientes c ON v.cliente_id = c.id
                       ORDER BY v.data_venda DESC
                       LIMIT 5";
if ($result = $conn->query($sql_ultimas_vendas)) {
    $ultimas_vendas = $result->fetch_all(MYSQLI_ASSOC);
}

// 12. Produtos com estoque baixo
$produtos_estoque_baixo = [];
$sql_produtos_estoque_baixo = "SELECT nome, quantidade_estoque, estoque_minimo
                               FROM produtos
                               WHERE quantidade_estoque <= estoque_minimo
                               ORDER BY quantidade_estoque ASC
                               LIMIT 5";
if ($result = $conn->query($sql_produtos_estoque_baixo)) {
    $produtos_estoque_baixo = $result->fetch_all(MYSQLI_ASSOC);
}

// Fechar a conexão com o banco de dados
$conn->close();

// Inclui o cabeçalho da página
include_once 'includes/header.php';
?>

<div class="page-header fade-in-up">
    <h1 class="page-title">
        <i class="fas fa-tachometer-alt"></i>
        Dashboard
    </h1>
    <p class="page-subtitle">Visão geral do seu negócio</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stats-card primary fade-in-up">
            <div class="stats-icon primary">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stats-value">R$ <?php echo number_format($total_vendas_hoje, 2, ',', '.'); ?></div>
            <div class="stats-label">Vendas Hoje</div>
            <div class="stats-change positive">
                <i class="fas fa-dollar-sign"></i> Lucro: R$ <?php echo number_format($lucro_hoje, 2, ',', '.'); ?>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stats-card success fade-in-up">
            <div class="stats-icon success">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stats-value">R$ <?php echo number_format($total_vendas_mes, 2, ',', '.'); ?></div>
            <div class="stats-label">Vendas do Mês</div>
            <div class="stats-change positive">
                <i class="fas fa-dollar-sign"></i> Lucro: R$ <?php echo number_format($lucro_mes, 2, ',', '.'); ?>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stats-card warning fade-in-up">
            <div class="stats-icon warning">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stats-value"><?php echo $produtos_criticos; ?></div>
            <div class="stats-label">Estoque Crítico</div>
            <div class="stats-change negative">
                <i class="fas fa-arrow-down"></i> de <?php echo $total_produtos; ?> produtos
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stats-card info fade-in-up">
            <div class="stats-icon info">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-value"><?php echo $total_clientes; ?></div>
            <div class="stats-label">Total de Clientes</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-6 col-md-4">
        <div class="stats-card primary fade-in-up">
            <div class="stats-icon primary">
                 <i class="fas fa-truck"></i>
            </div>
            <div class="stats-value"><?php echo $agendamentos_proximos; ?></div>
            <div class="stats-label">Entregas Próximas</div>
        </div>
    </div>

    <div class="col-6 col-md-4">
        <div class="stats-card warning fade-in-up">
            <div class="stats-icon warning">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stats-value"><?php echo $orcamentos_pendentes; ?></div>
            <div class="stats-label">Orçamentos Pendentes</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-8">
        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-chart-bar"></i>
                Performance dos Últimos 7 Dias
            </div>
            <div class="card-body-modern">
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-exclamation-triangle"></i>
                Produtos com Estoque Baixo
            </div>
            <div class="card-body-modern">
                <?php if (empty($produtos_estoque_baixo)): ?>
                    <div class="text-center py-4">
                        <div class="stats-icon success mx-auto mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h6 class="text-muted">Estoque OK!</h6>
                        <p class="text-muted small">Nenhum produto com estoque crítico.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($produtos_estoque_baixo as $produto): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($produto['nome']); ?></h6>
                                    <small class="text-muted">Mínimo: <?php echo $produto['estoque_minimo']; ?></small>
                                </div>
                                <span class="status-badge status-danger">
                                    <?php echo $produto['quantidade_estoque']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="produtos.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-boxes me-1"></i> Ver Produtos
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="modern-card fade-in-up">
            <div class="card-header-modern">
                <i class="fas fa-shopping-cart"></i>
                Últimas Vendas
                <div class="ms-auto">
                    <a href="registrar_venda.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Nova Venda
                    </a>
                </div>
            </div>
            <div class="card-body-modern">
                <?php if (empty($ultimas_vendas)): ?>
                    <div class="text-center py-5">
                        <div class="stats-icon primary mx-auto mb-3">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h5 class="text-muted mb-2">Nenhuma venda registrada</h5>
                        <p class="text-muted">Suas vendas mais recentes aparecerão aqui.</p>
                        <a href="registrar_venda.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Registrar Primeira Venda
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-modern">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimas_vendas as $venda): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold text-primary">#<?php echo $venda['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                    <?php echo strtoupper(substr($venda['cliente_nome'] ?? 'C', 0, 1)); ?>
                                                </div>
                                                <?php echo htmlspecialchars($venda['cliente_nome'] ?? 'Cliente não informado'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold"><?php echo date('d/m/Y', strtotime($venda['data_venda'])); ?></div>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($venda['data_venda'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($venda['status_venda']) {
                                                case 'concluida':
                                                    $status_class = 'status-success';
                                                    $status_text = 'Concluída';
                                                    break;
                                                case 'pendente':
                                                    $status_class = 'status-warning';
                                                    $status_text = 'Pendente';
                                                    break;
                                                case 'cancelada':
                                                    $status_class = 'status-danger';
                                                    $status_text = 'Cancelada';
                                                    break;
                                                default:
                                                    $status_class = 'status-info';
                                                    $status_text = ucfirst($venda['status_venda']);
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="detalhes_venda.php?id=<?php echo $venda['id']; ?>"
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-4">
                        <a href="vendas.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i> Ver Todas as Vendas
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('performanceChart');
    if (ctx) {
        <?php
        $labels = [];
        $valores_vendas = [];
        $valores_lucro = [];

        $data_map = [];
        foreach ($dados_grafico as $dado) {
            $data_map[$dado['data']] = $dado;
        }

        for ($i = 6; $i >= 0; $i--) {
            $data_chave = date('Y-m-d', strtotime("-$i days"));
            $labels[] = "'" . date('d/m', strtotime($data_chave)) . "'";
            
            $valores_vendas[] = $data_map[$data_chave]['total_vendas'] ?? 0;
            $valores_lucro[] = $data_map[$data_chave]['total_lucro'] ?? 0;
        }
        ?>

        const performanceData = {
            labels: [<?php echo implode(', ', $labels); ?>],
            datasets: [
                {
                    label: 'Receita (Vendas)',
                    data: [<?php echo implode(', ', $valores_vendas); ?>],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                },
                {
                    label: 'Lucro',
                    data: [<?php echo implode(', ', $valores_lucro); ?>],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                }
            ]
        };

        new Chart(ctx, {
            type: 'line',
            data: performanceData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return 'R$ ' + value.toLocaleString('pt-BR'); }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>