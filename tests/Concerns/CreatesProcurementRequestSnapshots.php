<?php

namespace Tests\Concerns;

use App\Models\VoucherPackage;
use App\Services\ProcurementPricingSnapshotService;
use App\Services\VoucherPackageDiscountService;
use App\Support\DecimalAmount;
use Illuminate\Testing\TestResponse;

/**
 * @property array<string, string> $headers
 */
trait CreatesProcurementRequestSnapshots
{
    /**
     * Build valid server-signed tariff snapshots exactly as the package catalog
     * supplies them to clients, without weakening the production validation.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string>|null $headers
     */
    protected function postProcurementRequest(array $payload, ?array $headers = null): TestResponse
    {
        $networkId = (int) ($payload['network_id'] ?? 0);
        $posId = (int) ($payload['pos_id'] ?? 0);
        $items = (array) ($payload['items'] ?? []);

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! empty($item['pricing_snapshot_token'])) {
                continue;
            }

            $package = VoucherPackage::query()->findOrFail((int) ($item['package_id'] ?? 0));
            $discount = app(VoucherPackageDiscountService::class)->getEffectiveDiscount(
                $networkId,
                $posId,
                (int) $package->package_id
            );

            $items[$index]['pricing_snapshot_token'] = app(ProcurementPricingSnapshotService::class)->issue(
                $networkId,
                $posId,
                (int) $package->package_id,
                DecimalAmount::toMinorUnits($package->package_voucher_price),
                DecimalAmount::toMinorUnits($discount['discount_cash'] ?? 0),
                DecimalAmount::toMinorUnits($discount['discount_credit'] ?? 0),
            );
        }

        $payload['items'] = array_values($items);

        return $this->withHeaders($headers ?? $this->headers)
            ->postJson('/api/v1/requests', $payload);
    }
}
