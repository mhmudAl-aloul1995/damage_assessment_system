<?php

return [

    'connection' => env('DB_BACKUP_CONNECTION', env('DB_CONNECTION', 'mysql')),

    'path' => env('DB_BACKUP_PATH', 'backups/database'),

    'mysqldump_binary' => env('DB_BACKUP_MYSQLDUMP_BINARY'),

    'mariadb_dump_binary' => env('DB_BACKUP_MARIADB_DUMP_BINARY'),

    'pg_dump_binary' => env('DB_BACKUP_PG_DUMP_BINARY'),

    'host' => env('DB_HOST'),
    'port' => env('DB_PORT'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),

];