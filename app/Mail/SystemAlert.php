<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * تنبيه تشغيلي إلى مسؤول الدعم الفني (ALERT_EMAIL). الرسالة البريدية الوحيدة في
 * النظام، استثناءً محصورًا معتمدًا (docs/DECISIONS.md): بلا بيانات شخصية، ولا
 * تُرسل لأي مستخدم. لا تُوضع في الطوابير عمدًا (قد تكون هي المتوقفة).
 */
class SystemAlert extends Mailable
{
    public function __construct(public string $alert, public string $details) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('health.alerts.subject', [
                'app' => (string) config('app.name'),
                'title' => __('health.alerts.'.$this->alert.'.title'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.system-alert',
            with: [
                'title' => __('health.alerts.'.$this->alert.'.title'),
                'action' => __('health.alerts.'.$this->alert.'.action'),
                'details' => $this->details,
                'time' => now()->timezone('Asia/Riyadh')->format('Y-m-d H:i'),
                'healthUrl' => url((string) config('admin.path').'/system-health'),
            ],
        );
    }
}
