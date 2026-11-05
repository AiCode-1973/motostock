<div class="page-head"><div><h1>Auditoria da plataforma</h1><p>Ações da equipe MotoStock e eventos de sistema (logins, provisionamento, mudanças de situação, exportações).</p></div></div>
<div class="card">
  <div class="table-wrap"><table class="stack"><thead><tr><th>Data (Brasília)</th><th>Quem</th><th>Ação</th><th>Loja</th><th>Detalhes</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $a): ?><tr>
      <td data-label="Data" class="nowrap"><?= e(dt($a['created_at'], 'd/m/Y H:i', 'America/Sao_Paulo')) ?></td>
      <td data-label="Quem"><?= e($a['user_name'] ?? 'Sistema') ?></td>
      <td data-label="Ação" class="mono"><?= e($a['action']) ?></td>
      <td data-label="Loja"><?= e($a['trade_name'] ?? '—') ?></td>
      <td data-label="Detalhes" class="small mono"><?= e($a['meta'] ?? '') ?></td>
      <td data-label="IP" class="small"><?= e($a['ip'] ?? '') ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</div>
