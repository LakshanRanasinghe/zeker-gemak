<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\ShippingDocumentException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vanilo\Order\Models\Order;

class GenerateDhlShippingDocuments
{
    private const SLIPS_FOLDER = 'Slips Arthur';

    private const LABELS_FOLDER = 'Labels Arthur';

    public function __construct(
        private readonly DhlClient $dhl,
        private readonly DropboxClient $dropbox,
    ) {}

    /**
     * @return array{tracking_number: string, packing_slip_path: string, label_path: string}
     */
    public function handle(Order $order, array $shipment = []): array
    {
        $stage = 'DHL label generation';

        try {
            $order->loadMissing(['items', 'billpayer.address', 'shippingAddress', 'adjustmentsRelation']);
            $label = $this->dhl->generateLabel($order, $shipment);

            $stage = 'packing slip rendering';
            $packingSlip = Pdf::loadView('pdf.packing-slip', [
                'order' => $order,
                'recipient' => $shipment['recipient'] ?? null,
            ])
                ->setPaper('a4', 'portrait')
                ->output();

            if (! str_starts_with($packingSlip, '%PDF')) {
                throw new \RuntimeException('Packing slip rendering returned invalid PDF data.');
            }

            $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $order->number);

            $stage = 'packing slip Dropbox upload';
            $packingSlipPath = $this->dropbox->put(
                self::SLIPS_FOLDER,
                "Pakbon-{$safeNumber}.pdf",
                $packingSlip,
            );

            $stage = 'DHL label Dropbox upload';
            $labelPath = $this->dropbox->put(
                self::LABELS_FOLDER,
                "DHL-label-{$safeNumber}.pdf",
                $label['pdf'],
            );

            $stage = 'order status update';
            DB::transaction(function () use ($order, $label, $packingSlipPath, $labelPath, $shipment): void {
                $order->forceFill([
                    'tracking_number' => $label['tracking_number'],
                    'dhl_data' => [
                        'shipment_id' => $label['shipment_id'],
                        'label_id' => $label['label_id'],
                        'packing_slip_path' => $packingSlipPath,
                        'label_path' => $labelPath,
                        'shipment' => collect($shipment)->except('recipient')->all(),
                    ],
                    'status' => OrderStatus::SHIPPED,
                ])->save();
            });

            Log::info('Zeker Gemak DHL shipping documents generated', [
                'order_id' => $order->id,
                'tracking_number' => $label['tracking_number'],
                'packing_slip_path' => $packingSlipPath,
                'label_path' => $labelPath,
            ]);

            return [
                'tracking_number' => $label['tracking_number'],
                'packing_slip_path' => $packingSlipPath,
                'label_path' => $labelPath,
            ];
        } catch (Throwable $exception) {
            Log::error('Zeker Gemak DHL shipping workflow failed', [
                'order_id' => $order->id,
                'stage' => $stage,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw ShippingDocumentException::failed($exception);
        }
    }
}
