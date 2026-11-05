<div class="page-head"><div><p><a href="<?= e(url('/painel/produtos')) ?>">← Produtos</a></p><h1>Importar e exportar catálogo</h1></div></div>
<div class="grid grid-2">
  <div class="card">
    <h2>Exportar</h2>
    <p class="muted">Baixa todos os produtos em CSV (separador ponto e vírgula, abre no Excel). Use o arquivo como modelo para importar.</p>
    <a class="btn btn--ghost" href="<?= e(url('/painel/catalogo/exportar')) ?>">Baixar catalogo.csv</a>
  </div>
  <form class="card" method="post" action="<?= e(url('/painel/catalogo/importar')) ?>" enctype="multipart/form-data">
    <h2>Importar</h2>
    <?= csrf_field() ?>
    <p class="muted small">Colunas: <span class="mono">sku;nome;preco;custo;estoque_minimo;codigo_barras;categoria;marca;ativo;descricao</span>. SKU existente é atualizado; novo é criado. Categorias e marcas novas são criadas. <strong>O saldo de estoque não é importado</strong>: registre-o em Estoque › Movimentar, para ficar com motivo e responsável.</p>
    <div class="field"><label for="file">Arquivo CSV</label><input id="file" type="file" name="file" accept=".csv,text/csv" required<?= field_attrs('file') ?>><?= field_error('file') ?></div>
    <div class="actions"><button class="btn" type="submit" data-loading="Importando…">Importar</button></div>
  </form>
</div>
<?php if ($result): ?>
  <div class="card">
    <h2>Resultado</h2>
    <p><?= (int) $result['ok'] ?> de <?= (int) $result['total'] ?> linha(s) importada(s). <?= $result['failed'] ? '<strong>' . (int) $result['failed'] . ' com erro.</strong>' : '' ?></p>
    <?php if ($result['errors']): ?><div class="table-wrap"><table><thead><tr><th>Linha</th><th>Problema</th></tr></thead><tbody>
      <?php foreach ($result['errors'] as $line => $msg): ?><tr><td><?= (int) $line ?></td><td><?= e($msg) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
<?php endif; ?>
<div class="card">
  <h2>Importações anteriores</h2>
  <?php if (!$logs): ?><p class="muted">Nenhuma importação.</p><?php else: ?>
  <div class="table-wrap"><table class="stack"><thead><tr><th>Data</th><th>Arquivo</th><th>Por</th><th class="num">OK</th><th class="num">Erros</th></tr></thead><tbody>
    <?php foreach ($logs as $l): ?><tr><td data-label="Data"><?= e(dt($l['created_at'])) ?></td><td data-label="Arquivo"><?= e($l['file_name']) ?></td><td data-label="Por"><?= e($l['user_name'] ?? '—') ?></td><td data-label="OK" class="num"><?= (int) $l['rows_ok'] ?></td><td data-label="Erros" class="num"><?= (int) $l['rows_failed'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
