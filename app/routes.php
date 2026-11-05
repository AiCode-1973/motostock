<?php
declare(strict_types=1);

use App\Controllers\Main;
use App\Controllers\Panel;
use App\Controllers\Platform;
use App\Controllers\Store;
use App\Controllers\WebhookController;
use App\Controllers\MediaController;
use App\Core\Router;

return static function (Router $r): void {
    // ===== Site principal, cadastro e autenticação =====
    $r->get('/', [Main\HomeController::class, 'index']);
    $r->get('/cadastro', [Main\SignupController::class, 'form'], ['guest' => true]);
    $r->post('/cadastro', [Main\SignupController::class, 'store'], ['guest' => true]);
    $r->get('/entrar', [Main\AuthController::class, 'loginForm'], ['guest' => true]);
    $r->post('/entrar', [Main\AuthController::class, 'login'], ['guest' => true]);
    $r->get('/entrar/verificacao', [Main\AuthController::class, 'mfaForm']);
    $r->post('/entrar/verificacao', [Main\AuthController::class, 'mfaVerify']);
    $r->post('/sair', [Main\AuthController::class, 'logout']);
    $r->get('/esqueci-senha', [Main\AuthController::class, 'forgotForm']);
    $r->post('/esqueci-senha', [Main\AuthController::class, 'forgot']);
    $r->get('/redefinir-senha/{token}', [Main\AuthController::class, 'resetForm']);
    $r->post('/redefinir-senha/{token}', [Main\AuthController::class, 'reset']);
    $r->get('/conta/seguranca', [Main\AccountController::class, 'security'], ['auth' => true]);
    $r->post('/conta/senha', [Main\AccountController::class, 'password'], ['auth' => true]);
    $r->post('/conta/mfa/iniciar', [Main\AccountController::class, 'mfaStart'], ['auth' => true]);
    $r->post('/conta/mfa/ativar', [Main\AccountController::class, 'mfaEnable'], ['auth' => true]);
    $r->post('/conta/mfa/desativar', [Main\AccountController::class, 'mfaDisable'], ['auth' => true]);
    $r->post('/conta/trocar-loja', [Main\AccountController::class, 'switchTenant'], ['auth' => true]);

    // ===== Instalação/atualização sem terminal (exige SETUP_TOKEN no .env) =====
    $r->get('/instalacao', [Main\InstallController::class, 'show']);
    $r->post('/instalacao', [Main\InstallController::class, 'run']);

    // ===== Webhooks (sem CSRF; autenticados por assinatura do provedor) =====
    $r->post('/webhooks/mercadopago/{uuid}', [WebhookController::class, 'mercadoPago'], ['csrf' => false]);
    $r->post('/webhooks/simulado/{uuid}', [WebhookController::class, 'simulated'], ['csrf' => false]);

    // ===== Painel da loja =====
    $p = static fn(string $perm, array $extra = []) => ['area' => 'panel', 'perm' => $perm] + $extra;
    $r->get('/painel', [Panel\DashboardController::class, 'index'], $p('dashboard.view'));
    $r->get('/painel/midia/{id}', [MediaController::class, 'show'], $p('products.view'));

    $r->get('/painel/produtos', [Panel\ProductController::class, 'index'], $p('products.view'));
    $r->get('/painel/produtos/novo', [Panel\ProductController::class, 'create'], $p('products.manage'));
    $r->post('/painel/produtos', [Panel\ProductController::class, 'store'], $p('products.manage'));
    $r->get('/painel/produtos/{id}', [Panel\ProductController::class, 'edit'], $p('products.view'));
    $r->post('/painel/produtos/{id}', [Panel\ProductController::class, 'update'], $p('products.manage'));
    $r->post('/painel/produtos/{id}/imagens', [Panel\ProductController::class, 'uploadImage'], $p('products.manage'));
    $r->post('/painel/produtos/{id}/imagens/{imageId}/excluir', [Panel\ProductController::class, 'deleteImage'], $p('products.manage'));
    $r->post('/painel/produtos/{id}/compatibilidade', [Panel\ProductController::class, 'addFitment'], $p('products.manage'));
    $r->post('/painel/produtos/{id}/compatibilidade/{fitmentId}/excluir', [Panel\ProductController::class, 'removeFitment'], $p('products.manage'));

    $r->get('/painel/catalogo', [Panel\CatalogController::class, 'index'], $p('products.view'));
    $r->post('/painel/categorias', [Panel\CatalogController::class, 'storeCategory'], $p('products.manage'));
    $r->post('/painel/categorias/{id}/excluir', [Panel\CatalogController::class, 'deleteCategory'], $p('products.manage'));
    $r->post('/painel/marcas', [Panel\CatalogController::class, 'storeBrand'], $p('products.manage'));
    $r->post('/painel/marcas/{id}/excluir', [Panel\CatalogController::class, 'deleteBrand'], $p('products.manage'));
    $r->get('/painel/catalogo/exportar', [Panel\ImportExportController::class, 'export'], $p('catalog.import'));
    $r->get('/painel/catalogo/importar', [Panel\ImportExportController::class, 'form'], $p('catalog.import'));
    $r->post('/painel/catalogo/importar', [Panel\ImportExportController::class, 'import'], $p('catalog.import'));

    $r->get('/painel/estoque', [Panel\StockController::class, 'index'], $p('stock.view'));
    $r->get('/painel/estoque/movimentar', [Panel\StockController::class, 'form'], $p('stock.move'));
    $r->post('/painel/estoque/movimentar', [Panel\StockController::class, 'move'], $p('stock.move'));
    $r->get('/painel/estoque/historico', [Panel\StockController::class, 'history'], $p('stock.view'));

    $r->get('/painel/fornecedores', [Panel\SupplierController::class, 'index'], $p('suppliers.view'));
    $r->get('/painel/fornecedores/novo', [Panel\SupplierController::class, 'create'], $p('suppliers.manage'));
    $r->post('/painel/fornecedores', [Panel\SupplierController::class, 'store'], $p('suppliers.manage'));
    $r->get('/painel/fornecedores/{id}', [Panel\SupplierController::class, 'show'], $p('suppliers.view'));
    $r->post('/painel/fornecedores/{id}', [Panel\SupplierController::class, 'update'], $p('suppliers.manage'));

    $r->get('/painel/compras', [Panel\PurchaseController::class, 'index'], $p('purchases.view'));
    $r->get('/painel/compras/nova', [Panel\PurchaseController::class, 'create'], $p('purchases.manage'));
    $r->post('/painel/compras', [Panel\PurchaseController::class, 'store'], $p('purchases.manage'));
    $r->get('/painel/compras/{id}', [Panel\PurchaseController::class, 'show'], $p('purchases.view'));
    $r->post('/painel/compras/{id}/enviar', [Panel\PurchaseController::class, 'markOrdered'], $p('purchases.manage'));
    $r->post('/painel/compras/{id}/receber', [Panel\PurchaseController::class, 'receive'], $p('purchases.manage'));
    $r->post('/painel/compras/{id}/cancelar', [Panel\PurchaseController::class, 'cancel'], $p('purchases.manage'));

    $r->get('/painel/clientes', [Panel\CustomerController::class, 'index'], $p('customers.view'));
    $r->get('/painel/clientes/novo', [Panel\CustomerController::class, 'create'], $p('customers.manage'));
    $r->post('/painel/clientes', [Panel\CustomerController::class, 'store'], $p('customers.manage'));
    $r->get('/painel/clientes/{id}', [Panel\CustomerController::class, 'show'], $p('customers.view'));
    $r->post('/painel/clientes/{id}', [Panel\CustomerController::class, 'update'], $p('customers.manage'));
    $r->post('/painel/clientes/{id}/anonimizar', [Panel\CustomerController::class, 'anonymize'], $p('privacy.manage'));

    $r->get('/painel/pedidos', [Panel\OrderController::class, 'index'], $p('orders.view'));
    $r->get('/painel/pedidos/novo', [Panel\OrderController::class, 'create'], $p('orders.create'));
    $r->post('/painel/pedidos', [Panel\OrderController::class, 'store'], $p('orders.create'));
    $r->get('/painel/pedidos/{id}', [Panel\OrderController::class, 'show'], $p('orders.view'));
    $r->post('/painel/pedidos/{id}/status', [Panel\OrderController::class, 'transition'], $p('orders.manage'));
    $r->post('/painel/pedidos/{id}/pagamento', [Panel\OrderController::class, 'registerPayment'], $p('orders.manage'));
    $r->post('/painel/pedidos/{id}/rastreio', [Panel\OrderController::class, 'tracking'], $p('orders.manage'));

    $r->get('/painel/relatorios', [Panel\ReportController::class, 'index'], $p('reports.view'));
    $r->get('/painel/relatorios/{key}', [Panel\ReportController::class, 'show'], $p('reports.view'));
    $r->get('/painel/relatorios/{key}/csv', [Panel\ReportController::class, 'csv'], $p('reports.export'));

    $r->get('/painel/usuarios', [Panel\UserController::class, 'index'], $p('users.manage'));
    $r->post('/painel/usuarios', [Panel\UserController::class, 'store'], $p('users.manage'));
    $r->post('/painel/usuarios/{id}', [Panel\UserController::class, 'update'], $p('users.manage'));
    $r->get('/painel/auditoria', [Panel\UserController::class, 'audit'], $p('users.manage'));

    $r->get('/painel/configuracoes', [Panel\SettingsController::class, 'index'], $p('settings.manage'));
    $r->post('/painel/configuracoes/loja', [Panel\SettingsController::class, 'saveStore'], $p('settings.manage'));
    $r->post('/painel/configuracoes/politicas', [Panel\SettingsController::class, 'savePolicies'], $p('settings.manage'));
    $r->post('/painel/configuracoes/entrega', [Panel\SettingsController::class, 'saveShipping'], $p('settings.manage'));
    $r->post('/painel/configuracoes/pagamentos', [Panel\SettingsController::class, 'saveGateway'], $p('settings.manage'));

    $r->get('/painel/privacidade', [Panel\PrivacyController::class, 'index'], $p('privacy.manage'));
    $r->post('/painel/privacidade/{id}', [Panel\PrivacyController::class, 'resolve'], $p('privacy.manage'));

    $r->get('/painel/assinatura', [Panel\BillingController::class, 'index'], ['area' => 'panel', 'inactive' => true]);
    $r->get('/painel/exportar-dados', [Panel\BillingController::class, 'export'], $p('data.export', ['inactive' => true]));

    // ===== Plataforma MotoStock =====
    $a = static fn(array $extra = []) => ['area' => 'platform'] + $extra;
    $r->get('/plataforma', [Platform\TenantController::class, 'dashboard'], $a());
    $r->get('/plataforma/lojas', [Platform\TenantController::class, 'index'], $a());
    $r->get('/plataforma/lojas/{id}', [Platform\TenantController::class, 'show'], $a());
    $r->post('/plataforma/lojas/{id}/status', [Platform\TenantController::class, 'changeStatus'], $a(['perm' => 'platform.write']));
    $r->post('/plataforma/lojas/{id}/plano', [Platform\TenantController::class, 'changePlan'], $a(['perm' => 'platform.write']));
    $r->get('/plataforma/lojas/{id}/exportar', [Platform\TenantController::class, 'export'], $a(['perm' => 'platform.write']));
    $r->get('/plataforma/planos', [Platform\PlanController::class, 'index'], $a());
    $r->get('/plataforma/planos/novo', [Platform\PlanController::class, 'create'], $a(['perm' => 'platform.write']));
    $r->post('/plataforma/planos', [Platform\PlanController::class, 'store'], $a(['perm' => 'platform.write']));
    $r->get('/plataforma/planos/{id}', [Platform\PlanController::class, 'edit'], $a(['perm' => 'platform.write']));
    $r->post('/plataforma/planos/{id}', [Platform\PlanController::class, 'update'], $a(['perm' => 'platform.write']));
    $r->get('/plataforma/auditoria', [Platform\TenantController::class, 'audit'], $a());

    // ===== Loja virtual (host da loja ou /loja/<slug>) =====
    $s = ['area' => 'store'];
    $r->get('@store/', [Store\CatalogController::class, 'index'], $s);
    $r->get('@store/produto/{slug}', [Store\CatalogController::class, 'product'], $s);
    $r->get('@store/midia/{id}', [MediaController::class, 'show'], $s);
    $r->get('@store/carrinho', [Store\CartController::class, 'show'], $s);
    $r->post('@store/carrinho/adicionar', [Store\CartController::class, 'add'], $s);
    $r->post('@store/carrinho/atualizar', [Store\CartController::class, 'update'], $s);
    $r->get('@store/checkout', [Store\CheckoutController::class, 'form'], $s);
    $r->post('@store/checkout', [Store\CheckoutController::class, 'place'], $s);
    $r->get('@store/pedido/{number}/{token}', [Store\CheckoutController::class, 'track'], $s);
    $r->post('@store/pedido/{number}/{token}/pagar', [Store\CheckoutController::class, 'pay'], $s);
    $r->get('@store/pagamento-simulado/{uuid}', [Store\SimulatorController::class, 'show'], $s);
    $r->post('@store/pagamento-simulado/{uuid}', [Store\SimulatorController::class, 'decide'], $s);
    $r->get('@store/conta/entrar', [Store\AccountController::class, 'loginForm'], $s);
    $r->post('@store/conta/entrar', [Store\AccountController::class, 'login'], $s);
    $r->get('@store/conta/cadastro', [Store\AccountController::class, 'registerForm'], $s);
    $r->post('@store/conta/cadastro', [Store\AccountController::class, 'register'], $s);
    $r->post('@store/conta/sair', [Store\AccountController::class, 'logout'], $s);
    $r->get('@store/conta/pedidos', [Store\AccountController::class, 'orders'], $s);
    $r->get('@store/privacidade/solicitar', [Store\PageController::class, 'privacyForm'], $s);
    $r->post('@store/privacidade/solicitar', [Store\PageController::class, 'privacyRequest'], $s);
    $r->get('@store/{page}', [Store\PageController::class, 'show'], $s);
};
