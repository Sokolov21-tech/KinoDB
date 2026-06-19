<?php
 
 
 

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('[DB] Connection failed: ' . $e->getMessage());
                 
                http_response_code(503);
                die('Сервис временно недоступен. Попробуйте позже.');
            }
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone()     {}
}

 
function db(): PDO { return Database::get(); }

 
 
 

function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
        $stmt->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value, $type);
    }
    $stmt->execute();
    return $stmt;
}

function dbFetch(string $sql, array $params = []): ?array {
    return dbQuery($sql, $params)->fetch() ?: null;
}

function dbFetchAll(string $sql, array $params = []): array {
    return dbQuery($sql, $params)->fetchAll();
}

function dbInsert(string $table, array $data): int {
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
    $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
    dbQuery("INSERT INTO `$table` ($cols) VALUES ($vals)", $data);
    return (int) db()->lastInsertId();
}

function dbUpdate(string $table, array $data, array $where): int {
    $set   = implode(', ', array_map(fn($k) => "`$k` = :set_$k", array_keys($data)));
    $cond  = implode(' AND ', array_map(fn($k) => "`$k` = :where_$k", array_keys($where)));
    $params = [];
    foreach ($data  as $k => $v) $params["set_$k"]   = $v;
    foreach ($where as $k => $v) $params["where_$k"] = $v;
    return dbQuery("UPDATE `$table` SET $set WHERE $cond", $params)->rowCount();
}

function dbDelete(string $table, array $where): int {
    $cond   = implode(' AND ', array_map(fn($k) => "`$k` = :$k", array_keys($where)));
    return dbQuery("DELETE FROM `$table` WHERE $cond", $where)->rowCount();
}

function dbExists(string $table, array $where): bool {
    $cond = implode(' AND ', array_map(fn($k) => "`$k` = :$k", array_keys($where)));
    $row  = dbFetch("SELECT 1 FROM `$table` WHERE $cond LIMIT 1", $where);
    return $row !== null;
}
