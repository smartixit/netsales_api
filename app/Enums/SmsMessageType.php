<?php

namespace App\Enums;

enum SmsMessageType: string
{
    case RECEIPT = 'receipt';                         // سند قبض لنقطة البيع (لكم)
    case PAYMENT = 'payment';                         // سند دفع / قيد على نقطة البيع (عليكم)
    case POS_WITHDRAW = 'pos_withdraw';               // سحب نقطة البيع
    case EXPENSE = 'expense';                         // مصروفات الشبكة
    case SALE_INVOICE = 'sale_invoice';               // فواتير مبيعات الكروت
    case VOUCHER_REQUEST = 'voucher_request';         // طلبات الكروت والرصيد
    case LOW_BALANCE = 'low_balance';                 // تنبيه انخفاض الرصيد
    case DAILY_SUMMARY = 'daily_summary';             // الملخص اليومي
    case MONTHLY_SUMMARY = 'monthly_summary';         // الملخص الشهري
    case SUBSCRIPTION_EXPIRY = 'subscription_expiry'; // انتهاء الاشتراك
    case OTP = 'otp';                                 // رموز التحقق
    case GENERAL = 'general';                         // رسائل عامة

    /**
     * اسم العمود المقابل في جدول network_sms_settings
     */
    public function settingColumn(): ?string
    {
        return match ($this) {
            self::RECEIPT => 'notify_receipt',
            self::PAYMENT => 'notify_payment',
            self::POS_WITHDRAW => 'notify_pos_withdraw',
            self::EXPENSE => 'notify_expense',
            self::SALE_INVOICE => 'notify_sale_invoice',
            self::VOUCHER_REQUEST => 'notify_voucher_request',
            self::LOW_BALANCE => 'notify_low_balance',
            self::DAILY_SUMMARY => 'notify_daily_summary',
            self::MONTHLY_SUMMARY => 'notify_monthly_summary',
            self::SUBSCRIPTION_EXPIRY => 'notify_subscription_expiry',
            self::OTP, self::GENERAL => null,
        };
    }

    /**
     * التسمية العربية لنوع الرسالة
     */
    public function label(): string
    {
        return match ($this) {
            self::RECEIPT => 'سند قبض',
            self::PAYMENT => 'سند دفع / قيد',
            self::POS_WITHDRAW => 'سحب نقطة بيع',
            self::EXPENSE => 'مصروف شبكة',
            self::SALE_INVOICE => 'فاتورة مبيعات',
            self::VOUCHER_REQUEST => 'طلب بطائق ورصيد',
            self::LOW_BALANCE => 'تنبيه انخفاض الرصيد',
            self::DAILY_SUMMARY => 'الملخص اليومي',
            self::MONTHLY_SUMMARY => 'الملخص الشهري',
            self::SUBSCRIPTION_EXPIRY => 'تنبيه اشتراك الشبكة',
            self::OTP => 'رمز تحقق OTP',
            self::GENERAL => 'رسالة عامة',
        };
    }
}
