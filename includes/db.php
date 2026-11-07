<?php
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = $GLOBALS['config'];
        $pdo = new PDO("mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+05:00'");
    }
    return $pdo;
}
function query(string $sql, array $args = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}
function one(string $sql, array $args = []): ?array {
    return query($sql, $args)->fetch() ?: null;
}
