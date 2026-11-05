# MotoStock · Implantação e operação

## Requisitos

- PHP 8.1 ou mais novo (testado em 8.3) com `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl`.
- MySQL 8.0.16+ ou MariaDB 10.6+ (testado em MariaDB 10.11).
- Apache com `mod_rewrite` (ou Nginx), HTTPS.
- Cron (cPanel › Cron Jobs serve).

> Hospedagem sem terminal (cPanel): use o pacote `deploy/deploy.ps1` e a instalação pelo navegador em `/instalacao?token=SETUP_TOKEN`. Roteiro em [deploy/envio-producao.md](deploy/envio-producao.md).

## Passo a passo (hospedagem compatível com PHP e MySQL)

1. **Banco**: crie um banco `utf8mb4` e um usuário só para ele, com permissões de `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES` (as três últimas só durante migrations, se a hospedagem permitir separar).
2. **Arquivos**: envie o projeto para uma pasta **fora** do `public_html` (ex.: `/home/conta/motostock`) e aponte o domínio para `motostock/public`.
   Se a hospedagem não deixar mudar a raiz do domínio, coloque o projeto inteiro na raiz: o `.htaccess` da raiz bloqueia `app/`, `bin/`, `database/`, `docs/`, `storage/`, `tests/` e arquivos ocultos (`.env`).
3. **Configuração**: copie `.env.example` para `.env` e preencha. Gere a chave com `php bin/generate-key.php`. Em produção:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://app.motostock.com.br
   APP_BASE_DOMAIN=motostock.com.br
   STORE_USE_SUBDOMAINS=true
   STORE_PATH_FALLBACK=false
   PLATFORM_REQUIRE_MFA=true
   SIMULATED_GATEWAY_ENABLED=false
   PAYMENT_GATEWAY=mercadopago
   MAIL_DRIVER=mail
   ```
   Permissões: `.env` 600; `storage/` gravável pelo PHP (750).
4. **Esquema**: `php bin/migrate.php` (sem terminal: Cron Jobs com o comando uma única vez).
5. **Administrador**: `php bin/create-platform-admin.php voce@empresa.com.br "Seu Nome"`. A senha provisória aparece uma vez. Entre, troque a senha e ative a verificação em duas etapas (obrigatória para a plataforma).
6. **Planos**: em *Plataforma › Planos*, crie ao menos um plano público. Sem plano, o cadastro de lojas fica fechado.
7. **Subdomínios**: DNS curinga `*.motostock.com.br` (A/CNAME para o servidor) e certificado curinga (Let's Encrypt com validação DNS ou o AutoSSL da hospedagem, se cobrir curingas). Configure o virtual host para aceitar `*.motostock.com.br` na mesma pasta `public/`.
8. **Cron** a cada 15 minutos:
   ```
   */15 * * * * php /home/conta/motostock/bin/cron.php >> /home/conta/motostock/storage/logs/cron.log 2>&1
   ```
9. **Conferência**: abra o site, crie uma loja de teste, faça um pedido "combinar com a loja" e registre o pagamento no balcão.

## Desenvolvimento local

```bash
cp .env.example .env            # ajuste DB_* e APP_KEY
php bin/migrate.php
php bin/seed-demo.php           # dados de demonstração identificados
php -S localhost:8080 -t public public/router.php
```
Lojas: `http://localhost:8080/loja/ze-motopecas` ou `http://ze-motopecas.localhost:8080` (navegadores resolvem `*.localhost`). Contas demo e senha: `bin/seed-demo.php`.

Testes (usam o banco de `.env.testing`, que é apagado; o nome precisa conter `test`):
```bash
php bin/test.php
```

## Domínio próprio da loja (recurso configurável)

1. Plano com "Permite domínio próprio" e `ALLOW_CUSTOM_DOMAINS=true`.
2. Cadastrar em `tenant_domains` (`kind = custom`) com `verification_token`.
3. A loja cria um registro TXT `_motostock.loja.com.br` com o token e um CNAME para `lojas.motostock.com.br`.
4. Verificar o TXT e preencher `verified_at`; emitir certificado para o domínio.
A tela e a verificação automática do passo 4 **ainda não existem** (hoje é manual pela equipe; ver `docs/06`).

## Backup e restauração

- **Banco**: dump diário com retenção de 30 dias, criptografado e fora do servidor:
  ```
  mysqldump --single-transaction --routines --default-character-set=utf8mb4 -u USER -p BANCO | gzip > storage/backups/motostock-$(date +%F).sql.gz
  ```
  Envie para armazenamento externo (S3, Backblaze, Google Drive da empresa) e apague a cópia local após o envio.
- **Arquivos**: `storage/uploads/` (imagens por loja) e o `.env` (guarde a `APP_KEY` em cofre de senhas: sem ela, credenciais e MFA cifrados se perdem).
- **Restauração**: restaurar o dump em banco novo → apontar `.env` → `php bin/migrate.php` (aplica migrations mais novas) → conferir login, uma loja e um pedido. **Teste a restauração uma vez por trimestre** e registre o resultado.
- **Restauração de uma loja só**: use a exportação JSON (*Plataforma › Lojas › Exportar*), que permite conferir e reimportar dados de um tenant sem afetar os demais (importador ainda manual).

## Operação

| Rotina | Frequência |
|---|---|
| Ver `storage/logs/php-error.log` e `cron.log` | diária |
| *Plataforma › Visão geral*: testes terminando, falhas de webhook | diária |
| Atualizar PHP/MySQL da hospedagem; revisar avisos de segurança | mensal |
| Teste de restauração de backup | trimestral |
| Revisar contas da plataforma e MFA | trimestral |

## Atualizações do sistema

1. Backup do banco. 2. Enviar arquivos novos (sem sobrescrever `.env` e `storage/`). 3. `php bin/migrate.php`. 4. Conferir login e uma loja. Migrations são somente aditivas; nunca editar uma migration já aplicada em produção.
