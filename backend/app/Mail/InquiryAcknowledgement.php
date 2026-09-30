<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryAcknowledgement extends Mailable
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Inquiry $inquiry
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your inquiry — '.$this->inquiry->reference_no
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.acknowledgement'
        );
    }
}
