@php
    // استخراج بيانات الهوية والتواصل من إعدادات النظام الموحدة (SystemSettings) مع السقوط على المتغيرات الممررة
    $AppName         = $AppName ?? systemSetting('app_name', config('app.name', 'NetSales | نظام مبيعات الشبكة'));
    $CompanyName     = $CompanyName ?? systemSetting('app_company_name', 'Smartix Technology Solutions');
    $CompanyPhone    = $CompanyPhone ?? systemSetting('app_support_phone', '+967 772 957 881');
    $CompanyEmail    = $CompanyEmail ?? systemSetting('app_support_email', 'smartixitco@gmail.com');
    $CompanyAddress  = $CompanyAddress ?? systemSetting('app_company_address', 'اليمن - تعز');
    $WebsiteUrl      = $WebsiteUrl ?? systemSetting('app_website_url', 'https://www.smartixit.com');

    $CompanyLogoPath = $CompanyLogoPath ?? $CompanyLogo ?? systemSetting('app_company_logo', 'images/company_logo.png');
    $AppLogoPath     = $AppLogoPath ?? $AppLogo ?? systemSetting('app_logo', 'images/brand_logo.png');

    $FacebookUrl     = $FacebookUrl ?? systemSetting('social_facebook', 'https://facebook.com');
    $TwitterUrl      = $TwitterUrl ?? systemSetting('social_twitter', null);
    $InstaUrl        = $InstaUrl ?? systemSetting('social_instagram', 'https://instagram.com');
    $TelegramUrl     = $TelegramUrl ?? systemSetting('social_telegram', null);

    $CTA_Link        = $CTA_Link ?? null;
    $MessageTitle    = $MessageTitle ?? 'إشعار جديد من ' . $AppName;
    $UserName        = $UserName ?? 'عميلنا العزيز';
    $MessageBody     = $MessageBody ?? '';

    // حساب نطاق سنوات حقوق النشر بأسلوب الشركات العالمية (مثال: 2026 أو 2026 – 2027)
    $inceptionYear   = (int) systemSetting('app_inception_year', 2026);
    $currentYear     = (int) date('Y');
    $copyrightSpan   = $currentYear > $inceptionYear ? "{$inceptionYear} – {$currentYear}" : (string) $currentYear;

    // حل ذكي للأيقونات البصرية: استخدام CDN موثوق عالي السرعة ومتوافق مع كافة برامج البريد الإلكتروني
    $appUrl = config('app.url', '');
    $isLocalEnv = empty($appUrl) || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1') || preg_match('/:\d+$/', $appUrl);

    $getIcon = function($filename, $cdnUrl) use ($isLocalEnv) {
        if ($isLocalEnv) {
            return $cdnUrl;
        }
        return asset('images/icons/' . $filename);
    };

    $iconShield    = $getIcon('shield.png', 'https://img.icons8.com/fluency/48/shield.png');
    $iconLocation  = $getIcon('location.png', 'https://img.icons8.com/fluency/48/marker.png');
    $iconEmail     = $getIcon('email.png', 'https://img.icons8.com/fluency/48/mail--v1.png');
    $iconPhone     = $getIcon('phone.png', 'https://img.icons8.com/fluency/48/phone.png');
    $iconFacebook  = $getIcon('facebook.png', 'https://img.icons8.com/color/48/facebook-new.png');
    $iconTwitter   = $getIcon('twitterx.png', 'https://img.icons8.com/color/48/twitterx--v1.png');
    $iconInstagram = $getIcon('instagram.png', 'https://img.icons8.com/color/48/instagram-new--v1.png');
    $iconTelegram  = $getIcon('telegram.png', 'https://img.icons8.com/color/48/telegram-app.png');
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $MessageTitle ?? 'إشعار من نظام مبيعات الشبكة' }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, p, a, h1, h2, h3 { font-family: 'Segoe UI', Tahoma, Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; width: 100% !important;
            background-color: #f1f5f9;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; display: block; max-width: 100%; height: auto; }
        a { text-decoration: none; }
        body, table, td, p, a, span { word-break: break-word; overflow-wrap: break-word; }
        .ef { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans Arabic', sans-serif; }
        @media only screen and (max-width: 620px) {
            .wrap { width: 100% !important; padding: 0 12px !important; }
            .stack { display: block !important; width: 100% !important; text-align: center !important; }
            .card  { border-radius: 14px !important; }
            .cp    { padding: 24px 16px 28px !important; }
            .fp    { padding: 24px 12px !important; }
            .h1    { font-size: 20px !important; }
            .cta-wrap { width: 100% !important; }
            .cta   { display: block !important; padding: 14px 20px !important; font-size: 14px !important; text-align: center !important; }
            .logo-m { margin: 0 auto 15px !important; }
            .img-m { max-width: 140px !important; margin: 0 auto !important; }
            .foot-col { border-left: none !important; border-right: none !important; border-bottom: 1px solid #e2e8f0 !important; padding: 20px 0 !important; }
            .foot-col:last-child { border-bottom: none !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;direction:rtl;text-align:right;">

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f1f5f9;width:100% !important;table-layout:fixed;">
        <tr>
            <td align="center" style="padding:40px 16px 32px;">
                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="600" style="width:600px;">
                <tr>
                <td align="center" valign="top" width="600" style="width:600px;">
                <![endif]-->
                <table border="0" cellpadding="0" cellspacing="0" width="600" align="center" class="wrap" style="width:100%;max-width:600px;margin:0 auto;table-layout:fixed;">

                    {{-- ══════════════ HEADER ══════════════ --}}
                    <tr>
                        <td style="padding-bottom:24px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    {{-- Company logo + name (RIGHT) --}}
                                    <td align="right" valign="middle" class="stack" style="padding-bottom:12px;">
                                        <table border="0" cellpadding="0" cellspacing="0" class="logo-m">
                                            <tr>
                                                @php
                                                    $resolvedLogoUrl = null;
                                                    if (!empty($CompanyLogoPath)) {
                                                        if (filter_var($CompanyLogoPath, FILTER_VALIDATE_URL)) {
                                                            $resolvedLogoUrl = $CompanyLogoPath;
                                                        } elseif (file_exists(public_path($CompanyLogoPath))) {
                                                            $resolvedLogoUrl = asset($CompanyLogoPath);
                                                        } elseif (file_exists(storage_path('app/public/' . $CompanyLogoPath))) {
                                                            $resolvedLogoUrl = asset('storage/' . $CompanyLogoPath);
                                                        }
                                                    }
                                                @endphp
                                                @if($resolvedLogoUrl)
                                                <td valign="middle" style="padding-left:14px;">
                                                    <img src="{{ $resolvedLogoUrl }}"
                                                         alt="{{ $CompanyName }}"
                                                         width="220"
                                                         style="display:block;width:220px;max-width:100%;height:auto;max-height:70px;object-fit:contain;border:0;">
                                                </td>
                                                @else
                                                <td valign="middle">
                                                    <p style="margin:0;font-size:22px;font-weight:900;background:linear-gradient(135deg,#008769,#005C45);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;" class="ef">
                                                        {{ $CompanyName }}
                                                    </p>
                                                </td>
                                                @endif
                                            </tr>
                                        </table>
                                    </td>
                                    {{-- App name / tagline (LEFT) --}}
                                    <td align="left" valign="middle" class="stack">
                                        @if(!empty($WebsiteUrl))
                                        <a href="{{ $WebsiteUrl }}" class="ef"
                                           style="font-size:12px;font-weight:700;color:#008769;letter-spacing:0.3px;">
                                            {{ str_replace(['https://','http://'],'',$WebsiteUrl) }}
                                        </a>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            {{-- NetSales Teal Gradient Accent Line --}}
                            <div style="height:3px;background:linear-gradient(to left,#00A884,#008769,#005C45,#0F766E);border-radius:2px;margin-top:16px;"></div>
                        </td>
                    </tr>

                    {{-- ══════════════ MAIN CARD ══════════════ --}}
                    <tr>
                        <td class="card" style="background-color:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 8px 30px rgba(0,92,69,0.08);border:1px solid #e2e8f0;">

                            {{-- Top Emerald accent bar --}}
                            <div style="height:5px;background:linear-gradient(to left,#00A884,#008769,#005C45);"></div>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td class="cp" style="padding:40px 44px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">

                                            {{-- App logo + Greeting --}}
                                            <tr>
                                                <td style="padding-bottom:24px;">
                                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                        <tr>
                                                            <td align="right" valign="middle" class="stack" style="padding-bottom:12px;">
                                                                <p class="ef" style="margin:0;font-size:15px;color:#64748b;font-weight:500;">
                                                                    مرحباً، <strong style="color:#0f172a;">{{ $UserName }} 👋</strong>
                                                                </p>
                                                            </td>
                                                            @php
                                                                $resolvedAppLogoUrl = null;
                                                                if (!empty($AppLogoPath)) {
                                                                    if (filter_var($AppLogoPath, FILTER_VALIDATE_URL)) {
                                                                        $resolvedAppLogoUrl = $AppLogoPath;
                                                                    } elseif (file_exists(public_path($AppLogoPath))) {
                                                                        $resolvedAppLogoUrl = asset($AppLogoPath);
                                                                    } elseif (file_exists(storage_path('app/public/' . $AppLogoPath))) {
                                                                        $resolvedAppLogoUrl = asset('storage/' . $AppLogoPath);
                                                                    }
                                                                }
                                                            @endphp
                                                            @if($resolvedAppLogoUrl)
                                                            <td align="left" valign="middle" class="stack" style="padding-right:16px;">
                                                                <img src="{{ $resolvedAppLogoUrl }}"
                                                                     alt="{{ $AppName }}"
                                                                     width="150"
                                                                     style="display:block;width:150px;max-width:100%;height:auto;max-height:55px;object-fit:contain;border:0;"
                                                                     class="img-m">
                                                            </td>
                                                            @else
                                                            <td align="left" valign="middle" class="stack">
                                                                <span class="ef" style="font-size:14px;font-weight:800;color:#008769;background-color:#E6F6F2;padding:6px 14px;border-radius:8px;">
                                                                    {{ $AppName }}
                                                                </span>
                                                            </td>
                                                            @endif
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>

                                            {{-- Divider --}}
                                            <tr>
                                                <td style="padding-bottom:24px;">
                                                    <div style="height:1px;background-color:#f1f5f9;"></div>
                                                </td>
                                            </tr>

                                            {{-- Title --}}
                                            <tr>
                                                <td style="padding-bottom:18px;">
                                                    <h1 class="h1 ef"
                                                        style="margin:0;font-size:24px;font-weight:800;color:#0f172a;line-height:1.4;letter-spacing:-0.4px;">
                                                        {{ $MessageTitle }}
                                                    </h1>
                                                    {{-- Underline accent --}}
                                                    <div style="width:44px;height:3px;background:linear-gradient(to left,#00A884,#008769);border-radius:2px;margin-top:10px;"></div>
                                                </td>
                                            </tr>

                                            {{-- Body --}}
                                            <tr>
                                                <td style="padding-bottom:32px;">
                                                    <p class="ef" style="margin:0;font-size:15px;line-height:1.9;color:#334155;">
                                                        {!! nl2br(e($MessageBody)) !!}
                                                    </p>
                                                </td>
                                            </tr>

                                            {{-- CTA Button --}}
                                            @if($CTA_Link)
                                            <tr>
                                                <td align="right" style="padding-bottom:32px;" class="stack">
                                                    <table border="0" cellpadding="0" cellspacing="0" class="cta-wrap">
                                                        <tr>
                                                            <td style="border-radius:12px;background:linear-gradient(135deg,#008769,#005C45);box-shadow:0 6px 20px rgba(0,135,105,0.25);">
                                                                <a href="{{ $CTA_Link }}" target="_blank"
                                                                   class="cta ef"
                                                                   style="display:inline-block;padding:14px 38px;color:#ffffff;font-size:15px;font-weight:700;border-radius:12px;letter-spacing:0.2px;">
                                                                    انقر هنا للمتابعة ←
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            @endif

                                            {{-- Security Notice --}}
                                            <tr>
                                                <td>
                                                    <div style="background:linear-gradient(135deg,#f0fdf4,#f8fafc);border-radius:14px;border:1px solid #dcfce7;overflow:hidden;padding:16px 20px;">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                            <tr>
                                                                <td valign="top" width="44" style="padding-left:12px;">
                                                                    <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate !important;width:36px;height:36px;background-color:#ffffff;border:1px solid #bbf7d0;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                                                                        <tr>
                                                                            <td align="center" valign="middle" style="width:36px;height:36px;border-radius:10px;">
                                                                                <img src="{{ $iconShield }}" alt="أمان" width="20" height="20" style="display:block;margin:0 auto;border:0;">
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                                <td valign="middle">
                                                                    <p class="ef" style="margin:0;font-size:12px;color:#475569;line-height:1.7;">
                                                                        <strong style="color:#008769;">تنبيه أمني:</strong>
                                                                        فريق {{ $AppName }} لن يطلب منك كلمة مرورك أو بياناتك السرية عبر البريد الإلكتروني إطلاقاً. تأكد دائماً من الرابط قبل اتخاذ أي إجراء.
                                                                    </p>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>

                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ══════════════ FOOTER ══════════════ --}}
                    <tr>
                        <td class="fp" style="padding:40px 24px; text-align:center;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">

                                {{-- Contact Columns (Icon above Text) --}}
                                @if(!empty($CompanyPhone) || !empty($CompanyEmail) || !empty($CompanyAddress))
                                <tr>
                                    <td align="center" style="padding-bottom:36px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                {{-- Address --}}
                                                @if(!empty($CompanyAddress))
                                                <td align="center" valign="top" style="width:33%;" class="stack foot-col">
                                                    <table border="0" cellpadding="0" cellspacing="0" align="center" style="border-collapse:separate !important;margin:0 auto 12px;width:42px;height:42px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                                                        <tr>
                                                            <td align="center" valign="middle" style="width:42px;height:42px;border-radius:50%;">
                                                                <img src="{{ $iconLocation }}" alt="العنوان" width="20" height="20" style="display:block;margin:0 auto;border:0;">
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <span class="ef" style="font-size:13px; color:#1e293b; font-weight:700; line-height:1.4;">
                                                        {{ $CompanyAddress }}
                                                    </span>
                                                </td>
                                                @endif

                                                {{-- Email --}}
                                                @if(!empty($CompanyEmail))
                                                <td align="center" valign="top" style="width:34%; border-right:1.5px solid #e2e8f0; border-left:1.5px solid #e2e8f0;" class="stack foot-col">
                                                    <table border="0" cellpadding="0" cellspacing="0" align="center" style="border-collapse:separate !important;margin:0 auto 12px;width:42px;height:42px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                                                        <tr>
                                                            <td align="center" valign="middle" style="width:42px;height:42px;border-radius:50%;">
                                                                <img src="{{ $iconEmail }}" alt="البريد" width="20" height="20" style="display:block;margin:0 auto;border:0;">
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <a href="mailto:{{ $CompanyEmail }}" class="ef" dir="ltr"
                                                       style="font-size:13px; color:#008769; font-weight:700; text-decoration:none; direction:ltr; display:inline-block;">
                                                        {{ $CompanyEmail }}
                                                    </a>
                                                </td>
                                                @endif

                                                {{-- Phone --}}
                                                @if(!empty($CompanyPhone))
                                                <td align="center" valign="top" style="width:33%;" class="stack foot-col">
                                                    <table border="0" cellpadding="0" cellspacing="0" align="center" style="border-collapse:separate !important;margin:0 auto 12px;width:42px;height:42px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                                                        <tr>
                                                            <td align="center" valign="middle" style="width:42px;height:42px;border-radius:50%;">
                                                                <img src="{{ $iconPhone }}" alt="الهاتف" width="20" height="20" style="display:block;margin:0 auto;border:0;">
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <a href="tel:{{ $CompanyPhone }}" class="ef" dir="ltr"
                                                       style="font-size:13px; color:#008769; font-weight:700; text-decoration:none; direction:ltr; display:inline-block;">
                                                        {{ $CompanyPhone }}
                                                    </a>
                                                </td>
                                                @endif
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endif

                                {{-- Elegant Divider --}}
                                <tr>
                                    <td style="padding-bottom:28px;">
                                        <div style="height:1px; background:linear-gradient(to left, transparent, #00876966, transparent);"></div>
                                    </td>
                                </tr>

                                {{-- Brand Info --}}
                                <tr>
                                    <td style="padding-bottom:10px;">
                                        <h3 class="ef" style="margin:0; font-size:17px; font-weight:800; color:#0f172a; letter-spacing:-0.2px;">
                                            {{ $CompanyName }}
                                            @if(!empty($AppName) && $AppName !== $CompanyName)
                                                <span style="color:#00A884; margin:0 8px;">•</span> {{ $AppName }}
                                            @endif
                                        </h3>
                                    </td>
                                </tr>

                                {{-- Copyrights --}}
                                <tr>
                                    <td style="padding-bottom:24px;">
                                        <p class="ef" style="margin:0; font-size:12px; color:#64748b; line-height:1.7;">
                                            &copy; {{ $copyrightSpan }} {{ $CompanyName }}. جميع الحقوق محفوظة.
                                            @if(!empty($WebsiteUrl))
                                                &nbsp;&nbsp;<span style="color:#cbd5e1;">•</span>&nbsp;&nbsp;
                                                <a href="{{ $WebsiteUrl }}" style="color:#008769; font-weight:700; text-decoration:none;">{{ str_replace(['https://','http://'],'',$WebsiteUrl) }}</a>
                                            @endif
                                        </p>
                                    </td>
                                </tr>

                                {{-- Social Icons --}}
                                @if(!empty($FacebookUrl) || !empty($TwitterUrl) || !empty($InstaUrl) || !empty($TelegramUrl))
                                <tr>
                                    <td align="center" style="padding-bottom:30px;">
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                @if($FacebookUrl)
                                                <td style="padding:0 6px;">
                                                    <a href="{{ $FacebookUrl }}" target="_blank" style="text-decoration:none;">
                                                        <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate !important;width:40px;height:40px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
                                                            <tr>
                                                                <td align="center" valign="middle" style="width:40px;height:40px;border-radius:10px;">
                                                                    <img src="{{ $iconFacebook }}" alt="Facebook" width="20" height="20" style="display:block;margin:0 auto;border:0;">
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </a>
                                                </td>
                                                @endif
                                                @if($TwitterUrl)
                                                <td style="padding:0 6px;">
                                                    <a href="{{ $TwitterUrl }}" target="_blank" style="text-decoration:none;">
                                                        <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate !important;width:40px;height:40px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
                                                            <tr>
                                                                <td align="center" valign="middle" style="width:40px;height:40px;border-radius:10px;">
                                                                    <img src="{{ $iconTwitter }}" alt="X" width="18" height="18" style="display:block;margin:0 auto;border:0;">
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </a>
                                                </td>
                                                @endif
                                                @if($InstaUrl)
                                                <td style="padding:0 6px;">
                                                    <a href="{{ $InstaUrl }}" target="_blank" style="text-decoration:none;">
                                                        <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate !important;width:40px;height:40px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
                                                            <tr>
                                                                <td align="center" valign="middle" style="width:40px;height:40px;border-radius:10px;">
                                                                    <img src="{{ $iconInstagram }}" alt="Instagram" width="20" height="20" style="display:block;margin:0 auto;border:0;">
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </a>
                                                </td>
                                                @endif
                                                @if($TelegramUrl)
                                                <td style="padding:0 6px;">
                                                    <a href="{{ $TelegramUrl }}" target="_blank" style="text-decoration:none;">
                                                        <table border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate !important;width:40px;height:40px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
                                                            <tr>
                                                                <td align="center" valign="middle" style="width:40px;height:40px;border-radius:10px;">
                                                                    <img src="{{ $iconTelegram }}" alt="Telegram" width="20" height="20" style="display:block;margin:0 auto;border:0;">
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </a>
                                                </td>
                                                @endif
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endif

                                {{-- No Reply Footer --}}
                                <tr>
                                    <td>
                                        <div style="display:inline-block; padding:7px 18px; background-color:#e2e8f0; border-radius:20px;">
                                            <p class="ef" style="margin:0; font-size:11px; color:#64748b; font-weight:600;">
                                                هذا البريد الإلكتروني تم إرساله آلياً من نظام {{ $AppName }}، يُرجى عدم الرد عليه مباشرة.
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                </table>
                <!--[if (gte mso 9)|(IE)]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>

</body>
</html>
