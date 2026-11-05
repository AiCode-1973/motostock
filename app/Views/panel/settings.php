<?php $s = $settings; $t = $tenant; $sv = fn(string $k) => old($k, $s[$k] ?? ''); $tv = fn(string $k) => old($k, $t[$k] ?? ''); ?>
<div class="page-head"><div><h1>Configurações</h1><p>Loja virtual: <a href="<?= e($storeUrl) ?>" target="_blank" rel="noopener"><?= e($storeUrl) ?></a></p></div></div>

<form class="card" method="post" action="<?= e(url('/painel/configuracoes/loja')) ?>" novalidate>
  <h2>Dados da loja e atendimento</h2>
  <p class="small muted">Aparecem no rodapé da loja: o Decreto 7.962/2013 exige identificação do fornecedor (razão social, CNPJ, endereço) e canal de atendimento.</p>
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label class="req" for="trade_name">Nome da loja</label><input id="trade_name" name="trade_name" value="<?= e($tv('trade_name')) ?>" required<?= field_attrs('trade_name') ?>><?= field_error('trade_name') ?></div>
    <div class="field"><label>Razão social / CNPJ</label><input value="<?= e($t['legal_name'] . ' · ' . format_doc($t['document'])) ?>" disabled><span class="hint">Para alterar, fale com o suporte MotoStock.</span></div>
    <div class="field span-2"><label for="address_line">Endereço</label><input id="address_line" name="address_line" value="<?= e($tv('address_line')) ?>" maxlength="200" placeholder="Rua, número, bairro"></div>
    <div class="field"><label class="req" for="city">Cidade</label><input id="city" name="city" value="<?= e($tv('city')) ?>" required<?= field_attrs('city') ?>><?= field_error('city') ?></div>
    <div class="field"><label class="req" for="state">UF</label><select id="state" name="state"><?php foreach (\App\Core\Validator::UFS as $uf): ?><option<?= selected($tv('state'), $uf) ?>><?= $uf ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="zip">CEP</label><input id="zip" name="zip" value="<?= e(format_cep($tv('zip'))) ?>" inputmode="numeric"<?= field_attrs('zip') ?>><?= field_error('zip') ?></div>
    <div class="field"><label class="req" for="contact_email">E-mail de atendimento</label><input id="contact_email" name="contact_email" type="email" value="<?= e($sv('contact_email')) ?>" required<?= field_attrs('contact_email') ?>><?= field_error('contact_email') ?></div>
    <div class="field"><label for="contact_phone">Telefone</label><input id="contact_phone" name="contact_phone" value="<?= e(format_phone($sv('contact_phone'))) ?>"<?= field_attrs('contact_phone') ?>><?= field_error('contact_phone') ?></div>
    <div class="field"><label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" value="<?= e(format_phone($sv('whatsapp'))) ?>"<?= field_attrs('whatsapp') ?>><?= field_error('whatsapp') ?></div>
    <div class="field"><label for="service_hours">Horário de atendimento</label><input id="service_hours" name="service_hours" value="<?= e($sv('service_hours')) ?>" placeholder="Seg. a sex. 8h–18h, sáb. 8h–12h"></div>
    <div class="field span-2"><label for="about">Sobre a loja</label><textarea id="about" name="about" maxlength="3000"><?= e($sv('about')) ?></textarea></div>
  </div>
  <div class="actions"><button class="btn" type="submit">Salvar dados</button></div>
</form>

<div class="card" id="entrega">
  <h2>Formas de entrega</h2>
  <p class="small muted">Sem integração com transportadora ainda: valores e prazos são fixos por modalidade. “Grátis acima de” zera o frete a partir do subtotal informado.</p>
  <?php foreach ([...$shipping, null] as $m): $id = $m['id'] ?? 'novo'; ?>
  <form method="post" action="<?= e(url('/painel/configuracoes/entrega')) ?>" class="filters">
    <?= csrf_field() ?>
    <?php if ($m): ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><?php endif; ?>
    <div class="field field--wide"><label for="sn<?= $id ?>"><?= $m ? 'Nome' : 'Nova forma de entrega' ?></label><input id="sn<?= $id ?>" name="name" value="<?= e($m['name'] ?? '') ?>" required maxlength="80" placeholder="Ex.: Motoboy (região metropolitana)"></div>
    <div class="field"><label for="sk<?= $id ?>">Tipo</label><select id="sk<?= $id ?>" name="kind"><option value="delivery"<?= selected($m['kind'] ?? 'delivery', 'delivery') ?>>Entrega</option><option value="pickup"<?= selected($m['kind'] ?? '', 'pickup') ?>>Retirada</option></select></div>
    <div class="field"><label for="sp<?= $id ?>">Valor (R$)</label><input id="sp<?= $id ?>" name="price" inputmode="decimal" value="<?= e($m ? money_input((int) $m['price_cents']) : '') ?>"></div>
    <div class="field"><label for="sf<?= $id ?>">Grátis acima de</label><input id="sf<?= $id ?>" name="free_above" inputmode="decimal" value="<?= e($m && $m['free_above_cents'] !== null ? money_input((int) $m['free_above_cents']) : '') ?>"></div>
    <div class="field"><label for="sd<?= $id ?>">Prazo (dias úteis)</label><span class="filters"><input id="sd<?= $id ?>" name="days_min" type="number" min="0" value="<?= e($m['days_min'] ?? 1) ?>" aria-label="mínimo"><input name="days_max" type="number" min="0" value="<?= e($m['days_max'] ?? 3) ?>" aria-label="máximo"></span></div>
    <div class="field"><label for="sr<?= $id ?>">UFs (vazio = todas)</label><input id="sr<?= $id ?>" name="regions" value="<?= e($m['regions'] ?? '') ?>" placeholder="SP,RJ"></div>
    <label class="check"><input type="checkbox" name="active" value="1"<?= checked($m['active'] ?? 1) ?>> Ativa</label>
    <button class="btn btn--ghost btn--sm" type="submit"><?= $m ? 'Salvar' : 'Adicionar' ?></button>
  </form>
  <?php endforeach; ?>
</div>

<form class="card" id="politicas" method="post" action="<?= e(url('/painel/configuracoes/politicas')) ?>" novalidate>
  <h2>Políticas da loja <span class="badge">versão <?= (int) ($s['policy_version'] ?? 1) ?></span></h2>
  <div class="alert alert--warn">Os textos iniciais são <strong>modelos</strong>. Revise-os com um advogado antes de vender: eles envolvem CDC (direito de arrependimento, art. 49), Decreto 7.962/2013 e LGPD. Cada salvamento gera uma nova versão, e o aceite de cada cliente fica registrado com a versão que ele viu.</div>
  <?= csrf_field() ?>
  <div class="field"><label class="req" for="policy_privacy">Política de privacidade</label><textarea id="policy_privacy" name="policy_privacy" rows="8"><?= e($sv('policy_privacy')) ?></textarea><?= field_error('policy_privacy') ?></div><br>
  <div class="field"><label class="req" for="policy_returns">Trocas, devoluções e arrependimento</label><textarea id="policy_returns" name="policy_returns" rows="8"><?= e($sv('policy_returns')) ?></textarea><?= field_error('policy_returns') ?></div><br>
  <div class="field"><label class="req" for="policy_shipping">Entrega</label><textarea id="policy_shipping" name="policy_shipping" rows="5"><?= e($sv('policy_shipping')) ?></textarea><?= field_error('policy_shipping') ?></div>
  <div class="actions"><button class="btn" type="submit">Publicar nova versão</button></div>
</form>

<form class="card" id="pagamentos" method="post" action="<?= e(url('/painel/configuracoes/pagamentos')) ?>" autocomplete="off">
  <h2>Pagamento online · Mercado Pago</h2>
  <p>Situação atual: <strong><?= $activeGateway === 'mercadopago' ? 'Mercado Pago ativo' : ($activeGateway === 'simulated' ? 'Gateway SIMULADO (ambiente de testes)' : 'Sem pagamento online: pedidos ficam para combinar com a loja') ?></strong></p>
  <div class="alert alert--info">
    Cartão e Pix são pagos na tela do Mercado Pago (Checkout Pro): a loja não recebe dados de cartão. Para ativar:
    <ol>
      <li>No painel de desenvolvedor do Mercado Pago, crie uma aplicação e copie o <strong>Access Token</strong> (comece pelo de teste).</li>
      <li>Em Webhooks, cadastre a URL abaixo, evento <strong>Pagamentos</strong>, e copie a <strong>assinatura secreta</strong>.</li>
    </ol>
    <span class="code-box"><?= e($webhookUrl) ?></span>
    <p class="small">Integração implementada conforme a documentação pública, ainda <strong>não homologada com credenciais reais</strong>. Faça um pedido de teste em modo sandbox antes de ativar em produção.</p>
  </div>
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label for="public_key">Public Key (opcional)</label><input id="public_key" name="public_key" value="<?= e($gateway['public_key'] ?? '') ?>"></div>
    <div class="field"><label for="access_token">Access Token</label><input id="access_token" name="access_token" type="password" placeholder="<?= !empty($gateway['has_token']) ? '•••••• (guardado; preencha só para trocar)' : 'APP_USR-… ou TEST-…' ?>"<?= field_attrs('access_token') ?>><?= field_error('access_token') ?></div>
    <div class="field"><label for="webhook_secret">Assinatura secreta do webhook</label><input id="webhook_secret" name="webhook_secret" type="password" placeholder="<?= !empty($gateway['has_secret']) ? '•••••• (guardada; preencha só para trocar)' : '' ?>"></div>
    <label class="check"><input type="checkbox" name="sandbox" value="1"<?= checked($gateway['sandbox'] ?? 1) ?>> Modo de teste (sandbox)</label>
    <label class="check"><input type="checkbox" name="enabled" value="1"<?= checked($gateway['enabled'] ?? 0) ?>> Ativar pagamento online com Mercado Pago</label>
  </div>
  <div class="actions"><button class="btn" type="submit">Salvar pagamento</button></div>
  <p class="small muted">As credenciais são guardadas cifradas e nunca aparecem de novo nesta tela.</p>
</form>
