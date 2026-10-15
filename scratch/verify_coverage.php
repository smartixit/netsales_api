<?php

require __DIR__ . '/test_new_attributes.php';

$fields = json_decode(file_get_contents(__DIR__ . '/all_request_fields.json'), true);
$missingRules = [];
foreach ($fields as $f => $reqs) {
    if (!isset($newAttributes[$f])) {
        $missingRules[$f] = $reqs;
    }
}

echo "Missing rule fields in newAttributes: " . count($missingRules) . "\n";
if (count($missingRules) > 0) {
    print_r($missingRules);
}

$tables = json_decode(file_get_contents(__DIR__ . '/table_columns.json'), true);
$missingTableCols = [];
foreach ($tables as $t => $cols) {
    foreach ($cols as $col => $type) {
        if (!in_array($col, ['id', 'timestamps', 'softDeletes', 'rememberToken', 'created_at', 'updated_at', 'deleted_at'])) {
            if (!isset($newAttributes[$col])) {
                $missingTableCols[$col][$t] = $type;
            }
        }
    }
}

echo "Missing table columns in newAttributes: " . count($missingTableCols) . "\n";
if (count($missingTableCols) > 0) {
    print_r(array_keys($missingTableCols));
}
