<?php

declare(strict_types=1);

define('DB_HOST', getenv('FOOD_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('FOOD_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('FOOD_DB_PASSWORD') ?: '');
define('DB_NAME', getenv('FOOD_DB_NAME') ?: 'food_website');

/**
 * Return a mysqli connection to the configured database.
 *
 * @return mysqli
 * @throws RuntimeException When the connection cannot be established.
 */
function get_db_connection(): mysqli
{
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($connection->connect_error) {
        throw new RuntimeException('Database connection failed: ' . $connection->connect_error);
    }

    if (! $connection->set_charset('utf8mb4')) {
        throw new RuntimeException('Unable to set database charset: ' . $connection->error);
    }

    return $connection;
}
