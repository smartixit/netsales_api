<?php
$fields = json_decode(file_get_contents(__DIR__ . '/all_request_fields.json'), true);
$lang = require __DIR__ . '/../lang/ar/validation.php';
$m = [];
foreach ($fields as $f => $reqs) {
    if (!isset($lang['attributes'][$f])) {
        $m[] = $f;
    }
}
echo implode(PHP_EOL, $m);
