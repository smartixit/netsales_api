<?php

$rules = json_decode(file_get_contents(__DIR__ . '/all_request_rules.json'), true);

$authReqs = ['LoginRequest', 'RegisterRequest', 'RegisterPosRequest', 'UpdatePasswordRequest', 'VerifyOtpRequest', 'GenerateOtpRequest', 'ToggleTwoFactorRequest'];
foreach ($authReqs as $r) {
    if (isset($rules[$r])) {
        echo "=== $r ===\n";
        foreach ($rules[$r] as $f => $rule) {
            echo "  $f: " . json_encode($rule, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
