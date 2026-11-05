# MotoStock · Modelo de dados

Esquema completo em `database/migrations/001_schema.sql` (MySQL 8.0.16+ / MariaDB 10.6+, InnoDB, utf8mb4).

## Convenções

- Toda tabela de loja tem `tenant_id` e `UNIQUE (tenant_id, id)`. As FKs entre tabelas de loja são **compostas**: `(tenant_id, product_id) → products(tenant_id, id)`. O banco recusa um item de pedido da loja A apontando para um produto da loja B.
- Datas em **UTC** (`DATETIME`, conexão com `time_zone = '+00:00'`). A loja tem `tenants.timezone`.
- Dinheiro em **centavos** (`INT UNSIGNED`).
- `CHECK` no banco: `stock_qty >= 0`, `reserved_qty >= 0`, `reserved_qty <= stock_qty`, recebido ≤ pedido nas compras, ano final ≥ inicial.
- Dados de demonstração marcados com `is_demo = 1`.

## Mapa

```
plans ─┬─< tenants ─┬─< tenant_domains
       │            ├─< subscriptions
       │            ├─< tenant_users >── users ──< password_resets
       │            ├── store_settings (1:1)
       │            ├── gateway_credentials (segredos cifrados)
       │            ├─< categories ─┐
       │            ├─< brands ─────┤
       │            ├─< products ◄──┘ ─┬─< product_images
       │            │                  ├─< product_fitments (marca/modelo/anos da moto)
       │            │                  └─< stock_movements
       │            ├─< suppliers ─< purchase_orders ─< purchase_order_items >─ products
       │            ├─< customers ─< customer_addresses
       │            ├─< shipping_methods
       │            ├─< orders ─┬─< order_items >─ products
       │            │           ├─< order_status_history
       │            │           └─< payments
       │            ├─< data_requests (pedidos de titulares)
       │            ├─< import_logs
       │            └─< tenant_counters (numeração de pedidos e compras)
audit_logs, rate_limits, webhook_events, simulated_gateway_payments (globais)
```

## Tabelas principais

| Tabela | Papel | Observações |
|---|---|---|
| `plans` | Planos configuráveis | Preço e limites (`NULL` = sem limite) ficam no banco. |
| `tenants` | Empresa assinante | `status`: trial, active, past_due, suspended, cancelled. `retention_until` após cancelamento. |
| `tenant_domains` | Subdomínio e domínio próprio | Domínio próprio só vale com `verified_at` e plano que permita. |
| `users` / `tenant_users` | Identidade única + vínculo por loja | Uma pessoa pode estar em várias lojas com funções diferentes. `platform_role` para a equipe MotoStock. |
| `products` | Catálogo e saldo | `stock_qty` (físico), `reserved_qty` (pedidos aguardando pagamento). Saldo só muda via `StockService`. |
| `product_fitments` | Compatibilidade | Marca, modelo, ano inicial e final, observação. |
| `stock_movements` | Razão do estoque | Tipo, variação com sinal, saldo após, motivo, usuário, pedido/compra. Nunca editado. |
| `orders` | Pedido | Guarda **cópia** de cliente, endereço, frete e preços (o pedido não muda se o cadastro mudar). Token de acompanhamento só em hash. |
| `order_status_history` | Trilha de alterações | De/para, ator (equipe, cliente, provedor, sistema), usuário, observação. |
| `payments` | Tentativas de pagamento | `UNIQUE (tenant_id, idempotency_key)`; `uuid` vai como referência ao provedor; sem dados de cartão. |
| `webhook_events` | Deduplicação de notificações | `UNIQUE (gateway, event_key)`. |
| `audit_logs` | Auditoria | Metadados sem senhas, tokens ou cartão (filtro em `Audit::log`). |

## Regras de cálculo

Valem para o dashboard e para os relatórios (`app/Services/ReportService.php`):

| Indicador | Regra |
|---|---|
| Período | Datas locais da loja (`tenants.timezone`), convertidas para intervalo UTC `[início, fim)`. Máximo de 366 dias. |
| Faturamento | Soma de `orders.total_cents` com situação pago, separando, enviado ou entregue, **pela data do pagamento** (`paid_at`). Cancelados e reembolsados não entram. |
| Pedidos recebidos | Pedidos criados (`placed_at`) no período, qualquer situação. |
| Ticket médio | Faturamento ÷ quantidade de pedidos pagos no período. |
| Mais vendidos | Soma das quantidades em pedidos pagos no período. |
| Margem bruta | Receita − custo do produto **no momento da venda** (`order_items.unit_cost_cents`). |
| Estoque baixo | Produtos ativos com (físico − reservado) ≤ estoque mínimo. |
| Valor em estoque | Físico × custo cadastrado atual. |
| Pendentes | Aguardando pagamento + pagos + em separação. |
| Limite de pedidos/mês | Pedidos criados desde o dia 1 do mês (UTC). |

## Numeração

`tenant_counters` gera números sequenciais por loja (pedido nº 1, 2, 3… em cada loja) com `SELECT … FOR UPDATE` dentro da transação do pedido.
