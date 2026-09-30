<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Models\InquiryMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffReply extends Mailable
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Inquiry $inquiry,
        public InquiryMessage $reply
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on your inquiry — '.$this->inquiry->reference_no
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.staff-reply'
        );
    }
}
