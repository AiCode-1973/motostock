<div class="auth">
  <form method="post" action="<?= e(url('/entrar')) ?>" class="card">
    <h1>Entrar</h1>
    <p class="muted">Painel da loja e da plataforma MotoStock.</p>
    <?= csrf_field() ?>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required autocomplete="username" autofocus<?= field_attrs('email') ?>><?= field_error('email') ?></div>
    <br>
    <div class="field"><label for="password">Senha</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <div class="actions"><button class="btn btn--block" type="submit" data-loading="Entrando…">Entrar</button></div>
    <p class="small"><a href="<?= e(url('/esqueci-senha')) ?>">Esqueci minha senha</a> · <a href="<?= e(url('/cadastro')) ?>">Criar uma loja</a></p>
  </form>
</div>
