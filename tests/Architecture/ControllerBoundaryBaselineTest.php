<?php

namespace Tests\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ControllerBoundaryBaselineTest extends TestCase
{
    /**
     * Existing generic Request input extraction is tracked by path and occurrence
     * count. Replacing it with FormRequests reduces the count; adding more fails CI.
     *
     * @return array<string, int>
     */
    private function requestInputBaseline(): array
    {
        return [
            'BackupController.php' => 1,
            'BankAccountController.php' => 3,
            'Billing/BillingCatalogController.php' => 10,
            'Billing/BillingFinanceController.php' => 16,
            'Billing/BillingPolicyController.php' => 8,
            'Billing/BillingReportController.php' => 1,
            'Billing/BillingSelfServiceController.php' => 5,
            'DebtRepaymentController.php' => 2,
            'DirectorateController.php' => 2,
            'ExpenseController.php' => 2,
            'GovernorateController.php' => 2,
            'NetworkController.php' => 1,
            'NetworkMembershipController.php' => 3,
            'NetworkWalletController.php' => 3,
            'NotificationController.php' => 3,
            'OperationalReportController.php' => 2,
            'PosController.php' => 1,
            'PosMembershipController.php' => 3,
            'PosWalletController.php' => 3,
            'RequestController.php' => 2,
            'SaleInvoiceController.php' => 3,
            'SupportTicketController.php' => 2,
            'SystemLogController.php' => 3,
            'SystemSettingController.php' => 2,
            'UserController.php' => 1,
            'VoucherPackageController.php' => 2,
            'VoucherPackageDiscountController.php' => 2,
        ];
    }

    /**
     * Existing direct Eloquent reads/writes are limited to these known controllers.
     * Their counts must only decrease as each legacy responsibility is extracted.
     *
     * @return array<string, int>
     */
    private function directModelAccessBaseline(): array
    {
        return [
            'Billing/BillingTrialEligibilityController.php' => 4,
            'NetworkPosController.php' => 4,
            'OperationalReportController.php' => 10,
            'PosController.php' => 1,
            'VoucherPackageController.php' => 5,
            'VoucherPackageDiscountController.php' => 5,
        ];
    }

    public function test_controllers_do_not_introduce_new_generic_request_input_boundaries(): void
    {
        $baseline = $this->requestInputBaseline();
        $violations = [];

        foreach ($this->controllerFiles() as $relativePath => $source) {
            $count = preg_match_all(
                '/\$request\s*->\s*(?:validate|only|all)\s*\(/',
                $this->withoutPhpComments($source)
            ) ?: 0;
            $allowedCount = $baseline[$relativePath] ?? 0;

            if ($count > $allowedCount) {
                $violations[] = sprintf(
                    '%s: %d generic Request input calls; baseline is %d',
                    $relativePath,
                    $count,
                    $allowedCount
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Controllers must accept validated FormRequests rather than adding generic Request input handling.\n".
            implode("\n", $violations)
        );
    }

    public function test_controllers_do_not_introduce_new_direct_eloquent_data_access(): void
    {
        $baseline = $this->directModelAccessBaseline();
        $violations = [];

        foreach ($this->controllerFiles() as $relativePath => $source) {
            $code = $this->withoutPhpComments($source);
            $queryCalls = preg_match_all(
                '/\b[A-Z][A-Za-z0-9_]*\s*::\s*query\s*\(/',
                $code
            ) ?: 0;
            $directCalls = preg_match_all(
                '/\b[A-Z][A-Za-z0-9_]*\s*::\s*(?:where\w*|create|insert|update|delete|onlyTrashed|withTrashed|find|first|pluck|exists|count)\s*\(/',
                $code
            ) ?: 0;
            $databaseFacadeCalls = preg_match_all('/\bDB\s*::/', $code) ?: 0;
            $count = $queryCalls + $directCalls + $databaseFacadeCalls;
            $allowedCount = $baseline[$relativePath] ?? 0;

            if ($count > $allowedCount) {
                $violations[] = sprintf(
                    '%s: %d direct data-access calls; baseline is %d (query=%d, direct=%d, DB=%d)',
                    $relativePath,
                    $count,
                    $allowedCount,
                    $queryCalls,
                    $directCalls,
                    $databaseFacadeCalls
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Controllers must coordinate HTTP and delegate persistence/query work to Services and Repositories.\n".
            implode("\n", $violations)
        );
    }

    /**
     * @return array<string, string>
     */
    private function controllerFiles(): array
    {
        $root = base_path('app/Http/Controllers/API/V1');
        $files = [];

        foreach (File::allFiles($root) as $file) {
            if (! str_ends_with($file->getFilename(), 'Controller.php')) {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $files[$relativePath] = $file->getContents();
        }

        ksort($files);

        return $files;
    }

    private function withoutPhpComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    }
}
