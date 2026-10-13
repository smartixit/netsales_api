<?php

namespace App\Jobs;

use App\Mail\GenericNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $email,
        public string $subject,
        public string $messageBody,
        public string $template = 'emails.notification',
        public ?string $userName = null,
        public ?string $ctaLink = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->template === 'emails.notification') {
            Mail::to($this->email)->send(new GenericNotificationMail(
               [
                'userName' => $this->userName,
                    'ctaLink'  => $this->ctaLink,
                    'subject'  => $this->subject,
                    'body'     => $this->messageBody,
               ]
            ));
            return;
        }

        Mail::send($this->template, [
            'UserName'        => $this->userName ?? 'عميلنا العزيز',
            'MessageTitle'    => $this->subject,
            'MessageBody'     => $this->messageBody,
            'CTA_Link'        => $this->ctaLink ?? '#',
            'CompanyLogo'     => 'images/company_logo.png',
            'CompanyLogoPath' => 'images/company_logo.png',
            'AppLogo'         => 'images/brand_logo.png',
            'AppLogoPath'     => 'images/brand_logo.png',
            'CompanyName'     => 'Smartix - للحلول التقنية الذكية',
            'AppName'         => 'نظام مبيعات الشبكة | NetSales',
            'CompanyPhone'    => '+967 772 957 881',
            'CompanyAddress'  => 'اليمن - تعز',
            'CompanyEmail'    => 'smartixitco@gmail.com',
            'WebsiteUrl'      => 'https://www.smartixit.com',
            'FacebookUrl'     => 'https://facebook.com',
            'TwitterUrl'      => null,
            'InstaUrl'        => 'https://instagram.com',
            'TelegramUrl'     => null,
        ], function (Message $message) {
            $message->to($this->email)
                    ->subject($this->subject);
        });
    }
}
