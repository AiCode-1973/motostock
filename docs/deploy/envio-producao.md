# Envio para https://motostock.aicode.dev.br

O domínio hoje mostra a **versão antiga** (Vue + API). A nova substitui essa versão; a antiga é **guardada** numa pasta `_versao_anterior_<data>` no servidor (bloqueada para a web), nada é apagado.

Arquivos que já estão prontos neste computador (fora do Git):
- `deploy/.env.production`: com `APP_KEY` já gerada. **Falta o banco e o administrador.** Guarde uma cópia da `APP_KEY` no cofre de senhas.
- `deploy/.deploy.env`: com `DEPLOY_TOKEN` gerado. **Falta usuário e senha do FTP** e conferir `FTP_PATH`.

## Primeira instalação

### 1. No cPanel (5 min)
1. **Bancos de dados MySQL** → crie o banco (ex.: `conta_motostock`) e um usuário com senha forte; dê **todos os privilégios** desse banco ao usuário. Use um banco **novo**, não o da versão antiga.
2. **Selecionar versão do PHP** para o domínio: 8.1 ou mais nova (8.2/8.3 recomendadas), com `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl` e `zip`.
3. **Domínios**: anote a pasta raiz de `motostock.aicode.dev.br` (é o `FTP_PATH`).
4. **SSL/TLS Status**: confirme o certificado do domínio.

### 2. Neste computador
1. Em `deploy/.env.production`, preencha:
   - o banco: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (em geral `DB_HOST=localhost`, `DB_PORT=3306`);
   - o administrador: `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` (12+ caracteres). A conta é criada no envio e a senha é apagada do `.env` do servidor e deste arquivo logo em seguida.
2. Em `deploy/.deploy.env`, preencha `FTP_USER`, `FTP_PASS` e confira `FTP_PATH`.
3. Rode:
   ```powershell
   .\deploy\deploy.ps1
   ```
   O script roda os testes (não envia se algum falhar), monta o pacote, envia por FTP e executa o extrator no servidor.

   **Sem FTP?** Rode `.\deploy\deploy.ps1 -SomentePacote`, envie `deploy\dist\motostock-pacote.zip` e `deploy\dist\motostock-extrair.php` pelo **Gerenciador de Arquivos** do cPanel para a pasta raiz do domínio e abra o endereço que o script mostra (`/motostock-extrair.php?token=…`).

### 3. Primeiro acesso
O resultado do envio deve mostrar `✓ Administrador criado: <seu e-mail>`.
1. Entre em `https://motostock.aicode.dev.br/entrar` com esse e-mail e senha. O sistema pede para ativar a **verificação em duas etapas** (obrigatória para a plataforma): use o Google ou Microsoft Authenticator.
2. Em **Plataforma › Planos**, crie pelo menos um plano público (preço e limites). Sem plano, o cadastro de lojas fica fechado.

**Plano B** (se o administrador não for criado, por exemplo banco errado no primeiro envio): corrija o `.env` do servidor e abra `https://motostock.aicode.dev.br/instalacao?token=<SETUP_TOKEN do .env.production>`, que verifica o servidor e tem um formulário para criar a conta. Depois, apague o valor de `SETUP_TOKEN` no `.env` do servidor.

### 4. Cron (cPanel › Cron Jobs)
A cada 15 minutos (cancela pedidos não pagos no prazo, encerra testes vencidos, limpa limites de login). Troque `<conta>` e `<pasta-do-domínio>` pelos seus (a pasta aparece em cPanel › Domínios):
```
*/15 * * * * php /home/<conta>/<pasta-do-domínio>/bin/cron.php >> /home/<conta>/<pasta-do-domínio>/storage/logs/cron.log 2>&1
```
Se `php` não for encontrado, use o caminho da versão escolhida (ex.: `/usr/local/bin/php` ou `/opt/cpanel/ea-php83/root/usr/bin/php`).

### 5. Conferência
- [ ] `https://motostock.aicode.dev.br` abre o site novo com os planos.
- [ ] `https://motostock.aicode.dev.br/.env` e `/app/routes.php` retornam **403**.
- [ ] Cadastre uma loja de teste, crie um produto, abra `/loja/<endereço>`, faça um pedido "combinar com a loja" e registre o pagamento no balcão.
- [ ] "Esqueci minha senha" chega no e-mail (driver `mail` do servidor). Se não chegar, veja `docs/06` (SMTP).
- [ ] Depois de conferir, a pasta `_versao_anterior_*` pode ser baixada como backup e removida.

## Atualizações
`.\deploy\deploy.ps1` de novo. O `.env` do servidor é preservado e as migrations novas são aplicadas sozinhas. Faça backup do banco antes (cPanel › Backup ou phpMyAdmin › Exportar).

## O que fica diferente do local
- Lojas em `https://motostock.aicode.dev.br/loja/<endereço>`. Subdomínio por loja (`loja.motostock.aicode.dev.br`) exige DNS curinga e SSL curinga; quando houver, mude `STORE_USE_SUBDOMAINS=true`.
- Pagamento online desligado até cada loja configurar o Mercado Pago (o simulado fica desligado em produção).
- Sem dados de demonstração. Se quiser uma loja demo pública, peça: o seed precisa de adaptação para rodar sem terminal.
