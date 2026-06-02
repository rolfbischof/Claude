<?php
/**
 * mb Kommunikation + Events
 * Database class – PDO singleton wrapper with full CRUD helpers.
 *
 * Usage:
 *   $db  = Database::getInstance();
 *   $row = $db->fetchOne('SELECT * FROM users WHERE id = ?', [1]);
 *   $id  = $db->insert('events_portfolio', ['title' => 'My Event', ...]);
 */

declare(strict_types=1);

if (!defined('DB_HOST')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $queryCount = 0;

    // ----------------------------------------------------------------
    // Singleton constructor – private to enforce single connection
    // ----------------------------------------------------------------
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[DB] Connection failed: ' . $e->getMessage());
            throw new RuntimeException(
                'Datenbankverbindung fehlgeschlagen. Bitte versuchen Sie es später erneut.'
            );
        }
    }

    /** Prevent cloning of singleton */
    private function __clone() {}

    // ----------------------------------------------------------------
    // Get singleton instance
    // ----------------------------------------------------------------
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ----------------------------------------------------------------
    // Raw PDO access (for advanced queries not covered by helpers)
    // ----------------------------------------------------------------
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // ----------------------------------------------------------------
    // Execute a prepared statement and return the PDOStatement
    // ----------------------------------------------------------------
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->queryCount++;
            return $stmt;
        } catch (PDOException $e) {
            error_log('[DB] Query error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new RuntimeException('Datenbankfehler: ' . $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // Fetch a single row as associative array (or null if not found)
    // ----------------------------------------------------------------
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    // ----------------------------------------------------------------
    // Fetch all rows as array of associative arrays
    // ----------------------------------------------------------------
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    // ----------------------------------------------------------------
    // Fetch a single column value (first column of first row)
    // ----------------------------------------------------------------
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $result = $this->query($sql, $params)->fetchColumn();
        return $result === false ? null : $result;
    }

    // ----------------------------------------------------------------
    // INSERT – returns the new auto-increment ID
    // ----------------------------------------------------------------
    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('insert() requires at least one column.');
        }

        $columns      = array_keys($data);
        $placeholders = array_map(fn(string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->sanitizeIdentifier($table),
            implode(', ', array_map(fn(string $c): string => '`' . $c . '`', $columns)),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    // ----------------------------------------------------------------
    // UPDATE – returns number of affected rows
    // ----------------------------------------------------------------
    public function update(string $table, array $data, array $where): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('update() requires at least one column.');
        }
        if (empty($where)) {
            throw new InvalidArgumentException('update() requires a WHERE clause.');
        }

        $setParts   = [];
        $whereParts = [];
        $params     = [];

        foreach ($data as $col => $val) {
            $key          = 'set_' . $col;
            $setParts[]   = '`' . $col . '` = :' . $key;
            $params[$key] = $val;
        }

        foreach ($where as $col => $val) {
            $key           = 'where_' . $col;
            $whereParts[]  = '`' . $col . '` = :' . $key;
            $params[$key]  = $val;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->sanitizeIdentifier($table),
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        return $this->query($sql, $params)->rowCount();
    }

    // ----------------------------------------------------------------
    // DELETE – returns number of affected rows
    // ----------------------------------------------------------------
    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('delete() requires a WHERE clause.');
        }

        $whereParts = [];
        $params     = [];

        foreach ($where as $col => $val) {
            $key          = 'where_' . $col;
            $whereParts[] = '`' . $col . '` = :' . $key;
            $params[$key] = $val;
        }

        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->sanitizeIdentifier($table),
            implode(' AND ', $whereParts)
        );

        return $this->query($sql, $params)->rowCount();
    }

    // ----------------------------------------------------------------
    // COUNT rows with optional simple equality WHERE conditions
    // ----------------------------------------------------------------
    public function count(string $table, array $where = []): int
    {
        $params      = [];
        $whereClause = '';

        if (!empty($where)) {
            $parts = [];
            foreach ($where as $col => $val) {
                $key           = 'c_' . $col;
                $parts[]       = '`' . $col . '` = :' . $key;
                $params[$key]  = $val;
            }
            $whereClause = ' WHERE ' . implode(' AND ', $parts);
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s`%s',
            $this->sanitizeIdentifier($table),
            $whereClause
        );

        return (int) $this->fetchColumn($sql, $params);
    }

    // ----------------------------------------------------------------
    // FIND – single row by primary key or arbitrary column
    // ----------------------------------------------------------------
    public function find(string $table, int|string $id, string $column = 'id'): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `%s` = ? LIMIT 1',
            $this->sanitizeIdentifier($table),
            $this->sanitizeIdentifier($column)
        );
        return $this->fetchOne($sql, [$id]);
    }

    // ----------------------------------------------------------------
    // FIND ALL – rows with optional equality conditions, ordering, limit
    // ----------------------------------------------------------------
    public function findAll(
        string $table,
        array  $where   = [],
        string $orderBy = '',
        int    $limit   = 0,
        int    $offset  = 0
    ): array {
        $params      = [];
        $whereClause = '';

        if (!empty($where)) {
            $parts = [];
            foreach ($where as $col => $val) {
                $key          = 'w_' . $col;
                $parts[]      = '`' . $col . '` = :' . $key;
                $params[$key] = $val;
            }
            $whereClause = ' WHERE ' . implode(' AND ', $parts);
        }

        $orderClause  = $orderBy ? ' ORDER BY ' . $orderBy : '';
        $limitClause  = $limit   ? ' LIMIT ' . $limit      : '';
        $offsetClause = ($limit && $offset) ? ' OFFSET ' . $offset : '';

        $sql = sprintf(
            'SELECT * FROM `%s`%s%s%s%s',
            $this->sanitizeIdentifier($table),
            $whereClause,
            $orderClause,
            $limitClause,
            $offsetClause
        );

        return $this->fetchAll($sql, $params);
    }

    // ----------------------------------------------------------------
    // EXISTS – check if any row matches given conditions
    // ----------------------------------------------------------------
    public function exists(string $table, array $where): bool
    {
        if (empty($where)) {
            return false;
        }

        $parts  = [];
        $params = [];

        foreach ($where as $col => $val) {
            $key          = 'e_' . $col;
            $parts[]      = '`' . $col . '` = :' . $key;
            $params[$key] = $val;
        }

        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE %s LIMIT 1',
            $this->sanitizeIdentifier($table),
            implode(' AND ', $parts)
        );

        return $this->fetchColumn($sql, $params) !== null;
    }

    // ----------------------------------------------------------------
    // UPSERT (INSERT … ON DUPLICATE KEY UPDATE)
    // $insertData  = columns to insert
    // $updateData  = columns to update on duplicate (if empty, updates all insert columns)
    // ----------------------------------------------------------------
    public function upsert(string $table, array $insertData, array $updateData = []): int
    {
        if (empty($insertData)) {
            throw new InvalidArgumentException('upsert() requires at least one insert column.');
        }

        if (empty($updateData)) {
            $updateData = $insertData;
        }

        $insertCols   = array_keys($insertData);
        $insertPhs    = array_map(fn(string $c): string => ':i_' . $c, $insertCols);
        $updateParts  = [];
        $params       = [];

        foreach ($insertData as $col => $val) {
            $params['i_' . $col] = $val;
        }

        foreach ($updateData as $col => $val) {
            $updateParts[]       = '`' . $col . '` = :u_' . $col;
            $params['u_' . $col] = $val;
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $this->sanitizeIdentifier($table),
            implode(', ', array_map(fn(string $c): string => '`' . $c . '`', $insertCols)),
            implode(', ', $insertPhs),
            implode(', ', $updateParts)
        );

        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    // ----------------------------------------------------------------
    // TRANSACTION helpers
    // ----------------------------------------------------------------
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Run a callable inside a transaction.
     * Commits on success, rolls back on any exception.
     *
     * @param  callable(Database): mixed $callback
     * @return mixed  Whatever the callback returns
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // PAGINATE – execute a SELECT and return paginated result set
    //
    // Returns:
    //   ['data' => [...rows...], 'total' => int, 'pages' => int,
    //    'current_page' => int, 'per_page' => int]
    // ----------------------------------------------------------------
    public function paginate(
        string $sql,
        array  $params  = [],
        int    $page    = 1,
        int    $perPage = ITEMS_PER_PAGE
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        // Wrap query to count total rows without LIMIT
        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _paginate_sub';
        $total    = (int) $this->fetchColumn($countSql, $params);

        $pages  = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        $data = $this->fetchAll(
            $sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'data'         => $data,
            'total'        => $total,
            'pages'        => $pages,
            'current_page' => $page,
            'per_page'     => $perPage,
        ];
    }

    // ----------------------------------------------------------------
    // SETTINGS helpers (thin wrappers over the `settings` table)
    // ----------------------------------------------------------------

    /** Get a single setting value, with optional default */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $row = $this->fetchOne(
            'SELECT `value` FROM `settings` WHERE `key` = ?',
            [$key]
        );
        return $row !== null ? $row['value'] : $default;
    }

    /** Set (insert or update) a setting */
    public function setSetting(string $key, string $value): void
    {
        if ($this->exists('settings', ['key' => $key])) {
            $this->update('settings', ['value' => $value], ['key' => $key]);
        } else {
            $this->insert('settings', ['key' => $key, 'value' => $value]);
        }
    }

    /** Get all settings as an associative array keyed by setting key */
    public function getAllSettings(): array
    {
        $rows   = $this->fetchAll('SELECT * FROM `settings` ORDER BY `key`');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row;
        }
        return $result;
    }

    // ----------------------------------------------------------------
    // Diagnostics
    // ----------------------------------------------------------------

    /** Number of queries executed in this request */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    // ----------------------------------------------------------------
    // Internal helpers
    // ----------------------------------------------------------------

    /**
     * Sanitize a table or column identifier.
     * Only allows alphanumeric characters, underscores, and hyphens.
     */
    private function sanitizeIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            throw new InvalidArgumentException('Invalid DB identifier: ' . $name);
        }
        return $name;
    }
}
