# MotoStock · O que depende de credenciais, contratos ou serviços externos

Nada abaixo está "pronto para produção" só porque existe código. A coluna **Situação** diz o que foi verificado.

| Recurso | O que existe no código | Situação | Para funcionar em produção |
|---|---|---|---|
| **Mercado Pago (Pix e cartão via Checkout Pro)** | `MercadoPagoGateway`: criação de preferência com idempotência, validação da assinatura `x-signature`, consulta do pagamento, tradução de estados; tela de credenciais por loja | Testado com respostas simuladas (formato da documentação pública). **Não testado contra a API real** | Cada loja cria aplicação no Mercado Pago, informa Access Token e assinatura secreta do webhook, cadastra a URL de notificação e faz um pedido em sandbox antes de ativar |
| Estorno automático no provedor | — | **Não implementado** | Hoje o estorno é feito no painel do Mercado Pago; o MotoStock só registra o reembolso e o estoque |
| Gateway simulado | `SimulatedGateway` com notificação assinada | Testado (10 casos) | Somente desenvolvimento/homologação; `SIMULATED_GATEWAY_ENABLED=false` em produção |
| **Cobrança da assinatura das lojas** (recorrência) | Planos, assinaturas, estados e limites | Cobrança **manual** pela equipe | Contratar gateway com assinatura recorrente (ex.: Mercado Pago Assinaturas, Asaas, Iugu, Pagar.me) e integrar webhooks de cobrança |
| **E-mail transacional** | `Mailer` com drivers `log` e `mail()` | `log` testado; `mail()` depende do servidor | Para entrega confiável: provedor SMTP/API (Amazon SES, Mailgun, Brevo, SMTP da hospedagem com SPF/DKIM/DMARC) e driver SMTP (a implementar) |
| **Cotação de frete** (Correios, Melhor Envio, transportadoras) | Modalidades manuais com valor, prazo, frete grátis acima de X e UFs; peso do produto já é cadastrado | Manual testado | Contrato e credenciais do serviço; implementar provedor de cotação |
| Rastreamento automático | Campo de código de rastreio | Manual | API da transportadora |
| **Nota fiscal (NF-e/NFC-e)** | — | **Não implementado** | Emissor fiscal (ex.: Focus NFe, eNotas, PlugNotas), certificado digital A1 de cada loja e regras tributárias validadas por contador |
| **Domínio próprio da loja** | Resolução por host, tabela de domínios, permissão por plano | Resolução testada; cadastro e verificação **manuais** | Tela de cadastro, verificação de DNS (TXT) e emissão de certificado por domínio |
| Subdomínio por loja | Resolução por `<slug>.APP_BASE_DOMAIN` | Testado localmente (`*.localhost`) | DNS curinga e certificado curinga |
| QR code do MFA | Chave e URI `otpauth://` exibidas | Funcional (digitação manual da chave) | Opcional: gerar QR (biblioteca ou serviço local) |
| Variações de produto (tamanho/cor) | — | **Pendente** (cada variação hoje é um SKU) | Desenvolvimento |
| Estados de pedido personalizados por loja | Máquina de estados fixa e testada | **Pendente** | Desenvolvimento |
| Permissões finas por usuário | Três funções fixas | **Pendente** | Desenvolvimento |
| Banco dedicado por cliente | Ponto de extensão `Database::forTenant()` e plano em `docs/01` | **Pendente** | Desenvolvimento + infraestrutura |
| Textos legais (privacidade, trocas, termos da plataforma, contrato de operador) | Modelos marcados para revisão | **Não validados** | Advogado |
