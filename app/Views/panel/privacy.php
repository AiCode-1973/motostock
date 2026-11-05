<div class="page-head"><div><h1>Privacidade (LGPD)</h1><p>Pedidos de titulares recebidos pela página “Solicitar meus dados” da loja.</p></div></div>
<div class="alert alert--info">A loja é a <strong>controladora</strong> dos dados dos seus clientes; a MotoStock atua como <strong>operadora</strong>. Confirme a identidade de quem pede antes de entregar ou apagar dados. Prazos e respostas devem seguir a orientação jurídica da loja.</div>
<div class="card">
  <?php if (!$rows): ?>
    <?php $emptyTitle = 'Nenhuma solicitação'; $emptyText = 'Quando um cliente pedir acesso, correção ou eliminação de dados, o pedido aparece aqui.'; $emptyAction = null; include __DIR__ . '/../partials/empty.php'; ?>
  <?php else: foreach ($rows as $d): ?>
    <div class="card">
      <div class="card-head"><h3><?= e(label('data_request', $d['type'])) ?> · <?= e($d['email']) ?></h3><?= badge('data_request_status', $d['status']) ?></div>
      <p class="small muted">Recebido em <?= e(dt($d['created_at'])) ?><?= $d['customer_id'] ? ' · cliente cadastrado: <a href="' . e(url('/painel/clientes/' . $d['customer_id'])) . '">' . e($d['customer_name']) . '</a>' : ' · e-mail sem cadastro na loja' ?></p>
      <?php if ($d['message']): ?><p class="pre"><?= e($d['message']) ?></p><?php endif; ?>
      <?php if ($d['status'] === 'open'): ?>
        <form method="post" action="<?= e(url('/painel/privacidade/' . $d['id'])) ?>">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field"><label for="st<?= (int) $d['id'] ?>">Resultado</label><select id="st<?= (int) $d['id'] ?>" name="status"><option value="done">Atendido</option><option value="rejected">Negado (com justificativa)</option></select></div>
            <div class="field span-2"><label for="rs<?= (int) $d['id'] ?>">O que foi feito</label><textarea id="rs<?= (int) $d['id'] ?>" name="resolution" required placeholder="Ex.: identidade confirmada por telefone; dados enviados por e-mail em 04/11; cliente anonimizado."></textarea></div>
          </div>
          <div class="actions"><button class="btn" type="submit">Registrar resposta</button><?php if ($d['type'] === 'deletion' && $d['customer_id']): ?><a href="<?= e(url('/painel/clientes/' . $d['customer_id'])) ?>#anonimizar">Anonimizar o cliente</a><?php endif; ?></div>
        </form>
      <?php else: ?>
        <p class="small"><strong>Resposta</strong> (<?= e($d['resolver'] ?? '') ?>, <?= e(dt($d['resolved_at'])) ?>): <?= e($d['resolution']) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
