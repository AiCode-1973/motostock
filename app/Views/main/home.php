<section class="hero">
  <div class="wrap">
    <h1>Estoque, compras, vendas e loja virtual para quem vende peças de moto.</h1>
    <p>Controle o saldo de cada peça, receba mercadoria dos fornecedores, venda no balcão e na sua loja online com compatibilidade por marca, modelo e ano da moto.</p>
    <a class="btn" href="<?= e(url('/cadastro')) ?>">Criar minha loja</a>
    <a class="btn btn--ghost" href="<?= e(url('/entrar')) ?>">Já tenho conta</a>
  </div>
</section>
<div class="wrap">
  <div class="features">
    <div class="card"><h3>Estoque sem surpresa</h3><p class="muted">Reserva automática no pedido, baixa só com pagamento confirmado e histórico de cada movimentação com responsável e motivo.</p></div>
    <div class="card"><h3>Peça certa para a moto certa</h3><p class="muted">Cadastre a compatibilidade (marca, modelo, anos) e deixe o cliente filtrar e confirmar antes de comprar.</p></div>
    <div class="card"><h3>Loja virtual pronta</h3><p class="muted">Catálogo, carrinho, frete por modalidade, checkout com Pix e cartão pelo provedor e acompanhamento do pedido.</p></div>
    <div class="card"><h3>Equipe com permissões</h3><p class="muted">Proprietário, gerente e vendedor com acesso mínimo necessário e trilha de auditoria.</p></div>
  </div>

  <h2 id="planos">Planos</h2>
  <?php if (!$plans): ?>
    <div class="card"><?php $emptyTitle = 'Planos em configuração'; $emptyText = 'Os planos ainda não foram publicados pela equipe MotoStock.'; include __DIR__ . '/../partials/empty.php'; ?></div>
  <?php else: ?>
  <div class="plans">
    <?php foreach ($plans as $p): ?>
      <div class="card plan">
        <h3><?= e($p['name']) ?></h3>
        <div class="price"><?= e(money((int) $p['price_cents'])) ?><span class="small muted">/<?= $p['billing_interval'] === 'year' ? 'ano' : 'mês' ?></span></div>
        <?php if ($p['description']): ?><p class="muted"><?= e($p['description']) ?></p><?php endif; ?>
        <ul>
          <li><?= $p['max_users'] === null ? 'Usuários ilimitados' : (int) $p['max_users'] . ' usuário(s)' ?></li>
          <li><?= $p['max_products'] === null ? 'Produtos ilimitados' : 'Até ' . (int) $p['max_products'] . ' produtos' ?></li>
          <li><?= $p['max_orders_month'] === null ? 'Pedidos ilimitados' : 'Até ' . (int) $p['max_orders_month'] . ' pedidos/mês' ?></li>
          <?php if ($p['allow_custom_domain']): ?><li>Domínio próprio</li><?php endif; ?>
          <?php if ((int) $p['trial_days'] > 0): ?><li><?= (int) $p['trial_days'] ?> dias de teste</li><?php endif; ?>
        </ul>
        <a class="btn btn--block" href="<?= e(url('/cadastro', ['plano' => $p['id']])) ?>">Começar com este plano</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
