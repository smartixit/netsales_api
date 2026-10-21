<?php

namespace App\Console\Commands;

use App\Interfaces\OtpRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر تنظيف رموز التحقق المؤقتة (OTP) المنتهية الصلاحية أو المستخدمة.
 */
class CleanupExpiredOtp extends Command
{
    protected $signature = 'cleanup:expired-otp 
                            {--hours=24 : Delete OTPs expired or used older than this number of hours}';

    protected $description = 'ينظف رموز التحقق المؤقتة (OTP) المنتهية الصلاحية أو المستعملة من قاعدة البيانات';

    public function handle(OtpRepositoryInterface $otpRepository): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = Carbon::now('Asia/Aden')->subHours($hours);

        $this->info("🚀 بدء تنظيف رموز OTP المنتهية أو المستعملة قبل {$cutoff->toDateTimeString()}...");

        $deletedCount = $otpRepository->deleteExpiredOrUsed($cutoff);

        $this->info("✅ تم تنظيف وحذف {$deletedCount} رمز OTP منتهي بنجاح.");

        return 0;
    }
}
