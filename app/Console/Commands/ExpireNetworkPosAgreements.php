<?php

namespace App\\Console\\Commands;

use App\\Services\\NetworkPosAgreementService;
use Illuminate\\Console\\Command;
use Throwable;

class ExpireNetworkPosAgreements extends Command
{
    protected $signature = 'network-pos:expire-agreements {--limit=100 : Maximum relationship records to process per run}';

    protected $description = 'تحويل الاتفاقيات المنتهية إلى حالة EXPIRED وتسجيل أحداث انتهاء السريان';

    public function handle(NetworkPosAgreementService $agreements): int
    {
        try {
            $limit = min(max((int) $this->option('limit'), 1), 500);
            $expired = $agreements->expireDueAgreements($limit);
            $this->info("تم إنهاء صلاحية {$expired} اتفاقية.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('تعذرت معالجة الاتفاقيات المنتهية: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
