<?php

namespace App\Services\Moneybird;

use App\Models\MoneybirdSetting;
use App\Models\Order;
use App\Support\OrderEmailDetails;

class MoneybirdInvoicePayloadBuilder
{
    /**
     * @return array{sales_invoice: array<string, mixed>}
     */
    public function build(Order $order): array
    {
        $order->loadMissing(['items', 'adjustmentsRelation']);
        $details = new OrderEmailDetails($order);
        $amounts = $details->amounts();
        $invoiceLines = [];
        $discount = $amounts === []
            ? abs((float) $order->adjustmentsRelation->where('type', 'promotion')->sum('amount'))
            : $details->discount();
        $fees = $amounts === []
            ? (float) $order->adjustmentsRelation->where('type', 'misc')->sum('amount')
            : $details->fees();

        foreach ($details->items() as $item) {
            $invoiceLines[] = $this->line(
                $item['name'],
                $item['quantity'],
                $item['total'] / max(1, $item['quantity']),
            );
        }

        if ($details->shipping() !== 0.0) {
            $invoiceLines[] = $this->line((string) __('Shipping'), 1, $details->shipping());
        }

        if ($fees !== 0.0) {
            $invoiceLines[] = $this->line((string) __('Payment Fee'), 1, $fees);
        }

        if ($discount !== 0.0) {
            $invoiceLines[] = $this->line((string) __('Discount'), 1, -abs($discount));
        }

        if ($amounts === [] && $details->tax() !== 0.0) {
            $invoiceLines[] = $this->line((string) __('VAT'), 1, $details->tax());
        }

        $difference = $this->cents($details->total()) - array_sum(array_map(
            fn (array $line): int => $this->cents((float) $line['price'] * (float) $line['amount']),
            $invoiceLines,
        ));

        if ($difference !== 0) {
            $invoiceLines[] = $this->line((string) __('Rounding correction'), 1, $difference / 100);
        }

        $settings = MoneybirdSetting::resolved();
        $invoice = [
            'reference' => $order->number,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => $order->currency ?: 'EUR',
            'prices_are_incl_tax' => true,
            'details_attributes' => $invoiceLines,
        ];

        foreach (['workflow_id', 'document_style_id'] as $key) {
            if (filled($settings[$key])) {
                $invoice[$key] = $settings[$key];
            }
        }

        return ['sales_invoice' => $invoice];
    }

    /**
     * @return array<string, string|null>
     */
    private function line(string $description, int $quantity, float $price): array
    {
        $line = [
            'description' => $description,
            'amount' => (string) $quantity,
            'price' => rtrim(rtrim(number_format($price, 10, '.', ''), '0'), '.'),
            'tax_rate_id' => null,
        ];
        $ledgerAccountId = MoneybirdSetting::resolved()['ledger_account_id'];

        if (filled($ledgerAccountId)) {
            $line['ledger_account_id'] = (string) $ledgerAccountId;
        }

        return $line;
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
