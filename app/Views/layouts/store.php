<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? '') ?> · <?= e($store['trade_name']) ?></title>
  <meta name="description" content="<?= e(mb_substr((string) ($settings['about'] ?? ('Peças e acessórios para motos · ' . $store['trade_name'])), 0, 155)) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<?php if (!empty($store['is_demo'])): ?><div class="demo-ribbon">Loja de DEMONSTRAÇÃO · produtos e preços fictícios, pedidos não são entregues</div><?php endif; ?>
<header class="store-header">
  <div class="wrap">
    <a class="store-name" href="<?= e(store_url('/')) ?>"><?= e($store['trade_name']) ?></a>
    <form class="store-search" method="get" action="<?= e(store_url('/')) ?>" role="search">
      <label class="sr-only" for="busca">Buscar peças</label>
      <input id="busca" type="search" name="q" value="<?= e($query['q'] ?? '') ?>" placeholder="Buscar por peça, SKU ou marca">
      <button class="btn btn--dark" type="submit" data-no-loading>Buscar</button>
    </form>
    <nav class="store-links" aria-label="Conta e carrinho">
      <?php if (!empty($customer)): ?>
        <a href="<?= e(store_url('/conta/pedidos')) ?>">Meus pedidos</a>
      <?php else: ?>
        <a href="<?= e(store_url('/conta/entrar')) ?>">Entrar</a>
      <?php endif; ?>
      <a class="cart-pill" href="<?= e(store_url('/carrinho')) ?>">Carrinho (<?= (int) $cartCount ?>)</a>
    </nav>
  </div>
</header>
<?php if (!empty($navCategories)): ?>
<nav class="store-cats" aria-label="Categorias">
  <div class="wrap">
    <a href="<?= e(store_url('/')) ?>">Todas as peças</a>
    <?php foreach ($navCategories as $c): ?><a href="<?= e(store_url('/', ['categoria' => $c['id']])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
<main class="store-main" id="conteudo">
  <div class="wrap">
    <?php include __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?>
  </div>
</main>
<footer class="store-footer">
  <div class="wrap cols">
    <div>
      <strong><?= e($store['trade_name']) ?></strong><br>
      <?= e($store['legal_name']) ?><br>
      CNPJ <?= e(format_doc($store['document'])) ?><br>
      <?= e(trim(($store['address_line'] ? $store['address_line'] . ' · ' : '') . $store['city'] . '/' . $store['state'])) ?>
    </div>
    <div>
      <strong>Atendimento</strong><br>
      <?php if (!empty($settings['contact_email'])): ?><?= e($settings['contact_email']) ?><br><?php endif; ?>
      <?php if (!empty($settings['contact_phone'])): ?><?= e(format_phone($settings['contact_phone'])) ?><br><?php endif; ?>
      <?php if (!empty($settings['service_hours'])): ?><?= e($settings['service_hours']) ?><br><?php endif; ?>
      <a href="<?= e(store_url('/contato')) ?>">Fale conosco</a>
    </div>
    <div>
      <strong>Políticas</strong><br>
      <a href="<?= e(store_url('/trocas-e-devolucoes')) ?>">Trocas, devoluções e arrependimento</a><br>
      <a href="<?= e(store_url('/entrega')) ?>">Entrega</a><br>
      <a href="<?= e(store_url('/privacidade')) ?>">Privacidade</a> · <a href="<?= e(store_url('/privacidade/solicitar')) ?>">Solicitar meus dados</a><br>
      <a href="<?= e(store_url('/sobre')) ?>">Sobre a loja</a>
    </div>
  </div>
  <div class="wrap"><p class="small">Pagamentos processados pelo provedor; a loja não armazena dados de cartão. Loja hospedada na plataforma MotoStock.</p></div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
