<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lang = require __DIR__ . '/../lang/ar/validation.php';
$custom = $lang['custom'] ?? [];

$requestsDir = __DIR__ . '/../app/Http/Requests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($requestsDir));
$requestClasses = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (preg_match('/namespace\s+([^;]+);/', $content, $m1) && preg_match('/class\s+(\w+)\s+extends\s+FormRequest/', $content, $m2)) {
            $className = $m2[1];
            $fullClass = $m1[1] . '\\' . $className;
            if (class_exists($fullClass)) {
                $ref = new ReflectionClass($fullClass);
                if (!$ref->isAbstract() && $ref->hasMethod('rules')) {
                    try {
                        $inst = new $fullClass();
                        $requestClasses[$className] = [
                            'full' => $fullClass,
                            'rules' => array_keys($inst->rules()),
                            'rulesRaw' => $inst->rules(),
                        ];
                    } catch (Throwable $e) {}
                }
            }
        }
    }
}

echo "=== CHECKING EXISTING CUSTOM MESSAGES ===\n";
foreach ($custom as $reqName => $fields) {
    if (!isset($requestClasses[$reqName])) {
        echo "[-] Unknown FormRequest class in custom: $reqName\n";
        continue;
    }
    $actualRules = $requestClasses[$reqName]['rules'];
    echo "[+] $reqName:\n";
    foreach ($fields as $field => $messages) {
        if (!in_array($field, $actualRules)) {
            echo "    [MISMATCH FIELD] '$field' is not in rules: " . implode(', ', $actualRules) . "\n";
        }
    }
}
