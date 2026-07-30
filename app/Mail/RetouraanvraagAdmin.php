<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RetouraanvraagAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data,
        public string $rmaNumber,
        public ?UploadedFile $file = null,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->data['name'];
        $email = $this->data['email'];
        $invoiceNumber = $this->data['factuurnummer1'] ?? '-';

        return new Envelope(
            subject: "[Retouraanvraag] Nieuwe retouraanvraag van {$name} - Factuur #{$invoiceNumber}",
            replyTo: [
                new Address($email, $name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.retouraanvraag-admin',
            with: [
                'data' => $this->data,
                'products' => $this->products(),
                'rmaNumber' => $this->rmaNumber,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->file instanceof UploadedFile) {
            return [];
        }

        return [
            Attachment::fromPath($this->file->getRealPath())
                ->as($this->file->getClientOriginalName())
                ->withMime($this->file->getMimeType() ?: 'application/octet-stream'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return collect([1, 2])
            ->map(function (int $index): array {
                return [
                    'name' => $this->data["naam{$index}"] ?? null,
                    'sku' => $this->data["artikelnummer{$index}"] ?? null,
                    'quantity' => $this->data["aantal{$index}"] ?? null,
                    'invoice_number' => $this->data["factuurnummer{$index}"] ?? null,
                    'invoice_date' => $this->data["factuurdatum{$index}"] ?? null,
                    'problem' => $this->data["probleem{$index}"] ?? null,
                    'reasons' => $this->data["reden{$index}"] ?? [],
                    'notes' => $this->data["toelichting{$index}"] ?? null,
                ];
            })
            ->filter(fn (array $product): bool => collect($product)->except(['reasons'])->filter()->isNotEmpty() || ! empty($product['reasons']))
            ->values()
            ->all();
    }
}
