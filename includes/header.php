<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karla Wollinger - Sistema de Gestão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style-new.css?v=<?php echo time(); ?>">
</head>
<body class="dashboard-layout">
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <i class="fas fa-gem"></i>
                <span>Karla Wollinger</span>
            </a>
        </div>

        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="empresas_representadas.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'empresas_representadas.php' || basename($_SERVER['PHP_SELF']) == 'cadastro_empresa.php' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Empresas</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="produtos.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'produtos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>Produtos</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="clientes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Clientes</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="vendas.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vendas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Vendas</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="orcamentos.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orcamentos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Orçamentos</span>
                </a>
            </div>

            <?php if (isset($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] == 'admin'): ?>
            <div class="nav-item">
                <a href="usuarios.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' || basename($_SERVER['PHP_SELF']) == 'cadastro_usuario.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Usuários</span>
                </a>
            </div>
            <?php endif; ?>

            <div class="nav-item">
                <a href="agendamentos.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'agendamentos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Agendamentos</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="financeiro.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'financeiro.php' ? 'active' : ''; ?>">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Financeiro</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="relatorios.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
            </div>

            <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem;">

            <!-- Marketplace Section -->
            <div class="nav-item">
                <a href="marketplace_admin.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace_admin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i>
                    <span>Links Marketplace</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="marketplace_pedidos.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace_pedidos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Pedidos Marketplace</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="marketplace_cliente_empresas.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'marketplace_cliente_empresas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Acesso por Cliente</span>
                </a>
            </div>

            <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem;">

            <div class="nav-item">
                <a href="cadastro_produto.php" class="nav-link">
                    <i class="fas fa-plus"></i>
                    <span>Novo Produto</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="cadastro_cliente.php" class="nav-link">
                    <i class="fas fa-user-plus"></i>
                    <span>Novo Cliente</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="empresa_logos.php" class="nav-link">
                    <i class="fas fa-user-plus"></i>
                    <span>Logo Empresas</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="registrar_venda.php" class="nav-link">
                    <i class="fas fa-cash-register"></i>
                    <span>Nova Venda</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Topbar -->
    <header class="topbar" id="topbar">
        <div class="d-flex align-items-center">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="topbar-user">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION["nome"] ?? 'Usuário'); ?></div>
                <div class="user-role">Administrador</div>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION["nome"] ?? 'U', 0, 1)); ?>
            </div>
            <div class="dropdown">
                <button class="btn btn-link text-decoration-none p-0 ms-2" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-chevron-down text-muted"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-circle me-2"></i> Meu Perfil</a></li>
                    <?php if (isset($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] == 'admin'): ?>
                    <li><a class="dropdown-item" href="usuarios.php"><i class="fas fa-users-cog me-2"></i> Gerenciar Usuários</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">