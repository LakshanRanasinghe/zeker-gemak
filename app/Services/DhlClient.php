<?php

namespace App\Services;

use App\Models\DhlSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Vanilo\Order\Models\Order;

class DhlClient
{
    private ?array $settings = null;

    /**
     * @return array{pdf: string, tracking_number: string, shipment_id: ?string, label_id: string}
     */
    public function generateLabel(Order $order, array $shipment = []): array
    {
        $order->loadMissing(['billpayer.address', 'shippingAddress']);

        $address = $order->shippingAddress ?? $order->billpayer?->address;
        [$street, $houseNumber, $addition] = $this->splitAddress((string) $address?->address, (string) $address?->address2);
        $sender = (array) ($this->settings()['sender'] ?? []);

        $data = Validator::make([
            'credentials' => $this->settings(),
            'sender' => $sender,
            'recipient' => array_replace([
                'first_name' => $address?->firstname ?: $order->billpayer?->firstname,
                'last_name' => $address?->lastname ?: $order->billpayer?->lastname,
                'company' => $address?->company_name ?: $order->billpayer?->company_name,
                'street' => $street,
                'house_number' => $houseNumber,
                'addition' => $addition,
                'postal_code' => $address?->postalcode,
                'city' => $address?->city,
                'country_code' => $address?->country_id,
                'email' => $order->billpayer?->email,
                'phone' => $address?->phone ?: $order->billpayer?->phone,
            ], (array) ($shipment['recipient'] ?? [])),
            'shipment' => [
                'carrier' => $shipment['carrier'] ?? 'DHL-PARCEL',
                'shipping_method' => $shipment['shipping_method'] ?? ($this->settings()['product'] ?? 'DFY-B2C'),
                'parcel_type' => $shipment['parcel_type'] ?? ($this->settings()['parcel_type'] ?? 'SMALL'),
                'weight' => $shipment['weight'] ?? 1,
                'length' => $shipment['length'] ?? 10,
                'width' => $shipment['width'] ?? 10,
                'height' => $shipment['height'] ?? 10,
            ],
        ], [
            'credentials.user_id' => ['required', 'string'],
            'credentials.key' => ['required', 'string'],
            'credentials.account_id' => ['required', 'string'],
            'sender.company' => ['required', 'string', 'max:35'],
            'sender.first_name' => ['nullable', 'string', 'max:30'],
            'sender.last_name' => ['nullable', 'string', 'max:30'],
            'sender.street' => ['required', 'string', 'max:40'],
            'sender.house_number' => ['required', 'string', 'max:10'],
            'sender.house_number_addition' => ['nullable', 'string', 'max:10'],
            'sender.postal_code' => ['required', 'string', 'max:12'],
            'sender.city' => ['required', 'string', 'max:30'],
            'sender.country_code' => ['required', 'string', 'size:2'],
            'sender.email' => ['required', 'email', 'max:80'],
            'sender.phone' => ['nullable', 'string', 'max:25'],
            'sender.vat_number' => ['nullable', 'string', 'max:20'],
            'sender.eori_number' => ['nullable', 'string', 'max:20'],
            'recipient.first_name' => ['required', 'string'],
            'recipient.last_name' => ['required', 'string'],
            'recipient.company' => ['nullable', 'string', 'max:35'],
            'recipient.is_business' => ['nullable', 'boolean'],
            'recipient.street' => ['required', 'string', 'max:40'],
            'recipient.house_number' => ['required', 'string', 'max:10'],
            'recipient.addition' => ['nullable', 'string', 'max:10'],
            'recipient.postal_code' => ['required', 'string', 'max:12'],
            'recipient.city' => ['required', 'string', 'max:30'],
            'recipient.country_code' => ['required', 'string', 'size:2'],
            'recipient.email' => ['required', 'email', 'max:80'],
            'recipient.phone' => ['nullable', 'string', 'max:25'],
            'shipment.carrier' => ['required', 'in:DHL-PARCEL,DHL-EXPRESS'],
            'shipment.shipping_method' => ['required', 'string', 'max:50'],
            'shipment.parcel_type' => ['required', 'string', 'max:50'],
            'shipment.weight' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'shipment.length' => ['required', 'numeric', 'gt:0', 'max:999'],
            'shipment.width' => ['required', 'numeric', 'gt:0', 'max:999'],
            'shipment.height' => ['required', 'numeric', 'gt:0', 'max:999'],
        ], [
            'recipient.house_number.required' => 'The delivery address must include a house number.',
        ])->validate();

        $accessToken = $this->request()->post('authenticate/api-key', [
            'userId' => $data['credentials']['user_id'],
            'key' => $data['credentials']['key'],
            'accountNumbers' => [$data['credentials']['account_id']],
        ])->throw()->json('accessToken');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('DHL returned no access token.');
        }

        $shipmentId = Str::uuid()->toString();
        $response = $this->request()
            ->withToken($accessToken)
            ->post('shipments', $this->shipmentPayload($order, $data, $shipmentId))
            ->throw()
            ->json();

        $piece = $response['pieces'][0] ?? [];
        $labelId = trim((string) ($piece['labelId'] ?? ''));
        $trackingNumber = trim((string) ($piece['trackerCode'] ?? $piece['barcode'] ?? ''));

        if ($labelId === '' || $trackingNumber === '') {
            throw new RuntimeException('DHL returned incomplete label data.');
        }

        $encodedPdf = $this->request()
            ->withToken($accessToken)
            ->get("labels/{$labelId}")
            ->throw()
            ->json('pdf');
        $pdf = is_string($encodedPdf) ? base64_decode($encodedPdf, true) : false;

        if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF')) {
            throw new RuntimeException('DHL returned invalid label PDF data.');
        }

        return [
            'pdf' => $pdf,
            'tracking_number' => $trackingNumber,
            'shipment_id' => $response['shipmentId'] ?? $shipmentId,
            'label_id' => $labelId,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) ($this->settings()['base_url'] ?? ''), '/').'/')
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) ($this->settings()['connect_timeout'] ?? 2))
            ->timeout((int) ($this->settings()['timeout'] ?? 3));
    }

    private function settings(): array
    {
        return $this->settings ??= DhlSetting::resolved();
    }

    /**
     * @param  array{credentials: array, sender: array, recipient: array}  $data
     */
    private function shipmentPayload(Order $order, array $data, string $shipmentId): array
    {
        $sender = $data['sender'];
        $recipient = $data['recipient'];
        $company = $this->sanitizeName($recipient['company'] ?? '');
        $isBusiness = array_key_exists('is_business', $recipient)
            ? (bool) $recipient['is_business']
            : $company !== '';
        $addition = $recipient['addition'];

        if (! $isBusiness && $company !== '') {
            $addition = trim($addition.' '.$company);
            $company = '';
        }

        $fullName = mb_substr($this->sanitizeName($recipient['first_name'].' '.$recipient['last_name']), 0, 30);

        return [
            'shipmentId' => $shipmentId,
            'orderReference' => (string) $order->number,
            'receiver' => [
                'name' => [
                    'firstName' => $fullName,
                    'lastName' => '',
                    'companyName' => $company,
                ],
                'address' => [
                    'countryCode' => strtoupper($recipient['country_code']),
                    'postalCode' => strtoupper(str_replace(' ', '', $recipient['postal_code'])),
                    'city' => $recipient['city'],
                    'street' => $recipient['street'],
                    'number' => $recipient['house_number'],
                    'addition' => $addition,
                    'isBusiness' => $isBusiness,
                ],
                'email' => $recipient['email'],
                'phoneNumber' => $recipient['phone'] ?? '',
            ],
            'shipper' => [
                'email' => $sender['email'],
                'phoneNumber' => $sender['phone'] ?? '',
                'name' => [
                    'firstName' => $sender['first_name'] ?? '',
                    'lastName' => $sender['last_name'] ?? '',
                    'companyName' => $sender['company'],
                ],
                'address' => [
                    'countryCode' => strtoupper($sender['country_code']),
                    'postalCode' => strtoupper(str_replace(' ', '', $sender['postal_code'])),
                    'city' => $sender['city'],
                    'street' => $sender['street'],
                    'number' => $sender['house_number'],
                    'addition' => $sender['house_number_addition'] ?? '',
                    'isBusiness' => true,
                ],
                'vatNumber' => $sender['vat_number'] ?? '',
                'eoriNumber' => $sender['eori_number'] ?? '',
            ],
            'accountId' => $data['credentials']['account_id'],
            'product' => $data['shipment']['shipping_method'],
            'options' => [[
                'key' => 'REFERENCE',
                'input' => Str::limit((string) $order->number, 35, ''),
            ]],
            'returnLabel' => false,
            'pieces' => [[
                'parcelType' => $data['shipment']['parcel_type'],
                'quantity' => 1,
                'weight' => $data['shipment']['weight'],
                'dimensions' => [
                    'length' => $data['shipment']['length'],
                    'width' => $data['shipment']['width'],
                    'height' => $data['shipment']['height'],
                ],
            ]],
        ];
    }

    /**
     * @return array{string, string, string}
     */
    private function splitAddress(string $address, string $address2): array
    {
        if (preg_match('/^(.+?)\s+(\d+[A-Za-z]?)(?:[-\s\/]\s*(.+))?$/u', trim($address), $matches) !== 1) {
            return [trim($address), '', trim($address2)];
        }

        return [
            trim($matches[1]),
            trim($matches[2]),
            trim((string) ($matches[3] ?? $address2)),
        ];
    }

    private function sanitizeName(string $value): string
    {
        $value = preg_replace('/[\/\\\\@&#$%*+=<>_\[\]\{\}\(\)\|~^`"\'!?]/u', '', trim($value));
        $value = preg_replace('/[\p{C}\p{So}\x{1F600}-\x{1F64F}]/u', '', (string) $value);

        return trim((string) preg_replace('/[^\p{L}\p{N}\s\.\-]/u', '', (string) $value));
    }
}
