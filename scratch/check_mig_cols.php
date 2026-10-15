<?php

$lang = require __DIR__ . '/../lang/ar/validation.php';
$attributes = $lang['attributes'] ?? [];

$migrationsDir = __DIR__ . '/../database/migrations';
$migrationColumns = [];
foreach (glob($migrationsDir . '/*.php') as $migFile) {
    $content = file_get_contents($migFile);
    if (preg_match_all('/\$table->\w+\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $col) {
            if (!in_array($col, ['id', 'timestamps', 'softDeletes', 'rememberToken'])) {
                $migrationColumns[$col] = true;
            }
        }
    }
}

$missing = [];
foreach ($migrationColumns as $col => $_) {
    if (!isset($attributes[$col])) {
        $missing[] = $col;
    }
}

echo "Migration columns missing from attributes: " . count($missing) . "\n";
echo implode(PHP_EOL, $missing) . "\n";
