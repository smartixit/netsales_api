<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$modelsDir = __DIR__ . '/../app/Models';
$models = [];

foreach (glob($modelsDir . '/*.php') as $file) {
    $className = 'App\\Models\\' . basename($file, '.php');
    if (class_exists($className)) {
        $ref = new ReflectionClass($className);
        if (!$ref->isAbstract() && $ref->isSubclassOf(Illuminate\Database\Eloquent\Model::class)) {
            $methods = [];
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->class === $className) {
                    $methods[] = $m->name;
                }
            }
            $models[basename($file, '.php')] = [
                'class' => $className,
                'methods' => $methods,
            ];
        }
    }
}

$reposDir = __DIR__ . '/../app/Repositories';
$repos = [];
foreach (glob($reposDir . '/*.php') as $file) {
    $className = 'App\\Repositories\\' . basename($file, '.php');
    if (class_exists($className)) {
        $repos[] = basename($file, '.php');
    }
}

echo "=== MODELS AND THEIR DEFINED METHODS ===\n";
foreach ($models as $name => $info) {
    echo "[$name]: " . implode(', ', $info['methods']) . "\n";
}

echo "\nTotal Repositories: " . count($repos) . "\n";
