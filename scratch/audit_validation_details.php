<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$lang = require __DIR__ . '/../lang/ar/validation.php';
$currentAttributes = $lang['attributes'] ?? [];
$currentCustom = $lang['custom'] ?? [];

// 1. Gather all migration columns
$migrationsDir = __DIR__ . '/../database/migrations';
$migrationColumns = [];
foreach (glob($migrationsDir . '/*.php') as $migFile) {
    $content = file_get_contents($migFile);
    if (preg_match_all('/\$table->\w+\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $col) {
            $migrationColumns[$col] = true;
        }
    }
}

// 2. Gather all FormRequests and their rules
$requestsDir = __DIR__ . '/../app/Http/Requests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($requestsDir));
$formRequests = [];
$allRuleFields = [];

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
                        $instance = new $fullClass();
                        $rules = $instance->rules();
                        $formRequests[$className] = [
                            'fullClass' => $fullClass,
                            'rules' => $rules,
                            'usesCustomTrait' => in_array('App\Http\Requests\Traits\LoadsCustomValidationMessages', $ref->getTraitNames()),
                        ];
                        foreach ($rules as $field => $r) {
                            $allRuleFields[$field] = true;
                            // Also base field if nested: e.g. items.*.package_id -> items
                            $parts = explode('.', $field);
                            foreach ($parts as $p) {
                                if ($p !== '*') {
                                    $allRuleFields[$p] = true;
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        $formRequests[$className] = [
                            'fullClass' => $fullClass,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }
        }
    }
}

// 3. Compare attributes
$missingFromAttributes = [];
foreach ($allRuleFields as $field => $_) {
    if (!isset($currentAttributes[$field])) {
        $missingFromAttributes[$field] = 'from_rules';
    }
}
foreach ($migrationColumns as $col => $_) {
    if (!isset($currentAttributes[$col])) {
        if (!isset($missingFromAttributes[$col])) {
            $missingFromAttributes[$col] = 'from_migrations';
        }
    }
}

// Check attributes that are never in rules or migrations
$orphanAttributes = [];
foreach ($currentAttributes as $attr => $label) {
    if (!isset($allRuleFields[$attr]) && !isset($migrationColumns[$attr])) {
        $orphanAttributes[$attr] = $label;
    }
}

// 4. Check custom array
$customOrphanClasses = [];
$customFieldMismatches = [];
foreach ($currentCustom as $reqClass => $fields) {
    if (!isset($formRequests[$reqClass])) {
        $customOrphanClasses[] = $reqClass;
    } else {
        $rules = $formRequests[$reqClass]['rules'] ?? [];
        foreach ($fields as $field => $rulesMsgs) {
            if (!isset($rules[$field])) {
                $customFieldMismatches[$reqClass][] = $field;
            }
        }
    }
}

$requestsWithoutCustom = [];
foreach ($formRequests as $reqClass => $info) {
    if (!isset($currentCustom[$reqClass])) {
        $requestsWithoutCustom[] = $reqClass;
    }
}

echo json_encode([
    'missingAttributesCount' => count($missingFromAttributes),
    'missingAttributes' => $missingFromAttributes,
    'orphanAttributesCount' => count($orphanAttributes),
    'orphanAttributes' => $orphanAttributes,
    'customOrphanClasses' => $customOrphanClasses,
    'customFieldMismatches' => $customFieldMismatches,
    'totalFormRequests' => count($formRequests),
    'requestsWithCustom' => count($currentCustom),
    'requestsWithoutCustom' => $requestsWithoutCustom,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
