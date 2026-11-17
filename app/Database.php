<?php
declare(strict_types=1);

namespace Bio;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Creates the application's PDO connection and prepares its additive schema.
 */
final class Database
{
    private static ?PDO $connection = null;

    private const DEFAULT_THEME = [
        'background'         => '#f5f1eb',
        'accent'             => '#147d69',
        'text'               => '#233b35',
        'cardBackground'     => '#ffffff',
        'font'               => 'sans',
        'radius'             => 'rounded',
        'style'              => 'solid',
        'buttonStyle'        => 'filled',
        'titleSize'          => 'xl',
        'backgroundType'     => 'solid',
        'backgroundGradient' => '',
        'backgroundImage'    => '',
        'colorScheme'        => 'light',
        'cardSize'           => 'md',
        'cardAlign'          => 'left',
    ];

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $url = trim((string) (getenv('DATABASE_URL') ?: ''));
        if ($url === '') {
            $path = dirname(__DIR__) . '/var/bio.sqlite';
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Não foi possível preparar o diretório local do banco.');
            }

            if (!extension_loaded('pdo_sqlite')) {
                throw new RuntimeException('O fallback SQLite requer a extensão pdo_sqlite.');
            }

            $pdo = new PDO('sqlite:' . $path);
        } else {
            [$dsn, $username, $password, $options] = self::parseUrl($url);
            try {
                $pdo = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $exception) {
                // Deliberately avoid including the DSN or the driver message: URLs can contain secrets.
                throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $exception);
            }
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        self::$connection = $pdo;

        return $pdo;
    }

    public static function migrate(): void
    {
        $pdo = self::connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        self::createMigrationTable($pdo, $driver);
        self::runMigration($pdo, '001_schema_v1', static function () use ($pdo, $driver): void {
            self::createApplicationTables($pdo, $driver);
        });
        self::runMigration($pdo, '002_seed_pages_v1', static function () use ($pdo, $driver): void {
            self::seedPages($pdo, $driver);
        });
    }

    /** @return array{0:string,1:?string,2:?string,3:array<int|string,mixed>} */
    private static function parseUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme'])) {
            throw new RuntimeException('DATABASE_URL inválida.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme === 'sqlite') {
            $path = (string) ($parts['path'] ?? '');
            if ($path === '' && isset($parts['host'])) {
                $path = (string) $parts['host'];
            }
            if ($path === '') {
                throw new RuntimeException('DATABASE_URL SQLite inválida.');
            }
            if (str_starts_with($path, '//')) {
                $path = substr($path, 2);
            }
            return ['sqlite:' . rawurldecode($path), null, null, []];
        }

        if ($scheme !== 'mysql') {
            throw new RuntimeException('DATABASE_URL deve usar MySQL.');
        }

        $host = (string) ($parts['host'] ?? '');
        $database = ltrim((string) ($parts['path'] ?? ''), '/');
        if ($host === '' || $database === '') {
            throw new RuntimeException('DATABASE_URL MySQL inválida.');
        }

        $dsn = 'mysql:host=' . $host;
        if (isset($parts['port'])) {
            $dsn .= ';port=' . (int) $parts['port'];
        }
        $dsn .= ';dbname=' . rawurldecode($database) . ';charset=utf8mb4';

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Keep TLS settings requested by a managed/external DSN. Unknown query keys are ignored.
        if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $sslCa = $query['ssl-ca'] ?? $query['ssl_ca'] ?? null;
            $sslCert = $query['ssl-cert'] ?? $query['ssl_cert'] ?? null;
            $sslKey = $query['ssl-key'] ?? $query['ssl_key'] ?? null;
            $sslVerify = $query['ssl-verify-server-cert'] ?? $query['ssl_verify_server_cert'] ?? null;
            $sslMode = strtolower((string) ($query['ssl-mode'] ?? $query['ssl_mode'] ?? ''));
            $sslSetting = $query['ssl'] ?? null;
            if (($sslCa === null || $sslCa === '') && is_string($sslSetting) && trim($sslSetting) !== '') {
                $decodedSslSetting = rawurldecode(trim($sslSetting));
                if (is_readable($decodedSslSetting)) {
                    $sslCa = $decodedSslSetting;
                }
            }
            $tlsFlag = $query['tls'] ?? false;
            $tlsRequested = in_array($sslMode, ['required', 'verify_ca', 'verify_identity'], true)
                || filter_var($tlsFlag, FILTER_VALIDATE_BOOL)
                || (is_string($sslSetting) && !in_array(
                    strtolower(trim($sslSetting)),
                    ['', '0', 'false', 'off', 'no'],
                    true
                ));
            if (is_string($sslCa) && $sslCa !== '') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = rawurldecode($sslCa);
            }
            if (defined('PDO::MYSQL_ATTR_SSL_CERT') && is_string($sslCert) && $sslCert !== '') {
                $options[PDO::MYSQL_ATTR_SSL_CERT] = rawurldecode($sslCert);
            }
            if (defined('PDO::MYSQL_ATTR_SSL_KEY') && is_string($sslKey) && $sslKey !== '') {
                $options[PDO::MYSQL_ATTR_SSL_KEY] = rawurldecode($sslKey);
            }
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') && $sslVerify !== null) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = filter_var(
                    $sslVerify,
                    FILTER_VALIDATE_BOOL,
                    FILTER_NULL_ON_FAILURE
                ) ?? true;
            }
            if ($tlsRequested && !isset($options[PDO::MYSQL_ATTR_SSL_CA])) {
                $caBundle = '/etc/ssl/certs/ca-certificates.crt';
                if (is_readable($caBundle)) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caBundle;
                }
            }
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') && $sslVerify === null && $tlsRequested) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = in_array(
                    $sslMode,
                    ['verify_ca', 'verify_identity'],
                    true
                );
            }
        }

        return [
            $dsn,
            isset($parts['user']) ? rawurldecode((string) $parts['user']) : null,
            isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null,
            $options,
        ];
    }

    private static function createMigrationTable(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (' .
                'version VARCHAR(100) NOT NULL PRIMARY KEY,' .
                'applied_at DATETIME NOT NULL' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'version TEXT NOT NULL PRIMARY KEY,' .
            'applied_at TEXT NOT NULL' .
            ')'
        );
    }

    /** @param callable():void $callback */
    private static function runMigration(PDO $pdo, string $version, callable $callback): void
    {
        $statement = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version LIMIT 1');
        $statement->execute(['version' => $version]);
        if ($statement->fetchColumn() !== false) {
            return;
        }

        $transactionStarted = false;
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $transactionStarted = true;
            }
            $callback();
            $insert = $pdo->prepare(
                'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)'
            );
            $insert->execute([
                'version' => $version,
                'applied_at' => gmdate('Y-m-d H:i:s'),
            ]);
            // MySQL implicitly commits DDL; do not call commit() after that implicit commit.
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function createApplicationTables(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS pages (' .
                'id CHAR(16) NOT NULL PRIMARY KEY,' .
                'slug VARCHAR(64) NOT NULL,' .
                'name VARCHAR(120) NOT NULL,' .
                'title VARCHAR(160) NOT NULL,' .
                'description TEXT NOT NULL,' .
                'avatar VARCHAR(2048) NOT NULL,' .
                'status VARCHAR(16) NOT NULL DEFAULT \'draft\',' .
                'theme JSON NOT NULL,' .
                'cards JSON NOT NULL,' .
                'socials JSON NOT NULL,' .
                'created_at DATETIME NOT NULL,' .
                'updated_at DATETIME NOT NULL,' .
                'UNIQUE KEY uq_pages_slug (slug),' .
                'KEY idx_pages_updated_at (updated_at),' .
                'KEY idx_pages_status (status)' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS users (' .
                'open_id VARCHAR(191) NOT NULL PRIMARY KEY,' .
                'email VARCHAR(320) NULL,' .
                'name VARCHAR(120) NOT NULL DEFAULT \'\'' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS media (' .
                'id CHAR(16) NOT NULL PRIMARY KEY,' .
                'mime VARCHAR(100) NOT NULL,' .
                'content LONGBLOB NOT NULL,' .
                'created_at DATETIME NOT NULL,' .
                'KEY idx_media_created_at (created_at)' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS visits (' .
                'page_id CHAR(16) NOT NULL,' .
                'card_id VARCHAR(64) NOT NULL DEFAULT \'\',' .
                'occurred_at DATETIME NOT NULL,' .
                'KEY idx_visits_page (page_id),' .
                'KEY idx_visits_occurred_at (occurred_at)' .
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS pages (' .
                'id TEXT NOT NULL PRIMARY KEY,' .
                'slug TEXT NOT NULL UNIQUE,' .
                'name TEXT NOT NULL,' .
                'title TEXT NOT NULL,' .
                'description TEXT NOT NULL,' .
                'avatar TEXT NOT NULL,' .
                'status TEXT NOT NULL DEFAULT \'draft\',' .
                'theme TEXT NOT NULL,' .
                'cards TEXT NOT NULL,' .
                'socials TEXT NOT NULL,' .
                'created_at TEXT NOT NULL,' .
                'updated_at TEXT NOT NULL' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_updated_at ON pages (updated_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_status ON pages (status)');
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS users (' .
                'open_id TEXT NOT NULL PRIMARY KEY,' .
                'email TEXT NULL,' .
                'name TEXT NOT NULL DEFAULT \'\'' .
                ')'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS media (' .
                'id TEXT NOT NULL PRIMARY KEY,' .
                'mime TEXT NOT NULL,' .
                'content BLOB NOT NULL,' .
                'created_at TEXT NOT NULL' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_media_created_at ON media (created_at)');
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS visits (' .
                'page_id TEXT NOT NULL,' .
                'card_id TEXT NOT NULL DEFAULT \'\',' .
                'occurred_at TEXT NOT NULL' .
                ')'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_visits_page ON visits (page_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_visits_occurred_at ON visits (occurred_at)');
        }

        // These additive checks make a partially prepared local database recoverable without dropping data.
        self::ensureColumns($pdo, $driver);
    }

    private static function ensureColumns(PDO $pdo, string $driver): void
    {
        $definitions = [
            'pages' => [
                'id' => $driver === 'mysql' ? 'CHAR(16) NULL' : 'TEXT NULL',
                'slug' => $driver === 'mysql' ? 'VARCHAR(64) NULL' : 'TEXT NULL',
                'name' => $driver === 'mysql' ? 'VARCHAR(120) NULL' : 'TEXT NULL',
                'title' => $driver === 'mysql' ? 'VARCHAR(160) NULL' : 'TEXT NULL',
                'description' => 'TEXT NULL',
                'avatar' => $driver === 'mysql' ? 'VARCHAR(2048) NULL' : 'TEXT NULL',
                'status' => $driver === 'mysql' ? 'VARCHAR(16) NULL' : 'TEXT NULL',
                'theme' => $driver === 'mysql' ? 'JSON NULL' : 'TEXT NULL',
                'cards' => $driver === 'mysql' ? 'JSON NULL' : 'TEXT NULL',
                'socials' => $driver === 'mysql' ? 'JSON NULL' : 'TEXT NULL',
                'created_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
                'updated_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
            ],
            'users' => [
                'open_id' => $driver === 'mysql' ? 'VARCHAR(191) NULL' : 'TEXT NULL',
                'email' => $driver === 'mysql' ? 'VARCHAR(320) NULL' : 'TEXT NULL',
                'name' => $driver === 'mysql' ? 'VARCHAR(120) NULL' : 'TEXT NULL',
            ],
            'media' => [
                'id' => $driver === 'mysql' ? 'CHAR(16) NULL' : 'TEXT NULL',
                'mime' => $driver === 'mysql' ? 'VARCHAR(100) NULL' : 'TEXT NULL',
                'content' => $driver === 'mysql' ? 'LONGBLOB NULL' : 'BLOB NULL',
                'created_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
            ],
            'visits' => [
                'page_id' => $driver === 'mysql' ? 'CHAR(16) NULL' : 'TEXT NULL',
                'card_id' => $driver === 'mysql' ? 'VARCHAR(64) NULL' : 'TEXT NULL',
                'occurred_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
            ],
        ];

        foreach ($definitions as $table => $columns) {
            $existing = self::tableColumns($pdo, $driver, $table);
            foreach ($columns as $column => $definition) {
                if (isset($existing[$column])) {
                    continue;
                }
                $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
            }
        }
    }

    /** @return array<string,bool> */
    private static function tableColumns(PDO $pdo, string $driver, string $table): array
    {
        $columns = [];
        if ($driver === 'mysql') {
            $query = $pdo->query('SHOW COLUMNS FROM ' . $table);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($row['Field'])) {
                    $columns[(string) $row['Field']] = true;
                }
            }
            return $columns;
        }

        $query = $pdo->query('PRAGMA table_info(' . $table . ')');
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($row['name'])) {
                $columns[(string) $row['name']] = true;
            }
        }
        return $columns;
    }

    private static function seedPages(PDO $pdo, string $driver): void
    {
        $theme = json_encode(self::DEFAULT_THEME, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $now = gmdate('Y-m-d H:i:s');
        $select = $pdo->prepare('SELECT id FROM pages WHERE slug = :slug LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO pages ' .
            '(id, slug, name, title, description, avatar, status, theme, cards, socials, created_at, updated_at) ' .
            'VALUES (:id, :slug, :name, :title, :description, :avatar, :status, :theme, :cards, :socials, :created_at, :updated_at)'
        );

        for ($number = 1; $number <= 36; $number++) {
            $slug = $number === 1 ? 'inclusao' : sprintf('pagina-%02d', $number);
            $name = $number === 1 ? 'Inclusão' : sprintf('Página %02d', $number);
            $description = $number === 1 ? 'Uma comunidade onde todos têm lugar.' : '';
            $select->execute(['slug' => $slug]);
            if ($select->fetchColumn() !== false) {
                continue;
            }

            $insert->execute([
                'id' => bin2hex(random_bytes(8)),
                'slug' => $slug,
                'name' => $name,
                'title' => $name,
                'description' => $description,
                'avatar' => '',
                'status' => 'draft',
                'theme' => $theme,
                'cards' => '[]',
                'socials' => '[]',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
