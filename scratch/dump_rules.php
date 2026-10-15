<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$requestsDir = __DIR__ . '/../app/Http/Requests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($requestsDir));
$allRequests = [];
$allFields = [];

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
                        $rules = $inst->rules();
                        $allRequests[$className] = $rules;
                        foreach ($rules as $field => $r) {
                            $allFields[$field][] = $className;
                        }
                    } catch (Throwable $e) {}
                }
            }
        }
    }
}

file_put_contents(__DIR__ . '/all_request_rules.json', json_encode($allRequests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__ . '/all_request_fields.json', json_encode($allFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Saved all_request_rules.json and all_request_fields.json. Total fields: " . count($allFields) . "\n";
