# Envio para https://motostock.aicode.dev.br

O domínio hoje mostra a **versão antiga** (Vue + API). A nova substitui essa versão; a antiga é **guardada** numa pasta `_versao_anterior_<data>` no servidor (bloqueada para a web), nada é apagado.

Arquivos que já estão prontos neste computador (fora do Git):
- `deploy/.env.production`: com `APP_KEY` e `SETUP_TOKEN` já gerados. **Falta só o banco.** Guarde uma cópia da `APP_KEY` no cofre de senhas.
- `deploy/.deploy.env`: com `DEPLOY_TOKEN` gerado. **Falta usuário e senha do FTP** e conferir `FTP_PATH`.

## Primeira instalação

### 1. No cPanel (5 min)
1. **Bancos de dados MySQL** → crie o banco (ex.: `conta_motostock`) e um usuário com senha forte; dê **todos os privilégios** desse banco ao usuário. Use um banco **novo**, não o da versão antiga.
2. **Selecionar versão do PHP** para o domínio: 8.1 ou mais nova (8.2/8.3 recomendadas), com `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl` e `zip`.
3. **Domínios**: anote a pasta raiz de `motostock.aicode.dev.br` (é o `FTP_PATH`).
4. **SSL/TLS Status**: confirme o certificado do domínio.

### 2. Neste computador
1. Em `deploy/.env.production`, preencha `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (em geral `DB_HOST=localhost`, `DB_PORT=3306`).
2. Em `deploy/.deploy.env`, preencha `FTP_USER`, `FTP_PASS` e confira `FTP_PATH`.
3. Rode:
   ```powershell
   .\deploy\deploy.ps1
   ```
   O script roda os testes (não envia se algum falhar), monta o pacote, envia por FTP e executa o extrator no servidor.

   **Sem FTP?** Rode `.\deploy\deploy.ps1 -SomentePacote`, envie `deploy\dist\motostock-pacote.zip` e `deploy\dist\motostock-extrair.php` pelo **Gerenciador de Arquivos** do cPanel para a pasta raiz do domínio e abra o endereço que o script mostra (`/motostock-extrair.php?token=…`).

### 3. Criar o administrador
1. Abra `https://motostock.aicode.dev.br/instalacao?token=<SETUP_TOKEN do .env.production>`.
2. Todos os itens devem estar "OK". Preencha nome, e-mail e senha (12+ caracteres) → **Instalar**.
3. Entre em `/entrar`: o sistema pede para ativar a **verificação em duas etapas** (obrigatória para a plataforma).
4. Em **Plataforma › Planos**, crie pelo menos um plano público (preço e limites). Sem plano, o cadastro de lojas fica fechado.
5. No **Gerenciador de Arquivos**, edite o `.env` do servidor e **apague o valor de `SETUP_TOKEN`**.

### 4. Cron (cPanel › Cron Jobs)
A cada 15 minutos (cancela pedidos não pagos no prazo, encerra testes vencidos, limpa limites de login). Use o comando que a tela de instalação mostra, por exemplo:
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
