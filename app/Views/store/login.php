<div class="auth">
  <form class="card" method="post" action="<?= e(store_url('/conta/entrar')) ?>">
    <h1>Entrar</h1>
    <p class="muted small">Conta válida somente nesta loja.</p>
    <?= csrf_field() ?>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autocomplete="username"<?= field_attrs('email') ?>><?= field_error('email') ?></div><br>
    <div class="field"><label for="password">Senha</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <div class="actions"><button class="btn btn--block" type="submit">Entrar</button></div>
    <p class="small">Não tem conta? <a href="<?= e(store_url('/conta/cadastro')) ?>">Criar conta</a>. Esqueceu a senha? <a href="<?= e(store_url('/contato')) ?>">Fale com a loja</a>.</p>
  </form>
</div>
