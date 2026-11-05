<?php
declare(strict_types=1);

namespace App\Support;

final class Labels
{
    public const MAP = [
        'order' => [
            'awaiting_payment' => 'Aguardando pagamento',
            'paid' => 'Pago',
            'picking' => 'Separando',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
        ],
        'payment' => [
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'rejected' => 'Recusado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Estornado',
        ],
        'pay_method' => [
            'online' => 'Online (Pix ou cartão no provedor)',
            'cash' => 'Dinheiro',
            'pix_manual' => 'Pix (conferido na loja)',
            'card_pos' => 'Cartão na maquininha',
            'transfer' => 'Transferência',
        ],
        'tenant' => [
            'trial' => 'Em teste',
            'active' => 'Ativa',
            'past_due' => 'Pagamento pendente',
            'suspended' => 'Suspensa',
            'cancelled' => 'Cancelada',
        ],
        'subscription' => [
            'trialing' => 'Em teste',
            'active' => 'Ativa',
            'past_due' => 'Pagamento pendente',
            'cancelled' => 'Cancelada',
        ],
        'movement' => [
            'entry' => 'Entrada',
            'exit' => 'Saída',
            'adjust' => 'Ajuste',
            'return' => 'Devolução',
            'reserve' => 'Reserva',
            'release' => 'Liberação de reserva',
        ],
        'purchase' => [
            'draft' => 'Rascunho',
            'ordered' => 'Enviado ao fornecedor',
            'partial' => 'Recebido em parte',
            'received' => 'Recebido',
            'cancelled' => 'Cancelado',
        ],
        'channel' => [
            'store' => 'Loja virtual',
            'manual' => 'Balcão / manual',
        ],
        'role' => [
            'owner' => 'Proprietário',
            'manager' => 'Gerente',
            'seller' => 'Atendente/vendedor',
        ],
        'data_request' => [
            'access' => 'Acesso aos dados',
            'correction' => 'Correção',
            'deletion' => 'Eliminação',
            'portability' => 'Portabilidade',
            'other' => 'Outro',
        ],
        'data_request_status' => [
            'open' => 'Aberto',
            'done' => 'Atendido',
            'rejected' => 'Negado (com justificativa)',
        ],
        'ship_kind' => [
            'delivery' => 'Entrega',
            'pickup' => 'Retirada na loja',
        ],
    ];

    public static function get(string $group, ?string $code): string
    {
        return self::MAP[$group][$code ?? ''] ?? (string) $code;
    }
}
