<?php

return [
    'disk' => env('DB_BACKUP_DISK', 'local'),
    'path' => env('DB_BACKUP_PATH', 'backups/database'),
    'schedule_time' => env('DB_BACKUP_TIME', '00:00'),
    'connection' => env('DB_BACKUP_CONNECTION', env('DB_CONNECTION', 'sqlite')),
    'host' => env('DB_BACKUP_HOST'),
    'port' => env('DB_BACKUP_PORT'),
    'database' => env('DB_BACKUP_DATABASE'),
    'username' => env('DB_BACKUP_USERNAME'),
    'password' => env('DB_BACKUP_PASSWORD'),
    'mysqldump_binary' => env('DB_BACKUP_MYSQLDUMP_BINARY'),
    'mariadb_dump_binary' => env('DB_BACKUP_MARIADB_DUMP_BINARY'),
    'pg_dump_binary' => env('DB_BACKUP_PG_DUMP_BINARY'),
];
