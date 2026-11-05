<div class="auth auth--wide">
  <h1>Criar a conta da sua loja</h1>
  <p class="muted">Leva 2 minutos. Você poderá completar os dados e as políticas da loja depois, no painel.</p>
  <?php if (!$plans): ?>
    <div class="alert alert--info">Os planos ainda não foram publicados. Tente novamente mais tarde.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('/cadastro')) ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <fieldset>
      <legend>Empresa</legend>
      <div class="form-grid">
        <div class="field"><label class="req" for="trade_name">Nome da loja</label><input id="trade_name" name="trade_name" value="<?= e(old('trade_name')) ?>" required maxlength="120"<?= field_attrs('trade_name') ?>><?= field_error('trade_name') ?></div>
        <div class="field"><label class="req" for="legal_name">Razão social</label><input id="legal_name" name="legal_name" value="<?= e(old('legal_name')) ?>" required maxlength="160"<?= field_attrs('legal_name') ?>><?= field_error('legal_name') ?></div>
        <div class="field"><label class="req" for="document">CNPJ</label><input id="document" name="document" value="<?= e(old('document')) ?>" inputmode="numeric" placeholder="00.000.000/0000-00" required<?= field_attrs('document') ?>><?= field_error('document') ?></div>
        <div class="field"><label class="req" for="phone">Telefone</label><input id="phone" name="phone" type="tel" value="<?= e(old('phone')) ?>" placeholder="(11) 98765-4321" required<?= field_attrs('phone') ?>><?= field_error('phone') ?></div>
        <div class="field"><label class="req" for="city">Cidade</label><input id="city" name="city" value="<?= e(old('city')) ?>" required<?= field_attrs('city') ?>><?= field_error('city') ?></div>
        <div class="field"><label class="req" for="state">UF</label>
          <select id="state" name="state" required<?= field_attrs('state') ?>><option value="">Escolha</option><?php foreach (\App\Core\Validator::UFS as $uf): ?><option<?= selected(old('state'), $uf) ?>><?= $uf ?></option><?php endforeach; ?></select><?= field_error('state') ?></div>
        <div class="field span-2"><label class="req" for="slug">Endereço da loja virtual</label>
          <input id="slug" name="slug" value="<?= e(old('slug')) ?>" placeholder="minhaloja" required pattern="[a-z0-9-]{3,40}"<?= field_attrs('slug') ?>>
          <span class="hint">Letras minúsculas, números e hífen. Sua loja ficará em <strong>minhaloja.<?= e(\App\Core\Env::get('APP_BASE_DOMAIN', 'motostock.com.br')) ?></strong>.</span><?= field_error('slug') ?></div>
      </div>
    </fieldset>
    <fieldset>
      <legend>Plano</legend>
      <?php foreach ($plans as $p): ?>
        <label class="ship-option"><input type="radio" name="plan_id" value="<?= (int) $p['id'] ?>"<?= checked((int) old('plan_id', $selectedPlan ?: $plans[0]['id']) === (int) $p['id']) ?>>
          <span><strong><?= e($p['name']) ?></strong> · <?= e(money((int) $p['price_cents'])) ?>/<?= $p['billing_interval'] === 'year' ? 'ano' : 'mês' ?>
          <?= (int) $p['trial_days'] > 0 ? ' · ' . (int) $p['trial_days'] . ' dias de teste' : '' ?><br><span class="small muted"><?= e($p['description'] ?? '') ?></span></span></label>
      <?php endforeach; ?>
      <?= field_error('plan_id') ?>
    </fieldset>
    <fieldset>
      <legend>Seu acesso (proprietário)</legend>
      <div class="form-grid">
        <div class="field"><label class="req" for="owner_name">Seu nome</label><input id="owner_name" name="owner_name" value="<?= e(old('owner_name')) ?>" required autocomplete="name"<?= field_attrs('owner_name') ?>><?= field_error('owner_name') ?></div>
        <div class="field"><label class="req" for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autocomplete="email"<?= field_attrs('email') ?>><?= field_error('email') ?></div>
        <div class="field"><label class="req" for="password">Senha</label><input id="password" name="password" type="password" required minlength="10" autocomplete="new-password"<?= field_attrs('password') ?>><span class="hint">Mínimo de 10 caracteres. Uma frase longa é mais segura.</span><?= field_error('password') ?></div>
        <div class="field"><label class="req" for="password_confirmation">Confirme a senha</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"<?= field_attrs('password_confirmation') ?>><?= field_error('password_confirmation') ?></div>
      </div>
    </fieldset>
    <label class="check"><input type="checkbox" name="accept_terms" value="1"<?= checked(old('accept_terms'))?><?= field_attrs('accept_terms') ?>> <span>Li e aceito os termos de uso e a política de privacidade da plataforma MotoStock. <span class="muted">(textos em validação jurídica)</span></span></label>
    <?= field_error('accept_terms') ?>
    <div class="actions"><button class="btn" type="submit" data-loading="Criando sua loja…">Criar minha loja</button><a href="<?= e(url('/entrar')) ?>">Já tenho conta</a></div>
  </form>
  <?php endif; ?>
</div>
