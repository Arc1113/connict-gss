<?php

namespace App\Support;

use RuntimeException;

final readonly class PostgresConnection
{
    public function __construct(
        public string $host,
        public string $port,
        public string $database,
        public string $username,
        public string $password,
        public string $sslmode,
    ) {}

    public static function configured(): self
    {
        $name = (string) config('database.default');
        $connection = config("database.connections.{$name}");

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('Backups require the configured PostgreSQL connection.');
        }

        $values = [
            'host' => (string) ($connection['host'] ?? '127.0.0.1'),
            'port' => (string) ($connection['port'] ?? '5432'),
            'database' => (string) ($connection['database'] ?? ''),
            'username' => (string) ($connection['username'] ?? ''),
            'password' => (string) ($connection['password'] ?? ''),
            'sslmode' => (string) ($connection['sslmode'] ?? 'prefer'),
        ];

        $url = $connection['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $parsed = parse_url($url);

            if ($parsed === false) {
                throw new RuntimeException('The configured PostgreSQL URL is invalid.');
            }

            $values['host'] = isset($parsed['host']) ? rawurldecode($parsed['host']) : $values['host'];
            $values['port'] = isset($parsed['port']) ? (string) $parsed['port'] : $values['port'];
            $values['database'] = isset($parsed['path'])
                ? rawurldecode(ltrim($parsed['path'], '/'))
                : $values['database'];
            $values['username'] = isset($parsed['user']) ? rawurldecode($parsed['user']) : $values['username'];
            $values['password'] = isset($parsed['pass']) ? rawurldecode($parsed['pass']) : $values['password'];

            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
                $values['sslmode'] = isset($query['sslmode']) ? (string) $query['sslmode'] : $values['sslmode'];
            }
        }

        if ($values['database'] === '' || $values['username'] === '') {
            throw new RuntimeException('The PostgreSQL backup connection is incomplete.');
        }

        return new self(...$values);
    }

    /**
     * @return list<string>
     */
    public function arguments(?string $database = null): array
    {
        $arguments = [
            '--host='.$this->host,
            '--port='.$this->port,
            '--username='.$this->username,
        ];

        if ($database !== null) {
            $arguments[] = '--dbname='.$database;
        }

        return $arguments;
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return [
            'PGPASSWORD' => $this->password,
            'PGSSLMODE' => $this->sslmode,
        ];
    }
}
