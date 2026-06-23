<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class IccProfileRequestAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $materialTitle = $this->data['materialTitle'] ?? '';
        $printerModel = $this->data['printerModel'] ?? '';
        $email = $this->data['email'] ?? '';
        $name = $this->data['companyName'] ?? $email; // Fallback to email if no name

        $subject = $materialTitle
            ? "ICC Profile Request: {$materialTitle} — {$printerModel}"
            : "ICC Profile Request — {$printerModel}";

        return new Envelope(
            subject: $subject,
            replyTo: [
                new Address($email, $name),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.icc_profile_request',
            with: [
                'data' => $this->data,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
