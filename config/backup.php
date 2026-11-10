<?php

// Резервные копии базы магазина (php artisan db:backup, routes/console.php).
return [
    // Папка с дампами *.sql.gz. На сервере её стоит ещё и копировать за
    // пределы машины: копия на том же диске не спасёт от его поломки.
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    // Сколько дней хранить копии; более старые удаляются после новой.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // В production копия должна уходить за пределы сервера.
    'remote_disk' => env('BACKUP_REMOTE_DISK', env('APP_ENV', 'production') === 'production' ? 's3' : null),
];
