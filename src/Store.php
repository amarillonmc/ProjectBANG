<?php
declare(strict_types=1);

namespace Imaginary;

use PDO;
use Throwable;

/** Small PDO store: one transaction serializes each room command. */
final class Store
{
    private $pdo;
    private $driver;
    private $prefix;
    private $transaction = false;

    public function __construct(array $config)
    {
        $this->driver = $config['driver'] ?? 'sqlite';
        $this->prefix = $config['prefix'] ?? 'im_';
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,24}$/D', $this->prefix)) {
            throw new \RuntimeException('Invalid database table prefix.');
        }
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        if ($this->driver === 'sqlite') {
            $path = $config['sqlite_path'];
            if ($path !== ':memory:' && !is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
                throw new \RuntimeException('Cannot create private database directory.');
            }
            $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
            $this->pdo->exec('PRAGMA busy_timeout=8000');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
            $this->pdo->exec('PRAGMA journal_mode=WAL');
        } elseif ($this->driver === 'mysql') {
            $host = $config['host'] ?? 'localhost';
            $port = (int) ($config['port'] ?? 3306);
            $database = $config['database'] ?? '';
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $host) || !preg_match('/^[a-zA-Z0-9_-]+$/D', $database)) {
                throw new \RuntimeException('Invalid MySQL host/database setting.');
            }
            $this->pdo = new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4', $config['username'] ?? '', $config['password'] ?? '', $options);
        } else {
            throw new \RuntimeException('Unsupported database driver.');
        }
        $this->initialize();
    }

    public function table(string $name): string
    {
        if (!in_array($name, ['users', 'builds', 'rooms', 'members', 'requests', 'events', 'feedback', 'rate_limits'], true)) {
            throw new \InvalidArgumentException('Unknown database table.');
        }
        return '`' . $this->prefix . $name . '`';
    }

    public function one(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public function lockSuffix(): string
    {
        return $this->driver === 'mysql' ? ' FOR UPDATE' : '';
    }

    public function transaction(callable $callback)
    {
        if ($this->transaction) {
            throw new \LogicException('Nested transactions are not supported.');
        }
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('BEGIN IMMEDIATE');
        } else {
            $this->pdo->beginTransaction();
        }
        $this->transaction = true;
        try {
            $result = $callback($this);
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('COMMIT');
            } else {
                $this->pdo->commit();
            }
            $this->transaction = false;
            return $result;
        } catch (Throwable $error) {
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('ROLLBACK');
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->transaction = false;
            throw $error;
        }
    }

    /** Atomic fixed-window throttling, using only the real peer IP (no spoofable headers). */
    public function rateLimit(string $key, int $maximum, int $seconds): bool
    {
        $now = time();
        $bucket = hash('sha256', $key . ':' . intdiv($now, $seconds));
        return $this->transaction(function () use ($bucket, $maximum, $seconds, $now) {
            $table = $this->table('rate_limits');
            if ($this->driver === 'mysql') {
                $this->execute("INSERT IGNORE INTO $table (bucket, hits, expires_at) VALUES (?, 0, ?)", [$bucket, $now + $seconds * 2]);
            } else {
                $this->execute("INSERT OR IGNORE INTO $table (bucket, hits, expires_at) VALUES (?, 0, ?)", [$bucket, $now + $seconds * 2]);
            }
            $row = $this->one("SELECT hits FROM $table WHERE bucket = ?" . $this->lockSuffix(), [$bucket]);
            if ((int) $row['hits'] >= $maximum) {
                return false;
            }
            $this->execute("UPDATE $table SET hits = hits + 1 WHERE bucket = ?", [$bucket]);
            // Bounded by rate-window lifetime. Indexed cleanup is inexpensive on shared hosts.
            $this->execute("DELETE FROM $table WHERE expires_at < ?", [$now]);
            return true;
        });
    }

    private function initialize(): void
    {
        $text = $this->driver === 'mysql' ? 'LONGTEXT' : 'TEXT';
        $suffix = $this->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin' : '';
        $definitions = [
            'users' => 'id VARCHAR(32) PRIMARY KEY, name VARCHAR(80) NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, created_at BIGINT NOT NULL',
            'builds' => "id VARCHAR(32) PRIMARY KEY, user_id VARCHAR(32) NOT NULL, data $text NOT NULL, updated_at BIGINT NOT NULL",
            'rooms' => "code VARCHAR(6) PRIMARY KEY, host_id VARCHAR(32) NOT NULL, status VARCHAR(12) NOT NULL, revision BIGINT NOT NULL, data $text NOT NULL, updated_at BIGINT NOT NULL",
            'members' => 'room_code VARCHAR(6) NOT NULL, user_id VARCHAR(32) NOT NULL, PRIMARY KEY (room_code, user_id)',
            'requests' => "user_id VARCHAR(32) NOT NULL, request_id VARCHAR(80) NOT NULL, payload_hash VARCHAR(64) NOT NULL, response $text NOT NULL, created_at BIGINT NOT NULL, PRIMARY KEY (user_id, request_id)",
            'events' => "id VARCHAR(32) PRIMARY KEY, room_code VARCHAR(6) NOT NULL, revision BIGINT NOT NULL, user_id VARCHAR(32) NOT NULL, kind VARCHAR(40) NOT NULL, data $text NOT NULL, created_at BIGINT NOT NULL",
            'feedback' => 'id VARCHAR(32) PRIMARY KEY, user_id VARCHAR(32) NOT NULL, room_code VARCHAR(6), body TEXT NOT NULL, created_at BIGINT NOT NULL',
            'rate_limits' => 'bucket VARCHAR(64) PRIMARY KEY, hits INT NOT NULL, expires_at BIGINT NOT NULL',
        ];
        foreach ($definitions as $name => $definition) {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . $this->table($name) . " ($definition)" . $suffix);
        }
        $indexes = [
            'builds_owner' => ['builds', 'user_id'],
            'rooms_host' => ['rooms', 'host_id, status'],
            'members_user' => ['members', 'user_id'],
            'events_room' => ['events', 'room_code, revision'],
            'requests_time' => ['requests', 'created_at'],
            'rate_expiry' => ['rate_limits', 'expires_at'],
        ];
        foreach ($indexes as $name => $spec) {
            $index = $this->prefix . $name;
            if ($this->driver === 'mysql') {
                $existing = $this->one('SHOW INDEX FROM ' . $this->table($spec[0]) . ' WHERE Key_name = ?', [$index]);
                if (!$existing) {
                    try {
                        $this->pdo->exec('CREATE INDEX `' . $index . '` ON ' . $this->table($spec[0]) . ' (' . $spec[1] . ')');
                    } catch (\PDOException $error) {
                        // Two first requests may create the same index concurrently.
                        if ((int) ($error->errorInfo[1] ?? 0) !== 1061) {
                            throw $error;
                        }
                    }
                }
            } else {
                $this->pdo->exec('CREATE INDEX IF NOT EXISTS `' . $index . '` ON ' . $this->table($spec[0]) . ' (' . $spec[1] . ')');
            }
        }
    }
}
