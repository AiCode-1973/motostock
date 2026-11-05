<?php
declare(strict_types=1);

namespace Tests;

use App\Core\BusinessException;
use App\Services\OrderService;
use App\Services\StockService;

final class StockTest extends TestCase
{
    private int $tid;
    private int $pid;
    private StockService $stock;

    public function setUp(): void
    {
        parent::setUp();
        $this->tid = (int) $this->tenant('estoque')['tenant']['id'];
        $this->pid = $this->product($this->tid, 'EST-1', 5000, 10);
        $this->stock = new StockService($this->db, $this->tid, null);
    }

    public function testInitialStockIsRecordedAsMovement(): void
    {
        $m = $this->db->fetch('SELECT * FROM stock_movements WHERE product_id = ?', [$this->pid]);
        $this->assertSame('entry', $m['type']);
        $this->assertSame(10, (int) $m['quantity']);
        $this->assertSame('Saldo inicial do cadastro', $m['reason']);
    }

    public function testEntryExitAndAdjustKeepHistory(): void
    {
        $this->stock->entry($this->pid, 5, 'compra avulsa');
        $this->stock->exit($this->pid, 3, 'avaria');
        $this->stock->adjust($this->pid, 20, 'inventário');
        $this->assertSame(['stock_qty' => 20, 'reserved_qty' => 0], $this->stock($this->tid, $this->pid));
        $types = array_column($this->db->fetchAll('SELECT type, quantity, stock_after FROM stock_movements WHERE product_id = ? ORDER BY id', [$this->pid]), 'stock_after');
        $this->assertSame([10, 15, 12, 20], array_map('intval', $types));
    }

    public function testReasonIsRequired(): void
    {
        $this->assertThrows(BusinessException::class, fn() => $this->stock->entry($this->pid, 1, '  '), 'motivo');
    }

    public function testCannotExitMoreThanAvailable(): void
    {
        $this->assertThrows(BusinessException::class, fn() => $this->stock->exit($this->pid, 11, 'x'), 'insuficiente');
        $this->assertSame(10, $this->stock($this->tid, $this->pid)['stock_qty']);
    }

    public function testReservedUnitsCannotBeTakenByManualExitOrAdjust(): void
    {
        (new OrderService($this->db, $this->tid))->create(['channel' => 'manual', 'items' => [['product_id' => $this->pid, 'quantity' => 8]]]);
        $this->assertSame(['stock_qty' => 10, 'reserved_qty' => 8], $this->stock($this->tid, $this->pid));
        $this->assertThrows(BusinessException::class, fn() => $this->stock->exit($this->pid, 3, 'x'));
        $this->assertThrows(BusinessException::class, fn() => $this->stock->adjust($this->pid, 7, 'contagem'), 'reservada');
        $this->stock->exit($this->pid, 2, 'ok');
        $this->assertSame(['stock_qty' => 8, 'reserved_qty' => 8], $this->stock($this->tid, $this->pid));
    }

    public function testCannotSellAboveAvailability(): void
    {
        $orders = new OrderService($this->db, $this->tid);
        $orders->create(['channel' => 'manual', 'items' => [['product_id' => $this->pid, 'quantity' => 9]]]);
        // O segundo pedido pela última unidade + 1 deve falhar e não deixar rastros.
        $this->assertThrows(BusinessException::class, fn() => $orders->create(['channel' => 'manual', 'items' => [['product_id' => $this->pid, 'quantity' => 2]]]), 'Estoque insuficiente');
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM orders'));
        $orders->create(['channel' => 'manual', 'items' => [['product_id' => $this->pid, 'quantity' => 1]]]);
        $this->assertSame(['stock_qty' => 10, 'reserved_qty' => 10], $this->stock($this->tid, $this->pid));
    }

    public function testFailedOrderRollsBackEveryReservation(): void
    {
        $p2 = $this->product($this->tid, 'EST-2', 5000, 1);
        $this->assertThrows(BusinessException::class, fn() => (new OrderService($this->db, $this->tid))->create(['channel' => 'manual', 'items' => [
            ['product_id' => $this->pid, 'quantity' => 2], ['product_id' => $p2, 'quantity' => 5],
        ]]));
        $this->assertSame(0, $this->stock($this->tid, $this->pid)['reserved_qty'], 'reserva do primeiro item desfeita');
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM stock_movements WHERE type = 'reserve'"));
    }

    public function testLowStockAlertAndDashboardWhenBelowMinimum(): void
    {
        $this->db->execute('UPDATE products SET min_stock = 50 WHERE id = ?', [$this->pid]);
        $this->product($this->tid, 'EST-ZERO', 1000, 0);
        $low = (new \App\Repositories\ProductRepository($this->db, $this->tid))->lowStock();
        $this->assertSame(2, count($low));
        $this->assertSame('EST-1', $low[0]['sku'], 'mais distante do mínimo primeiro');
        $p = \App\Support\Period::fromInput(null, null, 'America/Sao_Paulo');
        $this->assertSame(2, count((new \App\Services\ReportService($this->db, $this->tid))->dashboard($p)['low_stock']));
    }

    public function testDatabaseConstraintBlocksNegativeStock(): void
    {
        $this->assertThrows(\PDOException::class, fn() => $this->db->execute('UPDATE products SET stock_qty = -1 WHERE id = ?', [$this->pid]));
        $this->assertThrows(\PDOException::class, fn() => $this->db->execute('UPDATE products SET reserved_qty = 11 WHERE id = ?', [$this->pid]));
    }

    public function testPurchaseReceiptAddsStockAndUpdatesCost(): void
    {
        $sid = $this->db->insert('suppliers', ['tenant_id' => $this->tid, 'name' => 'Fornecedor', 'created_at' => \App\Core\Clock::now(), 'updated_at' => \App\Core\Clock::now()]);
        $ps = new \App\Services\PurchaseService($this->db, $this->tid, null);
        $po = $ps->create($sid, [['product_id' => $this->pid, 'quantity' => 6, 'unit_cost' => '41,50']], null, null);
        $this->assertThrows(BusinessException::class, fn() => $ps->receive($po, []), 'Envie a compra');
        $ps->markOrdered($po);
        $itemId = (int) $this->db->value('SELECT id FROM purchase_order_items WHERE purchase_order_id = ?', [$po]);
        $ps->receive($po, [$itemId => 4]);
        $this->assertSame('partial', $this->db->value('SELECT status FROM purchase_orders WHERE id = ?', [$po]));
        $this->assertThrows(BusinessException::class, fn() => $ps->receive($po, [$itemId => 3]), 'maior que a pendente');
        $ps->receive($po, [$itemId => 2]);
        $this->assertSame('received', $this->db->value('SELECT status FROM purchase_orders WHERE id = ?', [$po]));
        $this->assertSame(16, $this->stock($this->tid, $this->pid)['stock_qty']);
        $this->assertSame(4150, (int) $this->db->value('SELECT cost_cents FROM products WHERE id = ?', [$this->pid]));
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM stock_movements WHERE purchase_order_id = ?', [$po]));
        $this->assertThrows(BusinessException::class, fn() => $ps->cancel($po));
    }

    public function testCsvImportValidatesEachLineAndDoesNotTouchStock(): void
    {
        $csv = "sku;nome;preco;custo;estoque_minimo;codigo_barras;categoria;marca;ativo;descricao\n"
            . "EST-1;Nome atualizado;55,00;30,00;3;;Freios;Cobreq;sim;\n"
            . "NOVO-9;Produto novo;12,90;;;;Freios;;sim;desc\n"
            . ";Sem SKU;10,00;;;;;;sim;\n"
            . "RUIM-1;Preço ruim;abc;;;;;;sim;\n";
        $r = (new \App\Services\CatalogImportService($this->db, $this->tid))->import($csv, 'teste.csv');
        $this->assertSame(2, $r['ok']);
        $this->assertSame(2, $r['failed']);
        $this->assertSame('Nome atualizado', $this->db->value('SELECT name FROM products WHERE id = ?', [$this->pid]));
        $this->assertSame(10, $this->stock($this->tid, $this->pid)['stock_qty'], 'importação não muda saldo');
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM categories WHERE tenant_id = ? AND name = 'Freios'", [$this->tid]));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM import_logs WHERE rows_failed = 2'));
        $export = (new \App\Services\CatalogImportService($this->db, $this->tid))->export();
        $this->assertContains('NOVO-9;"Produto novo";12,90', $export);
    }
}
