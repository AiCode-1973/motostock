<div class="page-head"><div><h1>Relatórios</h1><p>Todos usam o fuso da loja e só dados desta loja. Exportação em CSV conforme sua permissão.</p></div></div>
<div class="grid grid-3">
  <?php $desc = [
      'vendas' => 'Pedidos do período com canal, situação, operador, descontos e frete.',
      'produtos' => 'Quantidade, receita e margem por produto em pedidos pagos.',
      'pagamentos' => 'Tentativas e confirmações de pagamento, por meio e situação.',
      'estoque' => 'Posição atual: físico, reservado, disponível e valor em estoque.',
      'compras' => 'Compras e recebimentos por fornecedor, com atraso médio de entrega.',
  ]; ?>
  <?php foreach ($reports as $key => $name): ?>
    <a class="card product-card" href="<?= e(url('/painel/relatorios/' . $key)) ?>"><div class="body"><strong><?= e($name) ?></strong><span class="muted small"><?= e($desc[$key]) ?></span></div></a>
  <?php endforeach; ?>
</div>
