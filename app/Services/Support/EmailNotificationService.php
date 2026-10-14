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
            'CompanyName' => config('app.name', 'مبيعات الشبكة'),
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

    /**
     * إرسال إشعار استلام طلب تسجيل مركز وحساب جديد.
     */
    public function sendRegistrationReceived(mixed $center, mixed $user): bool
    {
        $centerName = is_object($center) ? ($center->center_name ?? '') : ($center['center_name'] ?? '');
        $email = is_object($user) ? ($user->user_email ?? '') : ($user['user_email'] ?? '');
        $phone = is_object($user) ? ($user->user_phone_number ?? '') : ($user['user_phone_number'] ?? '');

        if (empty($email)) {
            return false;
        }

        $subject = 'استلام طلب تسجيل مركز خدمة جديد';
        $body = "مرحباً بك،\nتم استلام طلب تسجيل مركز الخدمة ({$centerName}) بنجاح.\nطلبك حالياً قيد المراجعة والتدقيق من قِبل إدارة النظام وسنوافيك بإشعار فور اعتماد الحساب وتفعيله.\nشكراً لانضمامك لمنصة مبيعات الشبكة.";

        return $this->sendEmail($email, $subject, $body, $phone);
    }

    /**
     * إرسال إشعار اعتماد وتفعيل مركز خدمة.
     */
    public function sendCenterApproved(mixed $center, mixed $user): bool
    {
        $centerName = is_object($center) ? ($center->center_name ?? '') : ($center['center_name'] ?? '');
        $email = is_object($user) ? ($user->user_email ?? '') : ($user['user_email'] ?? '');
        $phone = is_object($user) ? ($user->user_phone_number ?? '') : ($user['user_phone_number'] ?? '');

        if (empty($email)) {
            return false;
        }

        $subject = 'اعتماد وتفعيل حساب مركز الخدمة';
        $body = "تهانينا!\nتمت مراجعة واعتماد حساب مركز الخدمة ({$centerName}) بنجاح.\nحسابك الآن مفعل ويمكنك تسجيل الدخول واستخدام كافة ميزات النظام.\nنتمنى لك عملاً موفقاً.";

        return $this->sendEmail($email, $subject, $body, $phone);
    }
}
