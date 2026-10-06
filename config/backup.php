<?php

// Резервные копии базы магазина (php artisan db:backup, routes/console.php).
return [
    // Папка с дампами *.sql.gz. На сервере её стоит ещё и копировать за
    // пределы машины: копия на том же диске не спасёт от его поломки.
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    // Сколько дней хранить копии; более старые удаляются после новой.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
];
