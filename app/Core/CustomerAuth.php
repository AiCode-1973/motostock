<?php
declare(strict_types=1);

namespace App\Core;

/** Login de clientes da loja. A chave de sessão inclui o tenant, então a conta só vale naquela loja. */
final class CustomerAuth
{
    private static function key(): string
    {
        return 'customer.' . Tenant::id();
    }

    public static function id(): ?int
    {
        $id = Session::get(self::key());
        return is_int($id) ? $id : null;
    }

    public static function customer(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $c = Database::connect()->fetch(
            'SELECT * FROM customers WHERE tenant_id = ? AND id = ? AND anonymized_at IS NULL',
            [Tenant::id(), $id]
        );
        if (!$c) {
            Session::remove(self::key());
        }
        return $c;
    }

    public static function login(int $customerId): void
    {
        Session::regenerate();
        Session::set(self::key(), $customerId);
    }

    public static function logout(): void
    {
        Session::remove(self::key());
        Session::regenerate();
    }
}
