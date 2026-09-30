<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Reminder $reminder
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Follow-up reminder — '.$this->reminder->inquiry->reference_no
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reminder',
            with: [
                'inquiryUrl' => rtrim(config('app.frontend_url'), '/')
                    .'/inquiries/'.$this->reminder->inquiry_id,
            ]
        );
    }
}
