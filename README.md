# MotoStock

SaaS para lojas de peças e acessórios de moto: loja virtual com compatibilidade por moto, estoque com reserva, compras, clientes, pedidos (loja e balcão), pagamentos, relatórios e administração multiempresa.

PHP 8 puro (sem dependências) · MySQL 8 / MariaDB · banco compartilhado com isolamento rigoroso por loja.

## Começar

```bash
cp .env.example .env              # preencha DB_* e gere APP_KEY: php bin/generate-key.php
php bin/migrate.php
php bin/seed-demo.php             # opcional: duas lojas de demonstração
php -S localhost:8080 -t public public/router.php
```

- Painel: http://localhost:8080/entrar
- Loja demo: http://localhost:8080/loja/ze-motopecas
- Contas e senha de demonstração: `bin/seed-demo.php`

## Testes

```bash
php bin/test.php                  # usa .env.testing (banco com "test" no nome, é apagado)
php bin/test.php Isolation        # filtra por nome
```

78 testes: isolamento entre lojas, permissões, estoque, pedidos, pagamentos simulados, autenticação/MFA, ciclo de vida do tenant, LGPD e abertura de todas as telas.

## Documentação

1. [Especificação e arquitetura](docs/01-especificacao-e-arquitetura.md): decisões, isolamento, fluxos, riscos, plano de entregas
2. [Modelo de dados](docs/02-modelo-de-dados.md): tabelas, relacionamentos, regras de cálculo
3. [Telas](docs/03-telas.md)
4. [Segurança, LGPD e e-commerce](docs/04-seguranca-e-lgpd.md): pontos para validação jurídica
5. [Implantação, backup e operação](docs/05-implantacao-e-operacao.md)
6. [Dependências externas e limitações](docs/06-dependencias-externas.md)
7. [Envio para https://motostock.aicode.dev.br](docs/deploy/envio-producao.md): pacote, FTP, instalação pelo navegador (`/instalacao`), cron

## Estrutura

```
public/        raiz web (index.php, assets)
app/Core       kernel HTTP, sessão, CSRF, auth, MFA, tenant, permissões, auditoria
app/Repositories  acesso a dados com tenant_id obrigatório
app/Services   regras de negócio (estoque, pedidos, pagamentos, compras, tenants, relatórios)
app/Payments   gateways (simulado, Mercado Pago)
app/Controllers, app/Views
database/migrations, bin/, tests/, storage/ (fora da web), docs/
```
