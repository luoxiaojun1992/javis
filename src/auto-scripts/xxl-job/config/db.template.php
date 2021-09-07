<?php

return [
    'dsn' => 'mysql:dbname=xxl_job;host=127.0.0.1;charset=utf8mb4',
    'username' => 'root',
    'passwd' => '123456',
    'options' => [
        \PDO::ATTR_CASE => \PDO::CASE_NATURAL,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
