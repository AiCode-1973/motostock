<div class="card">
  <h1><?= e($title) ?></h1>
  <?php if ($page === 'contato'): ?>
    <dl class="dl">
      <dt>Loja</dt><dd><?= e($store['trade_name']) ?> · <?= e($store['legal_name']) ?> · CNPJ <?= e(format_doc($store['document'])) ?></dd>
      <dt>Endereço</dt><dd><?= e(trim(($store['address_line'] ? $store['address_line'] . ', ' : '') . $store['city'] . '/' . $store['state'] . ($store['zip'] ? ' · CEP ' . format_cep($store['zip']) : ''))) ?></dd>
      <?php if (!empty($settings['contact_email'])): ?><dt>E-mail</dt><dd><a href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a></dd><?php endif; ?>
      <?php if (!empty($settings['contact_phone'])): ?><dt>Telefone</dt><dd><?= e(format_phone($settings['contact_phone'])) ?></dd><?php endif; ?>
      <?php if (!empty($settings['whatsapp'])): ?><dt>WhatsApp</dt><dd><a href="https://wa.me/55<?= e(preg_replace('/\D/', '', $settings['whatsapp'])) ?>" rel="noopener" target="_blank"><?= e(format_phone($settings['whatsapp'])) ?></a></dd><?php endif; ?>
      <?php if (!empty($settings['service_hours'])): ?><dt>Horário</dt><dd><?= e($settings['service_hours']) ?></dd><?php endif; ?>
    </dl>
    <p class="small muted">Para trocas, devoluções ou desistência (até 7 dias do recebimento), informe o número do pedido.</p>
  <?php elseif ($body === ''): ?>
    <p class="muted">A loja ainda não publicou este conteúdo.</p>
  <?php else: ?>
    <div class="pre"><?= e($body) ?></div>
  <?php endif; ?>
  <?php if ($page === 'entrega' && $shipping): ?>
    <h2>Formas de entrega</h2>
    <ul><?php foreach ($shipping as $m): ?><li><?= e($m['name']) ?>: <?= (int) $m['price_cents'] === 0 ? 'grátis' : e(money((int) $m['price_cents'])) ?><?= $m['free_above_cents'] !== null ? ' (grátis acima de ' . e(money((int) $m['free_above_cents'])) . ')' : '' ?> · <?= e(\App\Services\OrderService::daysLabel($m)) ?><?= $m['regions'] ? ' · ' . e($m['regions']) : '' ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if ($page === 'privacidade'): ?><p><a class="btn btn--ghost" href="<?= e(store_url('/privacidade/solicitar')) ?>">Solicitar meus dados</a></p><?php endif; ?>
</div>
