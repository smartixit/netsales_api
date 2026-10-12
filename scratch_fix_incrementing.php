<?php

foreach (glob('app/Models/*.php') as $file) {
    $c = file_get_contents($file);
    if (strpos($c, 'protected $incrementing') !== false) {
        $c = str_replace('protected $incrementing', 'public $incrementing', $c);
        file_put_contents($file, $c);
        echo 'Updated: ' . basename($file) . "\n";
    }
}
