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
];
