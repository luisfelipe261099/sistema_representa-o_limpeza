// Marketplace JavaScript Functions

// Toggle carrinho
function toggleCarrinho() {
    const sidebar = document.getElementById('cart-sidebar');
    const overlay = document.getElementById('cart-overlay');
    
    if (sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    } else {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        carregarCarrinho();
    }
}

// Adicionar produto ao carrinho
function adicionarAoCarrinho(produtoId, produtoNome, preco, estoque) {
    // Verificar se já existe no carrinho
    const itemExistente = carrinho.find(item => item.produto_id == produtoId);
    
    if (itemExistente) {
        if (itemExistente.quantidade < estoque) {
            itemExistente.quantidade++;
            atualizarItemCarrinho(produtoId, itemExistente.quantidade);
        } else {
            alert('Quantidade máxima em estoque atingida!');
            return;
        }
    } else {
        // Adicionar novo item
        const novoItem = {
            produto_id: produtoId,
            produto_nome: produtoNome,
            quantidade: 1,
            preco_unitario: preco,
            quantidade_estoque: estoque
        };
        
        carrinho.push(novoItem);
        adicionarItemCarrinho(produtoId, 1, preco);
    }
    
    atualizarCarrinho();
    mostrarNotificacao('Produto adicionado ao carrinho!', 'success');
}

// Remover item do carrinho
function removerDoCarrinho(produtoId) {
    carrinho = carrinho.filter(item => item.produto_id != produtoId);
    removerItemCarrinho(produtoId);
    atualizarCarrinho();
    mostrarNotificacao('Produto removido do carrinho!', 'info');
}

// Alterar quantidade no carrinho
function alterarQuantidade(produtoId, novaQuantidade) {
    const item = carrinho.find(item => item.produto_id == produtoId);
    
    if (item) {
        if (novaQuantidade <= 0) {
            removerDoCarrinho(produtoId);
            return;
        }
        
        if (novaQuantidade > item.quantidade_estoque) {
            alert('Quantidade não disponível em estoque!');
            return;
        }
        
        item.quantidade = novaQuantidade;
        atualizarItemCarrinho(produtoId, novaQuantidade);
        atualizarCarrinho();
    }
}

// Atualizar interface do carrinho
function atualizarCarrinho() {
    const cartCount = document.getElementById('cart-count');
    const cartContent = document.getElementById('cart-content');
    
    // Atualizar contador
    const totalItens = carrinho.reduce((total, item) => total + item.quantidade, 0);
    cartCount.textContent = totalItens;
    cartCount.style.display = totalItens > 0 ? 'flex' : 'none';
    
    // Atualizar conteúdo
    if (carrinho.length === 0) {
        cartContent.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>Seu carrinho está vazio</p>
            </div>
        `;
        return;
    }
    
    let total = 0;
    let html = '<div class="cart-items">';
    
    carrinho.forEach(item => {
        const subtotal = item.quantidade * item.preco_unitario;
        total += subtotal;
        
        html += `
            <div class="cart-item border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${item.produto_nome}</h6>
                        <p class="text-muted small mb-2">R$ ${item.preco_unitario.toFixed(2).replace('.', ',')}</p>
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="alterarQuantidade(${item.produto_id}, ${item.quantidade - 1})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="mx-3">${item.quantidade}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="alterarQuantidade(${item.produto_id}, ${item.quantidade + 1})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-sm btn-outline-danger mb-2" onclick="removerDoCarrinho(${item.produto_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                        <div class="fw-bold">R$ ${subtotal.toFixed(2).replace('.', ',')}</div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    // Total e botões
    html += `
        <div class="cart-footer border-top pt-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Total:</h5>
                <h5 class="mb-0 text-primary">R$ ${total.toFixed(2).replace('.', ',')}</h5>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-primary" onclick="finalizarPedido()">
                    <i class="fas fa-check me-2"></i>Finalizar Pedido
                </button>
                <button class="btn btn-outline-secondary" onclick="limparCarrinho()">
                    <i class="fas fa-trash me-2"></i>Limpar Carrinho
                </button>
            </div>
        </div>
    `;
    
    cartContent.innerHTML = html;
}

// Carregar carrinho do servidor
function carregarCarrinho() {
    fetch('marketplace_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_carrinho',
            token: token
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carrinho = data.carrinho;
            atualizarCarrinho();
        }
    })
    .catch(error => console.error('Erro:', error));
}

// Adicionar item ao carrinho no servidor
function adicionarItemCarrinho(produtoId, quantidade, preco) {
    fetch('marketplace_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add_to_cart',
            token: token,
            produto_id: produtoId,
            quantidade: quantidade,
            preco_unitario: preco
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao adicionar ao carrinho:', data.message);
        }
    })
    .catch(error => console.error('Erro:', error));
}

// Atualizar item no carrinho no servidor
function atualizarItemCarrinho(produtoId, quantidade) {
    fetch('marketplace_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_cart_item',
            token: token,
            produto_id: produtoId,
            quantidade: quantidade
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao atualizar carrinho:', data.message);
        }
    })
    .catch(error => console.error('Erro:', error));
}

// Remover item do carrinho no servidor
function removerItemCarrinho(produtoId) {
    fetch('marketplace_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'remove_from_cart',
            token: token,
            produto_id: produtoId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Erro ao remover do carrinho:', data.message);
        }
    })
    .catch(error => console.error('Erro:', error));
}

// Limpar carrinho
function limparCarrinho() {
    if (confirm('Tem certeza que deseja limpar o carrinho?')) {
        fetch('marketplace_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'clear_cart',
                token: token
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                carrinho = [];
                atualizarCarrinho();
                mostrarNotificacao('Carrinho limpo!', 'info');
            }
        })
        .catch(error => console.error('Erro:', error));
    }
}

// Finalizar pedido
function finalizarPedido() {
    if (carrinho.length === 0) {
        alert('Adicione produtos ao carrinho antes de finalizar o pedido!');
        return;
    }
    
    // Redirecionar para página de checkout
    window.location.href = `marketplace_checkout.php?token=${token}`;
}

// Filtros e busca
document.addEventListener('DOMContentLoaded', function() {
    // Busca
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filtrarProdutos();
        });
    }
    
    // Filtro por empresa
    const filterEmpresa = document.getElementById('filterEmpresa');
    if (filterEmpresa) {
        filterEmpresa.addEventListener('change', function() {
            filtrarProdutos();
        });
    }
});

// Filtrar produtos
function filtrarProdutos() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const empresaFilter = document.getElementById('filterEmpresa').value;
    const produtos = document.querySelectorAll('.produto-item');
    
    produtos.forEach(produto => {
        const nome = produto.getAttribute('data-nome');
        const empresa = produto.getAttribute('data-empresa');
        
        const matchSearch = nome.includes(searchTerm);
        const matchEmpresa = !empresaFilter || empresa === empresaFilter;
        
        if (matchSearch && matchEmpresa) {
            produto.style.display = 'block';
        } else {
            produto.style.display = 'none';
        }
    });
}

// Mostrar notificação
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Remover após 3 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}
