<?php

namespace App\Services\Support;

use App\Mail\GenericNotificationMail;
use App\Services\SystemSettingService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    protected SystemSettingService $systemSettingService;

    public function __construct(?SystemSettingService $systemSettingService = null)
    {
        $this->systemSettingService = $systemSettingService ?? app(SystemSettingService::class);
    }

    /**
     * التحقق من المفتاح السيادي لتفعيل أو تعطيل البريد الإلكتروني.
     */
    public function isEmailEnabled(): bool
    {
        return $this->systemSettingService->getBool('email_enabled', true);
    }

    /**
     * إرسال بريد إلكتروني باستخدام قالب notification.blade.php
     */
    public function sendEmail(
        string|array $recipients,
        string $subject,
        string $body,
        ?string $userName = null,
        ?string $ctaLink = null,
        array $extraData = []
    ): bool {
        if (! $this->isEmailEnabled()) {
            Log::info('EmailNotificationService: Sending skipped because email_enabled is false in system settings.');

            return false;
        }

        if (app()->runningUnitTests()) {
            Log::info('EmailNotification [Mocked]: Email sent to recipient(s).', [
                'subject' => $subject,
                'recipients' => $recipients,
            ]);

            return true;
        }

        $this->configureDynamicMailer();

        $toList = is_array($recipients) ? $recipients : [$recipients];
        $validEmails = array_filter(array_map('trim', $toList), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if (empty($validEmails)) {
            Log::warning('EmailNotificationService: No valid recipient email addresses provided.');

            return false;
        }

        $appName = $this->systemSettingService->getString('app_name', config('app.name', 'NetSales | مبيعات الشبكة'));
        $companyName = $this->systemSettingService->getString('app_company_name', 'Smartix IT Solutions');

        $emailData = array_merge([
            'MessageTitle' => $subject,
            'MessageBody' => $body,
            'UserName' => $userName ?? 'عميلنا العزيز',
            'CTA_Link' => $ctaLink,
            'CompanyName' => $companyName,
            'AppName' => $appName,
        ], $extraData);

        try {
            Mail::to($validEmails)->send(new GenericNotificationMail($emailData));
            Log::info('EmailNotificationService: Email dispatched successfully.', [
                'subject' => $subject,
                'recipients_count' => count($validEmails),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('EmailNotificationService Exception: '.$e->getMessage(), [
                'subject' => $subject,
            ]);

            return false;
        }
    }

    /**
     * ضبط إعدادات خادم البريد SMTP ديناميكياً من كائن SystemSettingService مع السقوط على .env
     */
    protected function configureDynamicMailer(): void
    {
        $host = $this->systemSettingService->getString('mail_host');
        $port = $this->systemSettingService->getInt('mail_port');
        $username = $this->systemSettingService->getString('mail_username');
        $password = $this->systemSettingService->getString('mail_password');
        $encryption = $this->systemSettingService->getString('mail_encryption');
        $fromAddress = $this->systemSettingService->getString('mail_from_address');
        $fromName = $this->systemSettingService->getString('mail_from_name');

        $hasCustomSmtp = false;

        if (! empty($host)) {
            config(['mail.mailers.smtp.host' => $host]);
            $hasCustomSmtp = true;
        }
        if (! empty($port)) {
            config(['mail.mailers.smtp.port' => $port]);
            $hasCustomSmtp = true;
        }
        if (! empty($username)) {
            config(['mail.mailers.smtp.username' => $username]);
            $hasCustomSmtp = true;
        }
        if (! empty($password)) {
            config(['mail.mailers.smtp.password' => $password]);
            $hasCustomSmtp = true;
        }
        if (! empty($encryption)) {
            config(['mail.mailers.smtp.encryption' => $encryption]);
            $hasCustomSmtp = true;
        }
        if (! empty($fromAddress)) {
            config(['mail.from.address' => $fromAddress]);
        }
        if (! empty($fromName)) {
            config(['mail.from.name' => $fromName]);
        }

        if ($hasCustomSmtp) {
            Mail::purge('smtp');
        }
    }
}
