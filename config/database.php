<?php
/**
 * YingLume Finance — Database Configuration
 *
 * Returns the PDO connection configuration array.
 * Values are read from constants defined in config.php (which loads .env).
 *
 * @package YingLume\Config
 */

declare(strict_types=1);

return [
    'driver'   => 'mysql',
    'host'     => defined('DB_HOST') ? DB_HOST : 'localhost',
    'port'     => defined('DB_PORT') ? DB_PORT : 3306,
    'database' => defined('DB_NAME') ? DB_NAME : 'yinglume_finance',
    'username' => defined('DB_USER') ? DB_USER : 'root',
    'password' => defined('DB_PASS') ? DB_PASS : '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ],
];
