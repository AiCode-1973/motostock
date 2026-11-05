<?php
declare(strict_types=1);

namespace App\Services;

/**
 * MODELOS iniciais de textos legais. NÃO são pareceres jurídicos: cada loja deve revisar com
 * advogado antes de publicar (CDC, Decreto 7.962/2013, LGPD). O aviso aparece no painel.
 */
final class LegalTemplates
{
    public const REVIEW_NOTICE = '[MODELO — revisar com advogado antes de publicar]';

    public static function privacy(): string
    {
        return self::REVIEW_NOTICE . "\n\n"
            . "Quem somos: esta loja (identificada no rodapé) é a controladora dos dados pessoais coletados aqui. "
            . "A plataforma MotoStock atua como operadora, tratando os dados em nome da loja.\n\n"
            . "Dados coletados e finalidade: nome, e-mail, telefone e endereço, para processar e entregar seu pedido, "
            . "emitir documentos fiscais e prestar atendimento. CPF somente quando necessário para nota fiscal.\n\n"
            . "Pagamentos: os dados de cartão são informados diretamente ao provedor de pagamento e não ficam armazenados na loja.\n\n"
            . "Compartilhamento: provedor de pagamento, transportadora e obrigações legais.\n\n"
            . "Retenção: pelo tempo necessário ao pedido e às obrigações legais e fiscais.\n\n"
            . "Seus direitos (LGPD, art. 18): confirmação, acesso, correção, anonimização, portabilidade, eliminação e informação "
            . "sobre compartilhamento. Use a página \"Solicitar meus dados\" ou o contato da loja.";
    }

    public static function returns(): string
    {
        return self::REVIEW_NOTICE . "\n\n"
            . "Direito de arrependimento: compras feitas fora do estabelecimento podem ser canceladas em até 7 dias corridos "
            . "a partir do recebimento (CDC, art. 49), com devolução integral dos valores pagos, inclusive o frete.\n\n"
            . "Troca por defeito: até 90 dias para produtos duráveis (CDC, art. 26).\n\n"
            . "Peça incompatível: confira a compatibilidade com sua moto antes da compra. Em caso de dúvida, fale com a loja.\n\n"
            . "Como solicitar: entre em contato pelos canais da página Contato informando o número do pedido.";
    }

    public static function shipping(): string
    {
        return self::REVIEW_NOTICE . "\n\n"
            . "Formas de entrega, prazos e custos são mostrados no carrinho antes da confirmação do pedido. "
            . "O prazo começa a contar após a confirmação do pagamento.";
    }
}
