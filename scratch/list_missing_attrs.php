<?php

$lang = require __DIR__ . '/../lang/ar/validation.php';
$attributes = $lang['attributes'] ?? [];

$fields = json_decode(file_get_contents(__DIR__ . '/all_request_fields.json'), true);

$missing = [];
foreach ($fields as $field => $requests) {
    if (!isset($attributes[$field])) {
        $missing[$field] = $requests;
    }
}

echo "Total missing fields: " . count($missing) . "\n";
foreach ($missing as $f => $reqs) {
    echo sprintf("%-35s => [%s]\n", $f, implode(', ', array_slice($reqs, 0, 3)));
}
