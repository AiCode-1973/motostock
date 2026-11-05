<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** Acesso ao banco via PDO com consultas preparadas reais (sem emulação). */
final class Database
{
    private static ?Database $shared = null;
    private int $txDepth = 0;

    public function __construct(private PDO $pdo)
    {
    }

    public static function connect(): self
    {
        if (self::$shared === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::int('DB_PORT', 3306),
                Env::get('DB_DATABASE', 'motostock')
            );
            $pdo = new PDO($dsn, Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                // rowCount() passa a contar linhas encontradas, não só as alteradas (UPDATE condicional).
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
            $pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO'");
            self::$shared = new self($pdo);
        }
        return self::$shared;
    }

    /**
     * Ponto único para obter a conexão de um tenant. Hoje todos usam o banco compartilhado;
     * planos com banco dedicado passarão a resolver a conexão aqui (docs/01, seção 4).
     */
    public static function forTenant(int $tenantId): self
    {
        return self::connect();
    }

    public static function reset(): void
    {
        self::$shared = null;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k);
            $type = match (true) {
                is_int($v) => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_INT,
                $v === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($key, is_bool($v) ? (int) $v : $v, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** Insere com colunas vindas do código (nunca do usuário). */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        foreach ($cols as $c) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $c)) {
                throw new \InvalidArgumentException('Coluna inválida');
            }
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn($c) => ':' . $c, $cols))
        );
        $this->run($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** Transação com suporte a aninhamento (só a mais externa confirma). */
    public function transaction(callable $fn): mixed
    {
        if ($this->txDepth === 0) {
            $this->pdo->beginTransaction();
        }
        $this->txDepth++;
        try {
            $result = $fn($this);
            $this->txDepth--;
            if ($this->txDepth === 0) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            $this->txDepth--;
            if ($this->txDepth === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
