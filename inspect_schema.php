<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (['users', 'network_users', 'pos_users', 'roles'] as $t) {
    echo "=== TABLE: $t ===\n";
    $row = (array) Illuminate\Support\Facades\DB::selectOne("SHOW CREATE TABLE $t");
    echo array_values($row)[1] . "\n\n";
}
