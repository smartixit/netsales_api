<?php

namespace App\Enums;

/**
 * الأنواع والتصنيفات الدومينية الموحدة للإشعارات والتنبيهات (Enterprise Notification Type Enum).
 *
 * يمثل المصدر الأوحد للحقيقة لتصنيف الإشعارات وتحديد مسارات التوجيه الذكي (Deep Linking)
 * مع الكيانات المرتبطة دون الحاجة لـ Enum إضافي.
 */
enum NotificationType: string
{
    // فواتير وعمليات بيع الكروت
    case SALE_INVOICE_CREATED = 'SALE_INVOICE_CREATED';
    case SALE_INVOICE_COMPLETED = 'SALE_INVOICE_COMPLETED';
    case SALE_INVOICE_CANCELLED = 'SALE_INVOICE_CANCELLED';

    // طلبات كروت الإنترنت
    case VOUCHER_REQUEST_CREATED = 'VOUCHER_REQUEST_CREATED';
    case VOUCHER_REQUEST_APPROVED = 'VOUCHER_REQUEST_APPROVED';
    case VOUCHER_REQUEST_REJECTED = 'VOUCHER_REQUEST_REJECTED';
    case VOUCHER_REQUEST_CANCELLED = 'VOUCHER_REQUEST_CANCELLED';

    // المحافظ والحركات المالية
    case WALLET_DEPOSIT = 'WALLET_DEPOSIT';
    case WALLET_WITHDRAWAL = 'WALLET_WITHDRAWAL';
    case WALLET_TRANSFER = 'WALLET_TRANSFER';

    // تذاكر ورسائل الدعم الفني
    case SUPPORT_TICKET_CREATED = 'SUPPORT_TICKET_CREATED';
    case SUPPORT_TICKET_REPLIED = 'SUPPORT_TICKET_REPLIED';
    case SUPPORT_TICKET_CLOSED = 'SUPPORT_TICKET_CLOSED';

    // اعتماد وحالات الشبكات ونقاط البيع
    case NETWORK_APPROVED = 'NETWORK_APPROVED';
    case NETWORK_REJECTED = 'NETWORK_REJECTED';
    case POS_APPROVED = 'POS_APPROVED';
    case POS_REJECTED = 'POS_REJECTED';

    // الأمان وحماية الأجهزة والحسابات
    case SECURITY_LOGIN_ALERT = 'SECURITY_LOGIN_ALERT';
    case SECURITY_DEVICE_BLOCKED = 'SECURITY_DEVICE_BLOCKED';
    case SECURITY_PASSWORD_CHANGED = 'SECURITY_PASSWORD_CHANGED';

    // إعلانات النظام العامة
    case SYSTEM_ANNOUNCEMENT = 'SYSTEM_ANNOUNCEMENT';
    case GENERAL_INFO = 'GENERAL_INFO';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    /**
     * الاسم والوصف المعرب المعياري لنوع الإشعار.
     */
    public function label(): string
    {
        return match ($this) {
            self::SALE_INVOICE_CREATED => 'فاتورة مبيعات جديدة',
            self::SALE_INVOICE_COMPLETED => 'اكتمال فاتورة المبيعات',
            self::SALE_INVOICE_CANCELLED => 'إلغاء فاتورة مبيعات',

            self::VOUCHER_REQUEST_CREATED => 'طلب كروت إنترنت جديد',
            self::VOUCHER_REQUEST_APPROVED => 'تمت الموافقة على طلب الكروت',
            self::VOUCHER_REQUEST_REJECTED => 'تم رفض طلب الكروت',
            self::VOUCHER_REQUEST_CANCELLED=> 'تم إلغاء طلب الكروت',

            self::WALLET_DEPOSIT => 'إيداع في المحفظة',
            self::WALLET_WITHDRAWAL => 'سحب من المحفظة',
            self::WALLET_TRANSFER => 'تحويل رصيد محفظة',

            self::SUPPORT_TICKET_CREATED => 'تذكرة دعم فني جديدة',
            self::SUPPORT_TICKET_REPLIED => 'رد جديد على تذكرة الدعم',
            self::SUPPORT_TICKET_CLOSED => 'إغلاق تذكرة الدعم',

            self::NETWORK_APPROVED => 'اعتماد مزود الشبكة',
            self::NETWORK_REJECTED => 'رفض مزود الشبكة',
            self::POS_APPROVED => 'اعتماد نقطة البيع',
            self::POS_REJECTED => 'رفض نقطة البيع',

            self::SECURITY_LOGIN_ALERT => 'تنبيه تسجيل دخول',
            self::SECURITY_DEVICE_BLOCKED => 'حظر أو تعطيل جهاز',
            self::SECURITY_PASSWORD_CHANGED => 'تغيير كلمة المرور',

            self::SYSTEM_ANNOUNCEMENT => 'إعلان إداري عام',
            self::GENERAL_INFO => 'إشعار توجيهي عام',
        };
    }

    /**
     * التصنيف العام لنوع الإشعار (Category Grouping).
     */
    public function category(): string
    {
        return match ($this) {
            self::SALE_INVOICE_CREATED,
            self::SALE_INVOICE_COMPLETED,
            self::SALE_INVOICE_CANCELLED,
            self::WALLET_DEPOSIT,
            self::WALLET_WITHDRAWAL,
            self::WALLET_TRANSFER => 'FINANCIAL',

            self::VOUCHER_REQUEST_CREATED,
            self::VOUCHER_REQUEST_APPROVED,
            self::VOUCHER_REQUEST_REJECTED,
            self::VOUCHER_REQUEST_CANCELLED,
            self::NETWORK_APPROVED,
            self::NETWORK_REJECTED,
            self::POS_APPROVED,
            self::POS_REJECTED => 'OPERATIONAL',

            self::SUPPORT_TICKET_CREATED,
            self::SUPPORT_TICKET_REPLIED,
            self::SUPPORT_TICKET_CLOSED => 'SUPPORT',

            self::SECURITY_LOGIN_ALERT,
            self::SECURITY_DEVICE_BLOCKED,
            self::SECURITY_PASSWORD_CHANGED => 'SECURITY',

            self::SYSTEM_ANNOUNCEMENT,
            self::GENERAL_INFO => 'SYSTEM',
        };
    }

    /**
     * هل يتطلب الإشعار معرف كيان محدد (entity_id) للتوجيه المباشر؟
     */
    public function requiresEntityId(): bool
    {
        return match ($this) {
            self::SALE_INVOICE_CREATED,
            self::SALE_INVOICE_COMPLETED,
            self::SALE_INVOICE_CANCELLED,
            self::VOUCHER_REQUEST_CREATED,
            self::VOUCHER_REQUEST_APPROVED,
            self::VOUCHER_REQUEST_REJECTED,
            self::VOUCHER_REQUEST_CANCELLED,
            self::WALLET_DEPOSIT,
            self::WALLET_WITHDRAWAL,
            self::WALLET_TRANSFER,
            self::SUPPORT_TICKET_CREATED,
            self::SUPPORT_TICKET_REPLIED,
            self::SUPPORT_TICKET_CLOSED,
            self::NETWORK_APPROVED,
            self::NETWORK_REJECTED,
            self::POS_APPROVED,
            self::POS_REJECTED => true,
            default => false,
        };
    }

    public static function labels(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->label();
        }

        return $map;
    }
}
