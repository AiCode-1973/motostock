# MotoStock · Telas

As telas pedidas como protótipo foram construídas como telas reais e funcionais. Todas abrem com os dados de demonstração no teste `tests/SmokeTest.php`.

| Tela | Endereço | Perfil |
|---|---|---|
| Site e planos | `/` | público |
| Cadastro de empresa | `/cadastro` | público |
| Login, verificação em duas etapas, recuperação | `/entrar`, `/entrar/verificacao`, `/esqueci-senha` | equipe |
| Dashboard | `/painel` | todos da loja |
| Produtos (lista, cadastro, fotos, compatibilidade) | `/painel/produtos` | ver: todos · editar: gerente+ |
| Categorias e marcas | `/painel/catalogo` | gerente+ |
| Importar/exportar catálogo | `/painel/catalogo/importar` | gerente+ |
| Estoque, movimentação, histórico | `/painel/estoque` | ver: todos · mover: gerente+ |
| Fornecedores | `/painel/fornecedores` | gerente+ |
| Pedidos de compra e recebimento | `/painel/compras` | gerente+ |
| Clientes (com anonimização) | `/painel/clientes` | todos |
| Pedidos e venda no balcão | `/painel/pedidos`, `/painel/pedidos/novo` | todos |
| Relatórios e CSV | `/painel/relatorios` | gerente+ |
| Equipe, auditoria, configurações, privacidade | `/painel/usuarios`, `/painel/auditoria`, `/painel/configuracoes`, `/painel/privacidade` | proprietário |
| Assinatura e exportação de dados | `/painel/assinatura` | todos (exportação: proprietário) |
| Plataforma: visão geral, lojas, planos, auditoria | `/plataforma/*` | equipe MotoStock |
| Loja: catálogo com filtro por moto, produto, carrinho, checkout, pedido | `/loja/<slug>/…` ou `<slug>.dominio` | cliente |
| Loja: conta, meus pedidos, contato, políticas, solicitar meus dados | `/loja/<slug>/conta/…`, `/contato`, `/privacidade`… | cliente |

Estados tratados: vazio (com ação sugerida), validação por campo (`aria-invalid` + mensagem), confirmação antes de ações sensíveis, carregamento no envio (botão bloqueado contra clique duplo), erros 403/404/419/429/503 com mensagem clara. No celular, o menu vira gaveta e as tabelas viram blocos.
