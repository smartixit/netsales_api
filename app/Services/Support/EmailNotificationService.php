<?php

namespace App\Services\Support;

use App\Mail\GenericNotificationMail;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
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
        if (app()->runningUnitTests()) {
            Log::info('EmailNotification [Mocked]: Email sent to recipient(s).', [
                'subject' => $subject,
                'recipients' => $recipients,
            ]);

            return true;
        }

        $toList = is_array($recipients) ? $recipients : [$recipients];
        $validEmails = array_filter(array_map('trim', $toList), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if (empty($validEmails)) {
            Log::warning('EmailNotificationService: No valid recipient email addresses provided.');

            return false;
        }

        $emailData = array_merge([
            'MessageTitle' => $subject,
            'MessageBody' => $body,
            'UserName' => $userName ?? 'عميلنا العزيز',
            'CTA_Link' => $ctaLink,
            'CompanyName' => config('app.name', 'نظام مبيعات الشبكة'),
            'AppName' => 'مبيعات الشبكة | NetSales',
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
}
