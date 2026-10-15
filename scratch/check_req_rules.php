<?php

$rules = json_decode(file_get_contents(__DIR__ . '/all_request_rules.json'), true);

$requestsToCheck = [
    'StoreUserRequest',
    'UpdateUserRequest',
    'LoginRequest',
    'RegisterRequest',
    'RegisterPosRequest',
    'UpdatePasswordRequest',
    'StoreSecurityBanRequest',
    'UpdateSecurityBanRequest',
    'StoreNetworkRequest',
    'UpdateNetworkRequest',
    'StorePosRequest',
    'UpdatePosRequest',
    'StoreNetworkPosRequest',
    'StoreNetworkWalletRequest',
    'StorePosWalletRequest',
    'StoreBankAccountRequest',
    'UpdateBankAccountRequest',
    'StoreVoucherPackageRequest',
    'UpdateVoucherPackageRequest',
    'StoreVoucherPackageDiscountRequest',
    'StoreRequestRequest',
    'StoreRequestItemRequest',
    'StoreSaleInvoiceRequest',
    'UpdateSaleInvoiceRequest',
    'StoreSaleInvoiceItemRequest',
    'StoreExpenseRequest',
    'StoreSubscriptionPlanRequest',
    'UpdateSubscriptionPlanRequest',
    'StoreSubscriptionPlanRequestRequest',
    'SendAdminNotificationRequest',
    'SendUserDirectNotificationRequest',
    'UpdateFcmTokenRequest',
    'BatchIdsRequest',
    'BatchStatusRequest',
    'RejectRequest',
    'BatchUpdateSystemSettingRequest',
    'DepositNetworkWalletRequest',
    'WithdrawNetworkWalletRequest',
    'DepositPosWalletRequest',
    'WithdrawPosWalletRequest',
    'TransferNetworkToPosWalletRequest',
    'TransferPosToNetworkWalletRequest',
];

foreach ($requestsToCheck as $req) {
    if (isset($rules[$req])) {
        echo "=== $req ===\n";
        foreach ($rules[$req] as $field => $r) {
            echo "  $field: " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
