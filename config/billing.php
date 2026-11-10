<?php

return [
    'grace_period_days' => (int) env('BILLING_GRACE_PERIOD_DAYS', 7),
    'dunning_days' => [0, 1, 3, 7],
    'invoice_payment_terms_days' => (int) env('BILLING_DEFAULT_PAYMENT_TERMS_DAYS', 0),
    'renewal_batch_size' => 100,
    'dunning_batch_size' => 100,
    'revenue_recognition_batch_size' => 100,
];
