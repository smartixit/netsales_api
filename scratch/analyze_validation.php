<?php

$lang = require __DIR__ . '/../lang/ar/validation.php';

echo "Standard rules count: " . count(array_filter($lang, fn($k) => !in_array($k, ['attributes', 'custom', 'values']), ARRAY_FILTER_USE_KEY)) . PHP_EOL;
echo "Attributes count: " . count($lang['attributes'] ?? []) . PHP_EOL;
echo "Custom count: " . count($lang['custom'] ?? []) . PHP_EOL;
echo "Values count: " . count($lang['values'] ?? []) . PHP_EOL;

// Check for any is_credit_sale
if (isset($lang['attributes']['is_credit_sale'])) {
    echo "Found is_credit_sale in attributes!\n";
}

// Let's inspect all FormRequests and extract all rule keys
$requestsDir = __DIR__ . '/../app/Http/Requests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($requestsDir));
$requestFields = [];

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        // find class name
        if (preg_match('/namespace\s+([^;]+);/', $content, $m1) && preg_match('/class\s+(\w+)\s+extends\s+FormRequest/', $content, $m2)) {
            $class = $m1[1] . '\\' . $m2[1];
            if (class_exists($class)) {
                try {
                    $ref = new ReflectionClass($class);
                    if (!$ref->isAbstract() && $ref->hasMethod('rules')) {
                        $reqInstance = new $class();
                        $rules = $reqInstance->rules();
                        foreach ($rules as $k => $v) {
                            $baseKey = explode('.', $k)[0];
                            $requestFields[$k] = true;
                            $requestFields[$baseKey] = true;
                        }
                    }
                } catch (Throwable $e) {
                    // skip
                }
            }
        }
    }
}

echo "Total unique field keys in FormRequests: " . count($requestFields) . PHP_EOL;

// Check which request fields are missing in $lang['attributes']
$missingAttributes = [];
foreach (array_keys($requestFields) as $field) {
    if (!isset($lang['attributes'][$field])) {
        $missingAttributes[$field] = true;
    }
}
echo "Missing fields in attributes count: " . count($missingAttributes) . PHP_EOL;
echo "Missing fields: " . implode(', ', array_keys($missingAttributes)) . PHP_EOL;
