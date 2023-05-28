<?php

// Подключаемся к базе
function getDB(): \PDO
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    $dbConnStr = getenv('APP_DATABASE');
    if (!$dbConnStr) {
        echo 'DB connection settings not provided in APP_DATABASE environment variable.';
        exit(1);
    }

    $db = new \PDO($dbConnStr);

    return $db;
}

function getCachedStmt(string $query): \PDOStatement
{
    static $cache = [];

    $cacheKey = getQueryCacheKey($query);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = getDB()->prepare($query);

    $cache[$cacheKey] = $stmt;

    return $stmt;
}

function getQueryCacheKey(string $query): string
{
    return md5($query);
}