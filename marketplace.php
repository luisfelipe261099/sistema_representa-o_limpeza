<?php
session_start();

require_once 'includes/db_connect.php'; //

// Verificar token de acesso
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('Acesso negado. Token inválido.');
}

// Validar token e buscar dados do cliente
// Adicionado ml.ativo = 1 para garantir que apenas links ativos funcionem
$sql_token = "SELECT ml.*, c.id as cliente_id_db, c.nome as cliente_nome, c.email as cliente_email, c.endereco as cliente_endereco, c.cidade as cliente_cidade, c.estado as cliente_estado, c.cep as cliente_cep, c.telefone as cliente_telefone, c.tipo_pessoa, c.cpf_cnpj, c.data_cadastro
              FROM marketplace_links ml
              LEFT JOIN clientes c ON ml.cliente_id = c.id
              WHERE ml.token_acesso = ? AND ml.ativo = 1"; //
$stmt_token = $conn->prepare($sql_token);
if (!$stmt_token) {
    die("Erro ao preparar consulta do token: " . $conn->error);
}
$stmt_token->bind_param("s", $token);
$stmt_token->execute();
$result_token = $stmt_token->get_result();

if ($result_token->num_rows === 0) {
    die('Link inválido, expirado ou desativado.');
}

$link_data = $result_token->fetch_assoc();
$cliente_id = $link_data['cliente_id_db']; // ID do cliente para buscar pedidos e dados
$stmt_token->close();

// Verificar se o link expirou (se data_expiracao estiver definida)
if ($link_data['data_expiracao'] && strtotime($link_data['data_expiracao']) < time()) {
    die('Link expirado.');
}

// Atualizar último acesso e contador de acessos do link
$sql_update_link = "UPDATE marketplace_links SET ultimo_acesso = NOW(), total_acessos = total_acessos + 1 WHERE token_acesso = ?"; //
$stmt_update_link = $conn->prepare($sql_update_link);
if ($stmt_update_link) {
    $stmt_update_link->bind_param("s", $token);
    $stmt_update_link->execute();
    $stmt_update_link->close();
}

// Buscar configurações do marketplace
$sql_config = "SELECT chave, valor FROM marketplace_configuracoes"; //
$result_config = $conn->query($sql_config);
$config_marketplace = [];
if ($result_config) {
    while ($row_config = $result_config->fetch_assoc()) {
        $config_marketplace[$row_config['chave']] = $row_config['valor'];
    }
}

// Verificar se marketplace está ativo
if (!isset($config_marketplace['marketplace_ativo']) || $config_marketplace['marketplace_ativo'] != '1') { //
    die('Marketplace temporariamente indisponível.');
}

// Buscar produtos ativos para marketplace considerando empresas permitidas para o cliente
$sql_produtos = "SELECT p.*, e.nome_empresa
                 FROM produtos p
                 LEFT JOIN empresas_representadas e ON p.empresa_id = e.id
                 WHERE p.ativo_marketplace = 1 AND p.quantidade_estoque > 0";

// Verificar se o cliente tem empresas específicas associadas
$sql_check_empresas = "SELECT COUNT(*) as total FROM marketplace_cliente_empresas WHERE cliente_id = ? AND ativo = 1";
$stmt_check = $conn->prepare($sql_check_empresas);
$stmt_check->bind_param("i", $cliente_id);
$stmt_check->execute();
$empresas_associadas = $stmt_check->get_result()->fetch_assoc()['total'];

if ($empresas_associadas > 0) {
    // Cliente tem empresas específicas - mostrar apenas produtos dessas empresas
    $sql_produtos .= " AND p.empresa_id IN (
                        SELECT empresa_id FROM marketplace_cliente_empresas
                        WHERE cliente_id = ? AND ativo = 1
                      )";
}

$sql_produtos .= " ORDER BY p.destaque_marketplace DESC, p.ordem_exibicao ASC, p.nome ASC";

if ($empresas_associadas > 0) {
    $stmt_produtos = $conn->prepare($sql_produtos);
    $stmt_produtos->bind_param("i", $cliente_id);
    $stmt_produtos->execute();
    $result_produtos = $stmt_produtos->get_result();
} else {
    $result_produtos = $conn->query($sql_produtos);
}

if (!$result_produtos) {
    die("Erro ao buscar produtos: " . $conn->error);
}


// Buscar itens do carrinho para este token
$sql_carrinho = "SELECT mc.*, p.nome as produto_nome, p.preco_venda, p.quantidade_estoque, p.imagem_url
                 FROM marketplace_carrinho mc
                 LEFT JOIN produtos p ON mc.produto_id = p.id
                 WHERE mc.token_acesso = ?"; //
$stmt_carrinho = $conn->prepare($sql_carrinho);
if (!$stmt_carrinho) {
    die("Erro ao preparar consulta do carrinho: " . $conn->error);
}
$stmt_carrinho->bind_param("s", $token);
$stmt_carrinho->execute();
$result_carrinho = $stmt_carrinho->get_result();

$carrinho_itens = [];
$total_carrinho_valor = 0;
while ($item_carrinho = $result_carrinho->fetch_assoc()) {
    $carrinho_itens[] = $item_carrinho;
    $total_carrinho_valor += $item_carrinho['quantidade'] * $item_carrinho['preco_unitario'];
}
$stmt_carrinho->close();
$total_itens_no_carrinho = 0;
foreach($carrinho_itens as $ci) { // Soma as quantidades dos itens
    $total_itens_no_carrinho += $ci['quantidade'];
}


// Buscar histórico de pedidos do cliente
$sql_pedidos_hist = "SELECT mp.id, mp.numero_pedido, mp.data_pedido, mp.valor_total, mp.status_pedido,
                           (SELECT COUNT(*) FROM marketplace_itens_pedido mip WHERE mip.pedido_id = mp.id) as qtd_itens
                    FROM marketplace_pedidos mp
                    WHERE mp.cliente_id = ?
                    ORDER BY mp.data_pedido DESC"; //
$stmt_pedidos_hist = $conn->prepare($sql_pedidos_hist);
if (!$stmt_pedidos_hist) {
    die("Erro ao preparar consulta de histórico: " . $conn->error);
}
$stmt_pedidos_hist->bind_param("i", $cliente_id);
$stmt_pedidos_hist->execute();
$result_pedidos_hist = $stmt_pedidos_hist->get_result();
$historico_pedidos = [];
while ($pedido_hist = $result_pedidos_hist->fetch_assoc()) {
    $historico_pedidos[] = $pedido_hist;
}
$stmt_pedidos_hist->close();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config_marketplace['titulo_marketplace'] ?? 'Marketplace'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: <?php echo $config_marketplace['cor_primaria'] ?? '#2563eb'; ?>; /* Azul mais vibrante */
            --secondary-color: <?php echo $config_marketplace['cor_secundaria'] ?? '#334155'; ?>; /* Cinza ardósia escuro */
            --accent-color: <?php echo $config_marketplace['cor_acento'] ?? '#f97316'; ?>; /* Laranja */
            --success-color: #10b981;
            --warning-color: #facc15; /* Amarelo */
            --danger-color: #ef4444;
            --light-gray: #f3f4f6; /* Cinza mais claro para fundo */
            --medium-gray: #d1d5db; /* Cinza para bordas */
            --dark-gray: #4b5563; /* Cinza para texto secundário */
            --text-color: #1f2937; /* Cinza ardósia mais escuro para texto principal */
            --font-primary: 'Poppins', sans-serif;
            --font-secondary: 'Roboto', sans-serif;
        }

        body {
            font-family: var(--font-secondary);
            background: var(--light-gray);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-wrapper {
            flex-grow: 1;
        }

        .marketplace-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3.5rem 0;
            text-align: center;
            border-bottom: 5px solid var(--accent-color);
        }
        .marketplace-header h1 {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 3rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }
        .marketplace-header .welcome-box {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            display: inline-block;
            margin-top: 1.5rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .marketplace-header .welcome-box strong {
            font-weight: 600;
        }

        .nav-tabs-marketplace .nav-link {
            font-family: var(--font-primary);
            font-weight: 600;
            color: var(--dark-gray);
            border: none;
            border-bottom: 4px solid transparent;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
            font-size: 1.05rem;
            margin-right: 0.5rem;
        }
        .nav-tabs-marketplace .nav-link.active,
        .nav-tabs-marketplace .nav-link:hover {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: rgba(0,0,0,0.02);
        }
        .nav-tabs-marketplace {
            border-bottom: 1px solid var(--medium-gray);
            margin-bottom: 2.5rem; /* Mais espaço */
        }

        .product-card {
            border: 1px solid var(--medium-gray);
            border-radius: 18px; /* Mais arredondado */
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08);
            transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
            height: 100%;
            background-color: #fff;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-8px) scale(1.02); /* Efeito de zoom sutil */
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
        }
        .product-image-wrapper {
            height: 250px; /* Aumentado */
            overflow: hidden;
            border-radius: 18px 18px 0 0;
            background: #fff; /* Fundo branco para imagem */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px; /* Pequeno padding interno */
        }
        .product-image-wrapper img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain; /* Para ver a imagem inteira */
            border-radius: 8px; /* Borda suave na imagem */
        }
        .product-image-placeholder {
            font-size: 4rem; /* Aumentado */
            color: var(--primary-color);
            opacity: 0.6;
        }
        .product-card .card-body {
            padding: 1.75rem; /* Aumentado */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-grow: 1;
        }
        .product-card .card-title {
            font-family: var(--font-primary);
            font-weight: 600;
            font-size: 1.2rem; /* Aumentado */
            margin-bottom: 0.75rem; /* Ajustado */
            color: var(--secondary-color);
            line-height: 1.3;
        }
        .product-card .text-price {
            font-family: var(--font-primary);
            font-size: 1.75rem; /* Aumentado */
            font-weight: 700;
            color: var(--primary-color);
        }
        .btn-add-cart {
            background: var(--primary-color);
            border: none;
            border-radius: 12px; /* Mais arredondado */
            padding: 0.7rem 1.4rem; /* Ajustado */
            color: white;
            font-weight: 600; /* Mais forte */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .btn-add-cart:hover {
            background: var(--accent-color); /* Usar cor de acento no hover */
            transform: translateY(-3px) scale(1.05); /* Efeito mais pronunciado */
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }
        .product-card .badge.bg-warning { /* Ajuste para badge de destaque */
            background-color: var(--accent-color) !important;
            color: white !important;
        }
        .product-card .small.text-muted { font-size: 0.8rem; }

        .cart-sidebar {
            position: fixed;
            right: -480px; /* Aumentado para mais espaço */
            top: 0;
            width: 480px; /* Aumentado */
            height: 100vh;
            background: #fff; /* Fundo branco */
            box-shadow: -8px 0 30px rgba(0, 0, 0, 0.15); /* Sombra mais suave */
            transition: right 0.45s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 1050;
            display: flex;
            flex-direction: column;
        }
        .cart-sidebar.open { right: 0; }
        .cart-header, .cart-footer {
            padding: 1.75rem; /* Aumentado */
            border-bottom: 1px solid var(--medium-gray);
            background-color: var(--light-gray); /* Fundo levemente cinza */
        }
        .cart-footer { border-top: 1px solid var(--medium-gray); border-bottom: none; }
        .cart-header h5 { font-family: var(--font-primary); font-weight: 600; font-size: 1.3rem; }
        .cart-body {
            flex-grow: 1;
            overflow-y: auto;
            padding: 1.75rem; /* Aumentado */
        }
        .cart-item {
            display: flex;
            gap: 1.25rem; /* Aumentado */
            align-items: center;
        }
        .cart-item-img {
            width: 80px; /* Aumentado */
            height: 80px; /* Aumentado */
            border-radius: 12px; /* Mais arredondado */
            background: var(--light-gray);
            object-fit: cover;
            border: 1px solid var(--medium-gray);
        }
        .cart-item-img-placeholder {
            width: 80px; height: 80px; border-radius: 12px; background: var(--light-gray);
            display:flex; align-items:center; justify-content:center;
            font-size: 1.8rem; color: var(--dark-gray); border: 1px solid var(--medium-gray);
        }

        .cart-item-info h6 { font-size: 1rem; font-weight: 600; margin-bottom: 0.3rem; color: var(--text-color); }
        .cart-item-info .text-price-sm { font-size: 0.95rem; color: var(--primary-color); font-weight: 500; }
        .cart-item-actions .form-control { width: 70px; text-align: center; font-size:1rem; padding:0.4rem; border-radius: 8px; }
        .cart-item-actions .btn { border-radius: 8px; }

        .floating-cart {
            position: fixed;
            bottom: 35px; /* Ajustado */
            right: 35px; /* Ajustado */
            z-index: 1030;
        }
        .floating-cart .btn {
            width: 65px; /* Aumentado */
            height: 65px; /* Aumentado */
            box-shadow: 0 8px 20px rgba(0,0,0,0.25); /* Sombra mais forte */
            font-size: 1.3rem; /* Ícone maior */
            background-color: var(--accent-color); /* Usar cor de acento */
            border: none;
        }
        .floating-cart .btn:hover {
            background-color: var(--primary-color);
        }
        .badge-cart {
            position: absolute;
            top: -8px; /* Ajustado */
            right: -8px; /* Ajustado */
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 28px; /* Aumentado */
            height: 28px; /* Aumentado */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem; /* Aumentado */
            font-weight: 700; /* Mais forte */
            border: 2px solid white; /* Borda branca */
        }
        .cart-overlay { /* Adicionado */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6); /* Mais escuro */
            z-index: 1040;
            display: none;
            opacity: 0;
            transition: opacity 0.4s ease-in-out;
        }
        .cart-overlay.show { display: block; opacity: 1; }


        .form-control-sm-custom {
            height: calc(1.5em + .6rem + 2px); padding: .3rem .6rem; font-size: .9rem; border-radius: .25rem;
        }
        .btn-sm-custom { padding: .3rem .6rem; font-size: .9rem; border-radius: .25rem; }
        .filter-bar {
            background-color: #fff;
            padding: 1.5rem; /* Aumentado */
            border-radius: 16px; /* Mais arredondado */
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            margin-bottom: 2.5rem; /* Aumentado */
        }
        .form-control-filter, .form-select-filter {
            border-radius: 12px; /* Mais arredondado */
            padding: 0.6rem 1rem;
        }

        .data-card {
            background-color: #fff;
            padding: 2.5rem; /* Aumentado */
            border-radius: 16px; /* Mais arredondado */
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            margin-bottom: 2rem; /* Aumentado */
        }
        .data-card h5 {
            font-family: var(--font-primary);
            color: var(--primary-color);
            margin-bottom: 2rem; /* Aumentado */
            border-bottom: 3px solid var(--primary-color); /* Mais espesso */
            padding-bottom: 0.75rem; /* Aumentado */
            display:inline-block;
            font-size: 1.4rem; /* Aumentado */
        }
        .data-label {
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        .data-value {
            color: var(--text-color);
            font-size: 1.05rem;
            margin-bottom: 1.2rem;
        }
        .order-history-table th {
            font-family: var(--font-primary);
            font-weight:600;
            background-color: var(--light-gray);
            color: var(--secondary-color);
        }
        .order-history-table td {
            vertical-align: middle;
        }
        .status-badge {
            padding: 0.4em 0.9em; /* Ajustado */
            font-size: 0.75rem; /* Pequeno, mas legível */
            font-weight: 600; /* Mais forte */
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pendente { background-color: var(--warning-color); color: #333; } /* Ajustar contraste */
        .status-confirmado { background-color: var(--primary-color); color: white; }
        .status-preparando { background-color: #06b6d4; color: white; }
        .status-entregue { background-color: var(--success-color); color: white; }
        .status-cancelado { background-color: var(--danger-color); color: white; }

        .modal-header-marketplace {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
        }
        .modal-header-marketplace .btn-close { filter: brightness(0) invert(1); }
        .modal-body-marketplace h6 { color: var(--primary-color); font-weight: 600; font-size:1.1rem; }
        .modal-footer { border-top: 1px solid var(--medium-gray); }

        /* Estilos para a área de notificação */
        #notification-area {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060; /* Acima do modal do carrinho */
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        #notification-area .alert {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .footer-marketplace {
            background-color: var(--secondary-color);
            color: var(--medium-gray);
            padding: 2rem 0;
            margin-top: 3rem;
            font-size: 0.9rem;
        }
        .footer-marketplace a {
            color: var(--accent-color);
            text-decoration: none;
        }
        .footer-marketplace a:hover {
            text-decoration: underline;
        }

    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="marketplace-header">
            <div class="container">
                <h1 class="mb-2">
                    <i class="fas fa-store-alt me-3"></i> <?php echo htmlspecialchars($config_marketplace['titulo_marketplace'] ?? 'Marketplace Exclusivo'); ?>
                </h1>
                <p class="opacity-85 mb-0 fs-5"> <?php echo htmlspecialchars($config_marketplace['descricao_marketplace'] ?? 'Sua loja online personalizada.'); ?>
                </p>
                <div class="welcome-box">
                    <h6 class="mb-0 small">Bem-vindo(a), <strong><?php echo htmlspecialchars($link_data['cliente_nome']); ?>!</strong></h6>
                </div>
            </div>
        </div>

        <div class="container mt-4">
            <ul class="nav nav-tabs nav-tabs-marketplace" id="marketplaceTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="loja-tab" data-bs-toggle="tab" data-bs-target="#loja-tab-pane" type="button" role="tab" aria-controls="loja-tab-pane" aria-selected="true">
                        <i class="fas fa-shopping-bag me-2"></i>Loja
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos-tab-pane" type="button" role="tab" aria-controls="pedidos-tab-pane" aria-selected="false">
                        <i class="fas fa-receipt me-2"></i>Meus Pedidos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="conta-tab" data-bs-toggle="tab" data-bs-target="#conta-tab-pane" type="button" role="tab" aria-controls="conta-tab-pane" aria-selected="false">
                        <i class="fas fa-user-circle me-2"></i>Minha Conta
                    </button>
                </li>
            </ul>
            <div class="tab-content pt-3" id="marketplaceTabContent">
                <div class="tab-pane fade show active" id="loja-tab-pane" role="tabpanel" aria-labelledby="loja-tab" tabindex="0">
                    <div class="filter-bar row g-3 align-items-center">
                        <div class="col-lg-7 col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control form-control-filter border-start-0" placeholder="O que você procura?" id="searchInput">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4">
                            <select class="form-select form-select-filter" id="filterEmpresa">
                                <option value="">Todas as Empresas</option>
                                <?php
                                $sql_empresas_filtro = "SELECT DISTINCT e.id, e.nome_empresa
                                                 FROM empresas_representadas e
                                                 INNER JOIN produtos p ON e.id = p.empresa_id
                                                 WHERE p.ativo_marketplace = 1
                                                 ORDER BY e.nome_empresa"; //
                                $result_empresas_filtro = $conn->query($sql_empresas_filtro);
                                if($result_empresas_filtro){
                                    while ($empresa_filtro = $result_empresas_filtro->fetch_assoc()) {
                                        echo '<option value="' . $empresa_filtro['id'] . '">' . htmlspecialchars($empresa_filtro['nome_empresa']) . '</option>';
                                    }
                                    $result_empresas_filtro->close();
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2">
                             <button class="btn btn-outline-secondary w-100" type="button" onclick="document.getElementById('searchInput').value=''; document.getElementById('filterEmpresa').value=''; filtrarProdutos();">
                                <i class="fas fa-times me-1"></i> Limpar
                            </button>
                        </div>
                    </div>

                    <div class="row gy-4 gx-xl-4 gx-lg-3 gx-md-3 gx-sm-3" id="produtos-container">
                        <?php
                        if ($result_produtos && $result_produtos->num_rows > 0) {
                            while ($produto = $result_produtos->fetch_assoc()) {
                                ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 produto-item" data-empresa="<?php echo $produto['empresa_id']; ?>" data-nome="<?php echo strtolower(htmlspecialchars($produto['nome'])); ?>">
                                    <div class="card product-card">
                                        <div class="product-image-wrapper">
                                            <?php
                                            // Priorizar imagem_produto sobre imagem_url
                                            $imagem_url = '';
                                            if (!empty($produto['imagem_produto']) && file_exists("uploads/produtos/" . $produto['imagem_produto'])) {
                                                $imagem_url = "uploads/produtos/" . $produto['imagem_produto'];
                                            } elseif (!empty($produto['imagem_url']) && filter_var($produto['imagem_url'], FILTER_VALIDATE_URL)) {
                                                $imagem_url = $produto['imagem_url'];
                                            }

                                            if ($imagem_url): ?>
                                                <img src="<?php echo htmlspecialchars($imagem_url); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="img-fluid">
                                            <?php else: ?>
                                                <i class="fas fa-tags product-image-placeholder"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <div>
                                                <?php if ($produto['destaque_marketplace'] == 1): ?>
                                                    <span class="badge bg-warning text-dark mb-2 small">
                                                        <i class="fas fa-star"></i> Destaque
                                                    </span>
                                                <?php endif; ?>
                                                <h3 class="card-title"><?php echo htmlspecialchars($produto['nome']); ?></h3>
                                                <p class="small text-muted mb-1">
                                                    <i class="fas fa-barcode me-1"></i> Cód: <?php echo htmlspecialchars($produto['sku'] ?? 'N/D'); ?>
                                                </p>
                                                <?php if ($produto['nome_empresa']): ?>
                                                    <p class="small text-muted mb-2">
                                                        <i class="fas fa-industry me-1"></i> <?php echo htmlspecialchars($produto['nome_empresa']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($produto['descricao']): ?>
                                                    <p class="card-text small text-muted mb-3" style="min-height: 40px;"> <?php echo htmlspecialchars(mb_substr($produto['descricao'], 0, 60)) . (mb_strlen($produto['descricao']) > 60 ? '...' : ''); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-auto">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <p class="text-price mb-0">
                                                        R$ <?php echo number_format($produto['preco_venda'], 2, ',', '.'); ?>
                                                    </p>
                                                    <button class="btn btn-add-cart" onclick="adicionarAoCarrinho(<?php echo $produto['id']; ?>, '<?php echo htmlspecialchars(addslashes($produto['nome'])); ?>', <?php echo $produto['preco_venda']; ?>, <?php echo $produto['quantidade_estoque']; ?>, '<?php echo htmlspecialchars($produto['imagem_url'] ?? ''); ?>')" <?php echo ($produto['quantidade_estoque'] <= 0 ? 'disabled' : '');?>>
                                                        <i class="fas fa-cart-plus me-1"></i> Add
                                                    </button>
                                                </div>
                                                <small class="text-muted d-block text-end mt-1 <?php echo ($produto['quantidade_estoque'] <= ($produto['estoque_minimo'] ?? 0) && $produto['quantidade_estoque'] > 0 ? 'text-danger fw-bold' : ''); ?>">
                                                    <?php echo ($produto['quantidade_estoque'] > 0 ? "Estoque: " . $produto['quantidade_estoque'] : '<span class="text-danger fw-bold">Indisponível</span>'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="col-12 text-center py-5"><p class="lead text-muted">Nenhum produto disponível no momento.</p></div>';
                        }
                        if ($result_produtos) $result_produtos->data_seek(0); // Resetar ponteiro se for usar novamente
                        ?>
                    </div>
                </div>

                <div class="tab-pane fade" id="pedidos-tab-pane" role="tabpanel" aria-labelledby="pedidos-tab" tabindex="0">
                    <div class="data-card">
                        <h5><i class="fas fa-history me-2"></i>Histórico de Pedidos</h5>
                        <?php if (empty($historico_pedidos)): ?>
                            <p class="text-muted lead mt-3">Você ainda não realizou nenhum pedido.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover order-history-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Nº Pedido</th>
                                            <th>Data</th>
                                            <th class="text-center">Itens</th>
                                            <th class="text-end">Valor Total</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historico_pedidos as $pedido_h): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($pedido_h['numero_pedido']); ?></strong></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pedido_h['data_pedido'])); ?></td>
                                                <td class="text-center"><?php echo $pedido_h['qtd_itens']; ?></td>
                                                <td class="text-end fw-bold">R$ <?php echo number_format($pedido_h['valor_total'], 2, ',', '.'); ?></td>
                                                <td class="text-center">
                                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($pedido_h['status_pedido'])); ?>">
                                                        <?php echo ucfirst(htmlspecialchars(str_replace('_', ' ', $pedido_h['status_pedido']))); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesPedido(<?php echo $pedido_h['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>Detalhes
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-pane fade" id="conta-tab-pane" role="tabpanel" aria-labelledby="conta-tab" tabindex="0">
                    <div class="data-card">
                        <h5><i class="fas fa-id-card me-2"></i>Meus Dados Cadastrais</h5>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <p class="data-label mb-1">Nome Completo / Razão Social:</p>
                                <p class="data-value"><?php echo htmlspecialchars($link_data['cliente_nome']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="data-label mb-1">Email:</p>
                                <p class="data-value"><?php echo htmlspecialchars($link_data['cliente_email']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="data-label mb-1">Telefone:</p>
                                <p class="data-value"><?php echo htmlspecialchars($link_data['cliente_telefone'] ?? 'Não informado'); ?></p>
                            </div>
                             <div class="col-md-6">
                                <p class="data-label mb-1">Tipo de Pessoa:</p>
                                <p class="data-value"><?php echo ucfirst(htmlspecialchars($link_data['tipo_pessoa'] ?? 'Não informado')); ?></p>
                            </div>
                            <?php if (!empty($link_data['cpf_cnpj'])): ?>
                            <div class="col-md-6">
                                <p class="data-label mb-1"><?php echo $link_data['tipo_pessoa'] == 'fisica' ? 'CPF:' : 'CNPJ:'; ?></p>
                                <p class="data-value"><?php echo htmlspecialchars($link_data['cpf_cnpj']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <p class="data-label mb-1">Endereço Principal:</p>
                                <p class="data-value">
                                    <?php
                                    $endereco_completo = [];
                                    if (!empty($link_data['cliente_endereco'])) $endereco_completo[] = htmlspecialchars($link_data['cliente_endereco']);
                                    if (!empty($link_data['cliente_cidade'])) $endereco_completo[] = htmlspecialchars($link_data['cliente_cidade']);
                                    if (!empty($link_data['cliente_estado'])) $endereco_completo[] = htmlspecialchars($link_data['cliente_estado']);
                                    echo !empty($endereco_completo) ? implode(', ', $endereco_completo) : 'Não informado';
                                    if (!empty($link_data['cliente_cep'])) echo '<br>CEP: ' . htmlspecialchars($link_data['cliente_cep']);
                                    ?>
                                </p>
                            </div>
                             <div class="col-md-6">
                                <p class="data-label mb-1">Cliente desde:</p>
                                <p class="data-value"><?php echo !empty($link_data['data_cadastro']) ? date('d/m/Y', strtotime($link_data['data_cadastro'])) : 'Não informado'; ?></p>
                            </div>
                        </div>
                        <p class="mt-4 small text-muted"><i class="fas fa-info-circle me-1"></i> Para alterar seus dados cadastrais, por favor, entre em contato conosco.</p>
                    </div>
                </div>
            </div>
        </div>
    </div> <div id="notification-area"></div>

    <div class="floating-cart">
        <button class="btn btn-primary btn-lg rounded-circle" onclick="toggleCarrinho()" id="cart-button">
            <i class="fas fa-shopping-cart"></i>
            <span class="badge-cart" id="cart-count"><?php echo $total_itens_no_carrinho; ?></span>
        </button>
    </div>

    <div class="cart-overlay" id="cart-overlay" onclick="toggleCarrinho()"></div>

    <div class="cart-sidebar" id="cart-sidebar">
        <div class="cart-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Meu Carrinho</h5>
                <button type="button" class="btn-close" onclick="toggleCarrinho()"></button>
            </div>
        </div>
        <div class="cart-body" id="cart-content">
            </div>
         <div class="cart-footer">
            </div>
    </div>

    <div class="modal fade" id="modalDetalhesPedido" tabindex="-1" aria-labelledby="modalDetalhesPedidoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-marketplace">
                    <h5 class="modal-title" id="modalDetalhesPedidoLabel"><i class="fas fa-receipt me-2"></i>Detalhes do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-marketplace" id="modalDetalhesPedidoConteudo">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div> <footer class="footer-marketplace">
    <div class="container text-center">
        <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_marketplace['titulo_marketplace'] ?? 'Karla Wollinger Marketplace'); ?>. Todos os direitos reservados.</p>
        <p class="mb-0 small">Desenvolvido com <i class="fas fa-heart text-danger"></i> por <a href="https://karlawollinger.com.br" target="_blank">Karla Wollinger</a></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const token = '<?php echo $token; ?>';
    let carrinho = <?php echo json_encode($carrinho_itens); ?>;
    const clienteIdParaApi = <?php echo $cliente_id; ?>;

    document.addEventListener('DOMContentLoaded', function() {
        atualizarCarrinhoVisual();
    });
</script>
<script src="js/marketplace.js?v=<?php echo time(); ?>"></script>
</body>
</html>

<?php $conn->close(); ?>