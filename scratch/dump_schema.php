<?php

$migrationsDir = __DIR__ . '/../database/migrations';
$schema = [];

foreach (glob($migrationsDir . '/*.php') as $migFile) {
    $content = file_get_contents($migFile);
    if (preg_match('/Schema::create\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $mTable)) {
        $tableName = $mTable[1];
        preg_match_all('/\$table->([a-zA-Z0-9_]+)\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $mCols);
        $cols = [];
        for ($i = 0; $i < count($mCols[0]); $i++) {
            $type = $mCols[1][$i];
            $name = $mCols[2][$i];
            if (!in_array($type, ['index', 'unique', 'foreign', 'dropIndex', 'dropUnique', 'dropForeign'])) {
                $cols[$name] = $type;
            }
        }
        $schema[$tableName] = $cols;
    }
}

file_put_contents(__DIR__ . '/table_columns.json', json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Saved table_columns.json with " . count($schema) . " tables.\n";
