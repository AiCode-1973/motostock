<?php
declare(strict_types=1);

// Cria dados de DEMONSTRAÇÃO claramente identificados (is_demo = 1, e-mails @demo.test, faixa roxa nas telas).
// Uso: php bin/seed-demo.php            (recusa rodar se já houver lojas)
//      php bin/seed-demo.php --force    (adiciona mesmo assim)
// Senha de todas as contas demo: DEMO_PASSWORD abaixo (só para ambiente local/homologação).

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Clock;
use App\Core\Database;
use App\Core\Env;
use App\Core\Money;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Services\TenantService;

const DEMO_PASSWORD = 'demo-motostock-2026';

if (Env::get('APP_ENV') === 'production') {
    fwrite(STDERR, "Dados de demonstração não podem ser criados em produção.\n");
    exit(1);
}
$db = Database::connect();
if ($db->value('SELECT COUNT(*) FROM tenants') > 0 && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Já existem lojas. Use --force para adicionar a demonstração mesmo assim.\n");
    exit(1);
}

$now = Clock::now();
$plans = [
    ['essencial', 'Essencial', 'Valores de demonstração: edite em Plataforma › Planos.', 9900, 14, 2, 300, 300, 0, 1],
    ['profissional', 'Profissional', 'Valores de demonstração: edite em Plataforma › Planos.', 19900, 14, 6, 3000, 2000, 1, 2],
    ['rede', 'Rede', 'Valores de demonstração. Lojas com várias filiais.', 39900, 0, null, null, null, 1, 3],
];
foreach ($plans as [$code, $name, $desc, $price, $trial, $users, $products, $orders, $domain, $sort]) {
    if (!$db->value('SELECT 1 FROM plans WHERE code = ?', [$code])) {
        $db->insert('plans', ['code' => $code, 'name' => $name, 'description' => $desc, 'price_cents' => $price, 'trial_days' => $trial,
            'max_users' => $users, 'max_products' => $products, 'max_orders_month' => $orders, 'allow_custom_domain' => $domain,
            'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now]);
    }
}
$pro = $db->fetch("SELECT * FROM plans WHERE code = 'profissional'");
$ess = $db->fetch("SELECT * FROM plans WHERE code = 'essencial'");

if (!$db->value('SELECT 1 FROM users WHERE email = ?', ['plataforma@demo.test'])) {
    $db->insert('users', ['name' => 'Equipe MotoStock (demo)', 'email' => 'plataforma@demo.test', 'password_hash' => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT),
        'platform_role' => 'admin', 'password_changed_at' => $now, 'is_demo' => 1, 'created_at' => $now, 'updated_at' => $now]);
}

$tenants = new TenantService($db);
$a = $tenants->provision(['slug' => 'ze-motopecas', 'trade_name' => 'Zé Motopeças (DEMO)', 'legal_name' => 'Zé Motopeças Demonstração Ltda', 'document' => '11222333000181',
    'email' => 'dono@ze.demo.test', 'phone' => '11987654321', 'city' => 'São Paulo', 'state' => 'SP', 'owner_name' => 'José Demo', 'password' => DEMO_PASSWORD], $pro, true);
$b = $tenants->provision(['slug' => 'duas-rodas', 'trade_name' => 'Duas Rodas Acessórios (DEMO)', 'legal_name' => 'Duas Rodas Demonstração ME', 'document' => '45723174000110',
    'email' => 'dono@duasrodas.demo.test', 'phone' => '31998887766', 'city' => 'Belo Horizonte', 'state' => 'MG', 'owner_name' => 'Ana Demo', 'password' => DEMO_PASSWORD], $ess, true);

$tid = $a['tenant_id'];
$db->execute("UPDATE tenants SET address_line = 'Av. Exemplo, 1000 - Centro', zip = '01001000' WHERE id = ?", [$tid]);
$db->execute("UPDATE store_settings SET service_hours = 'Seg. a sex. 8h–18h · sáb. 8h–12h', whatsapp = '11987654321', about = 'Loja de DEMONSTRAÇÃO do MotoStock. Produtos e preços fictícios.' WHERE tenant_id = ?", [$tid]);
$db->execute("UPDATE shipping_methods SET name = 'Motoboy (Grande SP)', price_cents = 1500, free_above_cents = 25000, regions = 'SP', active = 1 WHERE tenant_id = ? AND kind = 'delivery'", [$tid]);
$db->insert('shipping_methods', ['tenant_id' => $tid, 'name' => 'Transportadora (todo o Brasil)', 'kind' => 'delivery', 'price_cents' => 3490, 'days_min' => 4, 'days_max' => 9, 'active' => 1, 'created_at' => $now]);

// Equipe extra
foreach ([['Gerente Demo', 'gerente@ze.demo.test', 'manager'], ['Vendedor Demo', 'vendedor@ze.demo.test', 'seller']] as [$n, $em, $role]) {
    $uid = $db->insert('users', ['name' => $n, 'email' => $em, 'password_hash' => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT), 'password_changed_at' => $now, 'is_demo' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $db->insert('tenant_users', ['tenant_id' => $tid, 'user_id' => $uid, 'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
}

$cat = fn(string $n) => $db->insert('categories', ['tenant_id' => $tid, 'name' => $n, 'slug' => slugify($n), 'created_at' => $now]);
$brand = fn(string $n) => $db->insert('brands', ['tenant_id' => $tid, 'name' => $n, 'created_at' => $now]);
$c = ['freios' => $cat('Freios'), 'trans' => $cat('Transmissão'), 'motor' => $cat('Motor'), 'eletrica' => $cat('Elétrica'), 'acess' => $cat('Acessórios')];
$br = ['cobreq' => $brand('Cobreq'), 'riffel' => $brand('Riffel'), 'ngk' => $brand('NGK'), 'mobil' => $brand('Mobil'), 'pro' => $brand('Pro Tork')];

$ps = new ProductService($db, $tid, $a['user_id']);
$catalog = [
    ['PF-CG160-D', 'Pastilha de freio dianteira CG 160', 'freios', 'cobreq', '4590', '2700', 40, 8, [['Honda', 'CG 160 Titan', 2016, 2025], ['Honda', 'CG 160 Fan', 2016, 2025]]],
    ['PF-FZ25-D', 'Pastilha de freio dianteira Fazer 250', 'freios', 'cobreq', '5290', '3100', 3, 6, [['Yamaha', 'Fazer 250', 2011, 2024]]],
    ['KT-CG160', 'Kit relação (coroa, pinhão e corrente) CG 160', 'trans', 'riffel', '18990', '11800', 12, 4, [['Honda', 'CG 160 Titan', 2016, 2025], ['Honda', 'CG 160 Start', 2016, 2025]]],
    ['KT-FAC150', 'Kit relação Factor 150', 'trans', 'riffel', '16990', '10500', 0, 3, [['Yamaha', 'Factor 150', 2016, 2024]]],
    ['VL-CPR8EA9', 'Vela de ignição NGK CPR8EA-9', 'eletrica', 'ngk', '3290', '1700', 60, 10, [['Honda', 'CG 160 Titan', 2016, 2025], ['Honda', 'Bros 160', 2015, 2025], ['Yamaha', 'Fazer 250', 2011, 2018]]],
    ['OL-10W30-1L', 'Óleo 4T 10W-30 semissintético 1 L', 'motor', 'mobil', '3990', '2400', 100, 20, []],
    ['FA-BROS160', 'Filtro de ar Bros 160', 'motor', null, '4490', '2200', 7, 5, [['Honda', 'Bros 160', 2015, 2025]]],
    ['RT-UNIV-01', 'Retrovisor universal par (rosca 10 mm)', 'acess', 'pro', '6990', '3500', 15, 4, []],
    ['MN-PRO-01', 'Manopla Pro Tork Energy par', 'acess', 'pro', '2990', '1300', 25, 6, []],
    ['LP-H4-12V', 'Lâmpada farol H4 12 V 35/35 W', 'eletrica', null, '1890', '800', 4, 8, []],
];
$ids = [];
foreach ($catalog as [$sku, $name, $ck, $bk, $price, $cost, $stock, $min, $fits]) {
    $id = $ps->create(['sku' => $sku, 'name' => $name, 'category_id' => (string) $c[$ck], 'brand_id' => $bk ? (string) $br[$bk] : '', 'price_cents' => Money::input((int) $price),
        'cost_cents' => Money::input((int) $cost), 'min_stock' => (string) $min, 'active' => '1', 'description' => "Produto de demonstração.\nDados fictícios para testar o MotoStock."], $stock, true);
    foreach ($fits as [$make, $model, $from, $to]) {
        $ps->addFitment($id, ['make' => $make, 'model' => $model, 'year_from' => (string) $from, 'year_to' => (string) $to]);
    }
    $ids[$sku] = $id;
}

$sup = $db->insert('suppliers', ['tenant_id' => $tid, 'name' => 'Distribuidora Demo de Autopeças', 'document' => '11444777000161', 'contact_name' => 'Carla (demo)', 'email' => 'vendas@distribuidora.demo.test', 'phone' => '1133334444', 'notes' => 'Fornecedor fictício.', 'created_at' => $now, 'updated_at' => $now]);
$po = new PurchaseService($db, $tid, $a['user_id']);
$poId = $po->create($sup, [['product_id' => $ids['KT-FAC150'], 'quantity' => 6, 'unit_cost' => '105,00'], ['product_id' => $ids['PF-FZ25-D'], 'quantity' => 10, 'unit_cost' => '31,00']], gmdate('Y-m-d', strtotime('+5 days')), 'Compra de demonstração');
$po->markOrdered($poId);

$cust = [];
foreach ([['Marcos Cliente Demo', 'marcos@cliente.demo.test', '11911112222'], ['Paula Cliente Demo', 'paula@cliente.demo.test', '11933334444']] as [$n, $em, $ph]) {
    $cust[] = $db->insert('customers', ['tenant_id' => $tid, 'name' => $n, 'email' => $em, 'phone' => $ph, 'is_demo' => 1, 'privacy_accepted_at' => $now, 'privacy_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
}
$orders = new OrderService($db, $tid, $a['user_id']);
$pay = new PaymentService($db, $db->fetch('SELECT * FROM tenants WHERE id = ?', [$tid]));
$o1 = $orders->create(['channel' => 'manual', 'customer_id' => $cust[0], 'items' => [['product_id' => $ids['KT-CG160'], 'quantity' => 1], ['product_id' => $ids['OL-10W30-1L'], 'quantity' => 2]]]);
$pay->recordManual($o1['id'], 'card_pos', $a['user_id'], 'Venda demo');
$orders->transition($o1['id'], 'picking');
$o2 = $orders->create(['channel' => 'manual', 'customer_id' => $cust[1], 'items' => [['product_id' => $ids['PF-CG160-D'], 'quantity' => 2], ['product_id' => $ids['VL-CPR8EA9'], 'quantity' => 1]]]);
$pay->recordManual($o2['id'], 'pix_manual', $a['user_id'], null);
$o3 = $orders->create(['channel' => 'manual', 'customer' => ['name' => 'Consumidor (balcão)'], 'items' => [['product_id' => $ids['MN-PRO-01'], 'quantity' => 1]]]);

// Segunda loja: poucos itens, para conferir o isolamento entre lojas.
$tb = $b['tenant_id'];
$cb = $db->insert('categories', ['tenant_id' => $tb, 'name' => 'Capacetes', 'slug' => 'capacetes', 'created_at' => $now]);
$psb = new ProductService($db, $tb, $b['user_id']);
$psb->create(['sku' => 'CAP-ABERTO-58', 'name' => 'Capacete aberto tamanho 58 (DEMO loja 2)', 'category_id' => (string) $cb, 'price_cents' => '199,00', 'cost_cents' => '120,00', 'min_stock' => '2', 'active' => '1'], 5, true);

echo "Demonstração criada.\n";
echo "  Plataforma: plataforma@demo.test\n";
echo "  Loja 1 (ze-motopecas): dono@ze.demo.test · gerente@ze.demo.test · vendedor@ze.demo.test\n";
echo "  Loja 2 (duas-rodas):   dono@duasrodas.demo.test\n";
echo "  Senha de todas: a constante DEMO_PASSWORD em bin/seed-demo.php\n";
