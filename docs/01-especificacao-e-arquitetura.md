# MotoStock · Especificação funcional e arquitetura

Versão 0.1 (MVP) · 2026-11-04

## 1. Visão do produto

MotoStock é um SaaS para lojas de peças e acessórios de motocicleta. Cada empresa assinante (tenant) tem:

- uma **loja virtual** própria (subdomínio `loja.motostock.com.br`, domínio próprio opcional ou, em desenvolvimento, `/loja/<slug>`);
- um **painel** para equipe (catálogo, estoque, compras, clientes, pedidos, relatórios);
- dados isolados de todas as outras empresas.

A equipe MotoStock tem um **painel de plataforma** separado para tenants, planos, assinaturas, uso e auditoria.

## 2. Decisões de arquitetura

| Decisão | Escolha | Por quê |
|---|---|---|
| Linguagem | PHP 8.1+ puro (testado em 8.3), `declare(strict_types=1)` | Exigência; roda em qualquer hospedagem PHP. |
| Dependências | Nenhuma (sem Composer) | Implantação por FTP/cPanel; superfície de ataque menor. Política em `docs/04`. |
| Banco | MySQL 8 / MariaDB 10.6+, InnoDB, utf8mb4 | Exigência. SQL compatível com os dois (testado em MariaDB 10.11). |
| Multiempresa | **Banco compartilhado, coluna `tenant_id`** em toda tabela de loja | Custo baixo, uma migration para todos. Isolamento reforçado em 4 camadas (seção 4). |
| Integridade entre tenants | **Chaves estrangeiras compostas** `(tenant_id, id)` | O banco recusa um item de pedido que aponte para produto de outra loja, mesmo com bug na aplicação. |
| Camadas | `Core` (HTTP, sessão, segurança) · `Repositories` (SQL) · `Services` (regras) · `Controllers` (rotas) · `Views` (HTML) | Separação pedida; regras testáveis sem HTTP. |
| Front-end | HTML renderizado no servidor + CSS próprio + JS mínimo | Funciona sem JS, rápido no celular, CSP rígida (`script-src 'self'`). |
| Dinheiro | Inteiros em centavos | Sem erro de arredondamento. |
| Datas | Gravadas em **UTC**; exibidas e filtradas no fuso da loja (padrão `America/Sao_Paulo`) | Relatórios corretos e sem ambiguidade. |
| Pagamentos | Interface `PaymentGateway` + `SimulatedGateway` (dev/testes) + `MercadoPagoGateway` (Checkout Pro) | Desacoplado; cartão e Pix ficam nas telas do provedor, nada de cartão passa por nós. |
| Frete | Modalidades manuais por loja; interface preparada para cotação externa | Sem credenciais de transportadora ainda. |
| E-mail | Driver `log` (grava em `storage/logs/mail.log`) e `mail` (função `mail()` do PHP) | SMTP autenticado depende de contratação; ver `docs/06`. |

## 3. Estrutura de pastas

```
motostock/
├── public/            # ÚNICA pasta exposta (document root): index.php, assets
├── app/
│   ├── Core/          # App, Router, Request, Response, View, Session, Csrf, Auth,
│   │                  # Tenant (contexto + resolução por host), Permissions, RateLimiter, Audit, Crypto, Totp
│   ├── Repositories/  # acesso a dados; base TenantRepository injeta tenant_id em todo SQL
│   ├── Services/      # regras: estoque, pedidos, checkout, pagamentos, compras, provisionamento, limites, relatórios
│   ├── Payments/      # PaymentGateway, SimulatedGateway, MercadoPagoGateway
│   ├── Controllers/   # Main (site, cadastro, login), Panel, Store, Platform, Webhook
│   └── Views/         # templates PHP com escape obrigatório (e())
├── database/migrations/  # SQL versionado
├── bin/               # migrate, seed-demo, cron, create-platform-admin, test
├── tests/             # testes automatizados (runner próprio)
├── storage/           # logs, uploads por tenant, exportações (fora do public)
├── docs/
└── .env.example       # variáveis sem segredos reais; o .env real fica fora do Git
```

## 4. Isolamento por tenant (defesa em profundidade)

1. **Identificação no servidor.** No painel, o tenant vem da sessão (`active_tenant_id`) e é **revalidado a cada requisição** contra `tenant_users` (vínculo ativo) e `tenants.status`. Na loja, vem do **host** (subdomínio ou domínio verificado) ou do prefixo `/loja/<slug>`. Nenhum `tenant_id` enviado pelo navegador é lido.
2. **Repositórios.** `TenantRepository` recebe o `tenant_id` no construtor e o coloca em todo `SELECT/UPDATE/DELETE` (`WHERE tenant_id = ? AND id = ?`). Um id de outra loja resulta em 404, não em 403, para não revelar existência.
3. **Banco.** FKs compostas `(tenant_id, x_id) → x(tenant_id, id)` impedem referências cruzadas; `UNIQUE (tenant_id, sku)` etc.
4. **Arquivos, exportações e tarefas.** Uploads ficam em `storage/uploads/<tenant_uuid>/` e são servidos por rota que confere o tenant do contexto. Exportações e relatórios usam os mesmos repositórios. O `cron` percorre os tenants um a um, com o id explícito.
5. **Testes.** `tests/TenantIsolationTest.php` tenta ler, editar, mover estoque, ver pedido, exportar e baixar imagem de uma loja com usuário de outra, e confere a negativa.

### Banco dedicado por cliente (plano futuro)

A camada `Database` é obtida por `Database::forTenant()`; hoje sempre devolve a conexão compartilhada. Para um plano com isolamento físico:

1. Coluna `tenants.db_dsn_ref` (referência a um segredo, nunca a senha em texto) e campo `plans.dedicated_database`.
2. `Database::forTenant()` abre a conexão dedicada quando houver referência; as tabelas são as mesmas (o `tenant_id` permanece, o que permite migrar de volta).
3. `bin/migrate.php --all-tenants` aplica migrations no banco compartilhado e em cada dedicado.
4. Migração de um tenant: exportar (`TenantExportService`), importar no banco novo, trocar a referência em janela de manutenção, conferir contagens, manter o original em modo leitura até o aceite.
5. Backups e restauração passam a ser por banco; custo e operação maiores justificam preço próprio.

## 5. Perfis e permissões

| Perfil | Onde | Pode |
|---|---|---|
| Admin da plataforma (`users.platform_role = admin`) | `/plataforma` | tenants, planos, assinaturas, auditoria. **Não** vê clientes/pedidos das lojas. MFA obrigatório em produção. |
| Suporte da plataforma (`support`) | `/plataforma` | só leitura de tenants e uso. |
| Proprietário (`owner`) | `/painel` | tudo da loja, incluindo usuários, configurações, credenciais de pagamento, exportação de dados e privacidade. |
| Gerente (`manager`) | `/painel` | catálogo, estoque, compras, fornecedores, clientes, pedidos, reembolso, relatórios e exportação de relatórios. |
| Vendedor (`seller`) | `/painel` | ver produtos e estoque (sem custo/margem), clientes, criar e andar pedidos (sem reembolso). |
| Cliente da loja | loja | catálogo, carrinho, pedido, acompanhamento, pedido de privacidade. |

As permissões ficam em `app/Core/Permissions.php` e são conferidas no backend em toda rota (`'perm' => 'orders.refund'`). A interface só esconde o que o backend já bloqueia.

## 6. Módulos e fluxos principais

### 6.1 Cadastro de empresa (onboarding)
`/cadastro` → dados da empresa (CNPJ com dígito verificador), endereço da loja (slug), plano público, dono (nome, e-mail, senha ≥ 10) → transação cria tenant, domínio, assinatura, vínculo `owner`, configurações e modalidades de entrega padrão → login automático no painel. Limitado a 5 cadastros por IP/hora.

### 6.2 Ciclo de vida do tenant
`trial → active ↔ past_due → suspended → active` e `qualquer → cancelled`. Suspensão bloqueia loja e painel (fica só a página de assinatura e a exportação de dados). Cancelamento define `retention_until` (padrão 90 dias, `TENANT_RETENTION_DAYS`); **nenhum dado é apagado automaticamente**. O expurgo é manual e auditado (fora do MVP).

### 6.3 Estoque
Saldo físico `stock_qty` e reservado `reserved_qty`; disponível = físico − reservado. Toda alteração passa por `StockService` com `UPDATE` condicional (`... WHERE stock_qty - reserved_qty >= ?`), o que evita vender acima do disponível mesmo com dois pedidos simultâneos. Cada operação grava `stock_movements` (tipo, quantidade, saldo após, motivo, usuário, pedido/compra).

### 6.4 Pedido
```
aguardando pagamento ─► pago ─► separando ─► enviado ─► entregue
        │                 │          │           │           │
        └► cancelado ◄────┴──────────┘           └──► reembolsado ◄┘
```
- Criar pedido (loja ou balcão) **reserva** o estoque. Preço sempre do cadastro, nunca do navegador.
- Pago: a reserva vira saída. Cancelado antes do pagamento: libera a reserva. Cancelado/reembolsado depois do pagamento e antes do envio: devolve ao estoque. Reembolso após envio: devolução ao estoque só se marcada (peça voltou).
- Pedido da loja não pago em `ORDER_PAYMENT_TTL_HOURS` (padrão 48) é cancelado pelo cron e a reserva é liberada.
- Cada mudança grava `order_status_history` (de, para, quem, quando, observação).

### 6.5 Pagamento
1. Checkout cria o pedido e um `payment` com **chave de idempotência** única por tenant; clique duplo reaproveita o pagamento pendente.
2. O gateway devolve a URL do checkout do provedor (cartão/Pix ficam lá).
3. Voltar para "sucesso" **não** marca nada como pago: a página só mostra o estado atual.
4. O provedor chama `/webhooks/<gateway>/<tenant_uuid>`; a assinatura é validada, o evento é deduplicado (`webhook_events`), o pagamento é **consultado na API do provedor** e o valor é conferido antes de marcar o pedido como pago.

### 6.6 Compras
Pedido de compra (rascunho → enviado → parcial/recebido, ou cancelado) com itens e custo. O recebimento (total ou parcial) gera entradas de estoque ligadas à compra e atualiza o custo do produto.

### 6.7 Dashboard e relatórios
Regras em `docs/02-modelo-de-dados.md#regras-de-calculo`. Resumo: faturamento = soma de `total` dos pedidos com pagamento confirmado (pago, separando, enviado, entregue) **pela data do pagamento**, no fuso da loja; ticket médio = faturamento ÷ pedidos pagos; cancelados e reembolsados ficam fora.

## 7. Riscos e mitigação

| Risco | Mitigação |
|---|---|
| Vazamento entre tenants por consulta sem filtro | Repositório base obrigatório, FKs compostas, testes de isolamento, revisão de todo SQL com `tenant_id`. |
| Venda acima do estoque (concorrência) | `UPDATE` condicional atômico + `CHECK` no banco + transação. |
| Cobrança duplicada | Idempotência local e no provedor (`X-Idempotency-Key`). |
| Pedido marcado como pago por falsificação | Só webhook assinado + consulta à API + conferência de valor. |
| Força bruta em login | Limite por IP+e-mail, atraso uniforme, MFA para administradores. |
| XSS / CSRF / SQLi | Escape em toda saída, CSP, token CSRF em todo POST, PDO com prepares reais. |
| Hospedagem compartilhada sem cron/terminal | Cron por cPanel; `bin/` também roda por agendador web protegido (futuro). |
| Requisitos legais (CDC, LGPD, Decreto 7.962/2013) | Modelos de texto marcados "revisar com advogado"; ver `docs/04`. |
| Crescimento (um banco para todos) | Índices por `(tenant_id, …)`, paginação, plano de banco dedicado (seção 4). |

## 8. Plano de entregas

| Etapa | Conteúdo | Situação |
|---|---|---|
| 0 | Especificação, arquitetura, modelo de dados | **feito** (este documento e `docs/02`) |
| 1 | Núcleo: rotas, sessão, CSRF, autenticação, MFA, tenant, permissões, auditoria | **feito** |
| 2 | Cadastro de empresa, planos, limites, painel da plataforma, suspensão/reativação/cancelamento, exportação | **feito** |
| 3 | Catálogo (categorias, marcas, produtos, imagens, compatibilidade), estoque e movimentações, importação/exportação CSV | **feito** |
| 4 | Loja virtual: busca, filtros, produto, carrinho, frete manual, checkout, acompanhamento, páginas legais | **feito** |
| 5 | Pedidos (loja e balcão), estados, histórico, pagamentos simulados e camada Mercado Pago | **feito** (Mercado Pago sem teste real: depende de credenciais) |
| 6 | Fornecedores, compras e recebimento | **feito** |
| 7 | Clientes, privacidade (pedidos de titulares, anonimização) | **feito** |
| 8 | Dashboard e relatórios com exportação | **feito** |
| 9 | Próximas: variações de produto (tamanho/cor), estados de pedido personalizados por loja, permissões finas por usuário, cobrança automática da assinatura, cotação de frete, NF-e, e-mail SMTP, verificação de domínio próprio por DNS | **pendente** |

O que está "feito" foi verificado por testes (`php bin/test.php`) ou funcionamento real; limitações estão em `docs/06-dependencias-externas.md`.
