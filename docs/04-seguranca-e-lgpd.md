# MotoStock · Segurança, privacidade e conformidade

> Este documento descreve controles técnicos. **Não é parecer jurídico** e o sistema não declara conformidade legal automática. Os pontos marcados com ⚖️ precisam de validação por advogado.

## Controles implementados (com teste)

| Ameaça | Controle | Onde | Teste |
|---|---|---|---|
| Acesso a dados de outra loja (IDOR) | Tenant pelo servidor, `WHERE tenant_id = ?` em todo SQL, FKs compostas, 404 para id de outra loja | `Core/App`, `Repositories`, migration | `TenantIsolationTest` (14 casos) |
| Escalada de privilégio | Permissão por rota no backend; menor privilégio por função | `Core/Permissions`, `routes.php` | `PermissionTest` |
| SQL injection | PDO com prepares reais (`EMULATE_PREPARES = false`); colunas e ordenações só vindas do código; `LIKE` escapado | `Core/Database` | indireto em todos |
| XSS | `e()` em toda saída; CSP `script-src 'self'` sem inline; nada de `style=`/`onclick=` | `helpers.php`, `App::secureHeaders` | `AuthTest::testOutputIsEscaped` |
| CSRF | Token por sessão em todo POST (exceto webhooks assinados) | `Core/Csrf` | `PermissionTest::testPostWithoutCsrfTokenIsRejected` |
| Força bruta | 5 tentativas por IP+e-mail e 30 por IP em 15 min; MFA também limitado; cadastro, recuperação e checkout limitados | `Core/RateLimiter` | `AuthTest::testBruteForceIsThrottled` |
| Senhas | `password_hash` (bcrypt), mínimo 10 caracteres, rehash automático, comparação em tempo constante mesmo para e-mail inexistente | `Core/Auth` | `AuthTest` |
| Sessão | Cookie HttpOnly + SameSite=Lax + Secure em HTTPS; id regenerado no login; expiração por inatividade (120 min) e absoluta (12 h); troca de senha derruba outras sessões | `Core/Session`, `Core/Auth` | `AuthTest` |
| MFA | TOTP (RFC 6238) opcional para lojistas e **obrigatório** para a plataforma (`PLATFORM_REQUIRE_MFA`); segredo cifrado | `Core/Totp`, `AccountController` | `AuthTest::testMfaFlow` |
| Recuperação de senha | Token aleatório, guardado só em hash, uso único, 1 h; resposta igual para e-mail inexistente | `UserService` | `AuthTest` |
| Segredos no banco | AES-256-GCM com `APP_KEY` (credenciais de gateway, MFA); nunca reexibidos na tela | `Core/Crypto` | `PaymentTest::testGatewayCredentialsAreEncryptedAtRest` |
| Pagamento forjado | Webhook com assinatura HMAC + deduplicação + consulta ao provedor + conferência de valor; retorno do navegador não paga | `PaymentService` | `PaymentTest` (10 casos) |
| Cobrança duplicada | Chave de idempotência local (UNIQUE) e enviada ao provedor | `PaymentService` | `PaymentTest::testStartingPaymentTwiceIsIdempotent` |
| Upload malicioso | Tipo pelo conteúdo (finfo + getimagesize), 2 MB, nome aleatório, fora de `public/`, servido por rota com tenant | `ProductService::addImage`, `MediaController` | `TenantIsolationTest::testMediaOfOtherTenantIsNotServed` |
| Injeção em planilhas | Células iniciadas por `= + - @` neutralizadas no CSV | `Support/Csv` | `LifecycleTest` |
| Redirecionamento aberto | "Voltar" só para o mesmo host; retorno do simulador só para página de pedido da loja | `App::backUrl`, `SimulatorController` | — |
| Clickjacking / sniffing | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, HSTS em HTTPS | `App::secureHeaders` | `AuthTest::testSecurityHeadersArePresent` |
| Logs com dados sensíveis | `Audit::log` remove chaves com senha/token/segredo/cartão; login falho registra só hash parcial do e-mail | `Core/Audit` | `PaymentTest` |

## Pendências de segurança (antes de produção)

- HTTPS obrigatório no servidor (redirecionamento 80 → 443) e `TRUST_PROXY` só atrás de proxy confiável.
- Varredura de dependências: o projeto não usa bibliotecas de terceiros no PHP; acompanhar avisos de segurança do PHP e do MySQL/MariaDB e aplicar atualizações mensais (ou imediatas, se críticas).
- Teste de intrusão externo e revisão de configuração do servidor.
- Expurgo definitivo de tenants após `retention_until`: hoje é manual (nada é apagado automaticamente).
- Rotação da `APP_KEY` exige recifrar `gateway_credentials` e `users.mfa_secret` (script ainda não existe).

## LGPD

### Papéis ⚖️
- **Loja (tenant)**: controladora dos dados dos seus clientes (decide finalidade e meios da venda).
- **MotoStock**: operadora desses dados (trata em nome da loja) e controladora dos dados de cadastro das próprias lojas e usuários.
- Precisa de: contrato/termo de tratamento de dados entre MotoStock e cada loja, política de privacidade da plataforma e indicação de encarregado (DPO) de cada parte.

### Princípios aplicados
- **Necessidade**: cliente da loja informa só nome, e-mail e celular; CPF é opcional (para nota fiscal). Endereço só para entrega.
- **Finalidade e transparência**: aceite da política com **versão** registrada (`customers.privacy_version`, `orders.privacy_version`); ofertas só com opt-in separado, desmarcado por padrão.
- **Segurança**: controles da tabela acima; a plataforma não vê clientes e pedidos das lojas.
- **Direitos do titular**: página "Solicitar meus dados" em cada loja → fila em *Painel › Privacidade*, com registro de resposta; anonimização de cliente que mantém valores dos pedidos por obrigação fiscal; exportação completa da loja em JSON.

### Retenção ⚖️ (proposta para validação)
| Dado | Proposta |
|---|---|
| Pedidos, pagamentos, itens | 5 anos (prazo fiscal), depois anonimizar |
| Cadastro de cliente sem compra | 2 anos sem atividade, depois anonimizar |
| Logs de auditoria | 1 ano (6 meses mínimos do Marco Civil para registros de acesso a aplicação) |
| Tenant cancelado | 90 dias (`TENANT_RETENTION_DAYS`) para exportação, depois expurgo manual auditado |
| `rate_limits` | 24 h (limpo pelo cron) |

## Comércio eletrônico ⚖️

| Exigência (Decreto 7.962/2013 e CDC) | Como o sistema atende | Validar |
|---|---|---|
| Identificação do fornecedor (razão social, CNPJ, endereço, contato) | Rodapé e página Contato de toda loja | Conferir dados de cada loja |
| Informações claras sobre produto, preço, frete e prazos | Página do produto, carrinho e checkout com total por forma de entrega | Textos das descrições são da loja |
| Resumo do pedido antes da confirmação | Checkout mostra itens, subtotal e total por forma de entrega | — |
| Confirmação imediata do recebimento | Página do pedido + e-mail com link | Depende de e-mail em produção |
| Atendimento eficaz | Contato, WhatsApp e horário configuráveis | — |
| Direito de arrependimento (7 dias, art. 49) | Texto modelo e aviso no checkout; cancelamento/reembolso no painel | Fluxo de devolução e estorno com o provedor |
| Políticas de troca, entrega e privacidade | Modelos marcados "[MODELO — revisar com advogado]", versão a cada publicação | **Todos os textos** |
