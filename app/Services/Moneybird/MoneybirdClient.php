<?php

namespace App\Services\Moneybird;

use App\Exceptions\MoneybirdException;
use App\Models\MoneybirdSetting;
use App\Models\Order;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MoneybirdClient
{
    public function __construct(private MoneybirdInvoicePayloadBuilder $payloadBuilder) {}

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $response = $this->formRequest()->post((string) config('services.zeker_gemak_moneybird.token_url'), [
            'client_id' => config('services.zeker_gemak_moneybird.client_id'),
            'client_secret' => config('services.zeker_gemak_moneybird.client_secret'),
            'redirect_uri' => config('services.zeker_gemak_moneybird.redirect_uri'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        $this->ensureSuccessful($response, 'Moneybird authorization failed.');

        return (array) $response->json();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function administrations(?string $accessToken = null): array
    {
        $response = $this->request($accessToken)->get('/administrations.json');
        $this->ensureSuccessful($response, 'Could not load Moneybird administrations.');

        return $this->options((array) $response->json());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function workflows(): array
    {
        return $this->administrationOptions('/workflows.json');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function documentStyles(): array
    {
        return $this->administrationOptions('/document_styles.json');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function ledgerAccounts(): array
    {
        return $this->administrationOptions('/ledger_accounts.json');
    }

    public function createInvoice(Order $order): Order
    {
        try {
            return Cache::lock("moneybird-invoice:{$order->id}", 30)->block(3, function () use ($order): Order {
                $order->refresh();

                if (filled($order->moneybird_invoice_id)) {
                    return $order;
                }

                if (! in_array($order->status->value(), ['processing', 'shipped', 'completed'], true)) {
                    throw new MoneybirdException(__('Only paid orders can be invoiced.'));
                }

                $settings = MoneybirdSetting::resolved();

                if (! $settings['connected'] || blank($settings['administration_id'])) {
                    throw new MoneybirdException(__('Moneybird is not fully configured.'));
                }

                $order->loadMissing(['items', 'billpayer.address']);

                if (! $order->billpayer) {
                    throw new MoneybirdException(__('The order has no billing customer.'));
                }

                $contactId = $this->findOrCreateContact($order);
                $payload = $this->payloadBuilder->build($order);
                $payload['sales_invoice']['contact_id'] = $contactId;
                $response = $this->administrationRequest()->post('/sales_invoices.json', $payload);
                $this->ensureSuccessful($response, 'Moneybird could not create the invoice.');
                $invoice = (array) $response->json();

                if (blank($invoice['id'] ?? null)) {
                    throw new MoneybirdException(__('Moneybird created an invoice without returning an ID.'));
                }

                $order->forceFill([
                    'moneybird_invoice_id' => (string) $invoice['id'],
                    'moneybird_invoice_number' => $invoice['invoice_id'] ?? null,
                    'moneybird_invoice_status' => $invoice['state'] ?? 'draft',
                    'moneybird_invoice_url' => $invoice['url']
                        ?? $invoice['payment_url']
                        ?? $this->invoiceUrl((string) $invoice['id']),
                ])->save();

                $sentAt = null;

                if ($this->shouldSendInvoice($order, $settings)) {
                    $sendResponse = $this->administrationRequest()->patch(
                        "/sales_invoices/{$invoice['id']}/send_invoice.json",
                        ['sales_invoice_sending' => ['delivery_method' => 'Email']],
                    );
                    $this->ensureSuccessful($sendResponse, 'The Moneybird invoice was created but could not be sent.');
                    $sentAt = now();
                    $order->moneybird_invoice_status = $sendResponse->json('state')
                        ?? $order->moneybird_invoice_status;
                }

                if ($sentAt) {
                    $order->moneybird_invoice_sent_at = $sentAt;
                    $order->save();
                }

                return $order;
            });
        } catch (LockTimeoutException) {
            throw new MoneybirdException(__('This order is already being invoiced.'));
        } catch (MoneybirdException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Zeker-Gemak Moneybird invoice failed.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            throw new MoneybirdException(__('Moneybird could not create the invoice.'), previous: $exception);
        }
    }

    public function downloadInvoicePdf(string $invoiceId): string
    {
        try {
            $response = $this->administrationRequest()
                ->withoutRedirecting()
                ->get("/sales_invoices/{$invoiceId}/download_pdf.json");

            if ($response->redirect() && filled($response->header('Location'))) {
                $response = Http::accept('application/pdf')
                    ->connectTimeout((int) config('services.zeker_gemak_moneybird.connect_timeout'))
                    ->timeout((int) config('services.zeker_gemak_moneybird.timeout'))
                    ->get((string) $response->header('Location'));
            }

            $this->ensureSuccessful($response, 'Moneybird could not download the invoice.');

            if ($response->body() === '' || ! str_starts_with($response->body(), '%PDF')) {
                throw new MoneybirdException(__('Moneybird returned an invalid invoice PDF.'));
            }

            return $response->body();
        } catch (MoneybirdException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Zeker-Gemak Moneybird invoice download failed.', [
                'invoice_id' => $invoiceId,
                'error' => $exception->getMessage(),
            ]);

            throw new MoneybirdException(__('Moneybird could not download the invoice.'), previous: $exception);
        }
    }

    public function refreshAccessToken(): void
    {
        $setting = MoneybirdSetting::current();
        $configuration = MoneybirdSetting::resolved();

        if (blank($configuration['refresh_token'])) {
            throw new MoneybirdException(__('The Moneybird refresh token is missing.'));
        }

        $response = $this->formRequest()->post((string) config('services.zeker_gemak_moneybird.token_url'), [
            'client_id' => config('services.zeker_gemak_moneybird.client_id'),
            'client_secret' => config('services.zeker_gemak_moneybird.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $configuration['refresh_token'],
        ]);
        $this->ensureSuccessful($response, 'Moneybird token refresh failed.');
        $tokens = (array) $response->json();
        $configuration['access_token'] = $tokens['access_token'] ?? null;
        $configuration['refresh_token'] = $tokens['refresh_token'] ?? $configuration['refresh_token'];
        $configuration['expires_at'] = now()->timestamp + (int) ($tokens['expires_in'] ?? 1200);
        $setting->configuration = $configuration;
        $setting->save();
    }

    private function findOrCreateContact(Order $order): string
    {
        $billpayer = $order->billpayer;
        $response = $this->administrationRequest()->get('/contacts/filter.json', [
            'query' => $billpayer->email,
            'per_page' => 1,
        ]);

        if ($response->successful() && filled($response->json('0.id'))) {
            return (string) $response->json('0.id');
        }

        $address = $billpayer->address;
        $response = $this->administrationRequest()->post('/contacts.json', [
            'contact' => [
                'company_name' => $billpayer->company_name,
                'firstname' => $billpayer->firstname,
                'lastname' => $billpayer->lastname,
                'address1' => $address?->address,
                'address2' => $address?->address2,
                'zipcode' => $address?->postalcode,
                'city' => $address?->city,
                'country' => $address?->country_id ?: 'NL',
                'phone' => $billpayer->phone,
                'email' => $billpayer->email,
                'send_invoices_to_email' => $billpayer->email,
                'delivery_method' => 'Email',
                'customer_id' => (string) ($order->customer_id ?: $billpayer->id),
            ],
        ]);
        $this->ensureSuccessful($response, 'Moneybird could not create the customer.');

        if (blank($response->json('id'))) {
            throw new MoneybirdException(__('Moneybird created a customer without returning an ID.'));
        }

        return (string) $response->json('id');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function administrationOptions(string $path): array
    {
        $response = $this->administrationRequest()->get($path);
        $this->ensureSuccessful($response, 'Could not load Moneybird invoice settings.');

        return $this->options((array) $response->json());
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @return array<int, array{id: string, name: string}>
     */
    private function options(array $values): array
    {
        return array_values(array_map(fn (array $value): array => [
            'id' => (string) ($value['id'] ?? ''),
            'name' => (string) ($value['name'] ?? ''),
        ], $values));
    }

    private function administrationRequest(): PendingRequest
    {
        $configuration = MoneybirdSetting::resolved();

        if ($configuration['expires_at'] && $configuration['expires_at'] <= now()->timestamp) {
            $this->refreshAccessToken();
            $configuration = MoneybirdSetting::resolved();
        }

        return $this->request()->baseUrl(
            rtrim((string) config('services.zeker_gemak_moneybird.api_url'), '/')
            .'/'.trim((string) $configuration['administration_id'], '/'),
        );
    }

    private function request(?string $accessToken = null): PendingRequest
    {
        $configuration = MoneybirdSetting::resolved();

        return Http::baseUrl((string) config('services.zeker_gemak_moneybird.api_url'))
            ->withToken($accessToken ?: (string) $configuration['access_token'])
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.zeker_gemak_moneybird.connect_timeout'))
            ->timeout((int) config('services.zeker_gemak_moneybird.timeout'));
    }

    private function formRequest(): PendingRequest
    {
        return Http::asForm()
            ->acceptJson()
            ->connectTimeout((int) config('services.zeker_gemak_moneybird.connect_timeout'))
            ->timeout((int) config('services.zeker_gemak_moneybird.timeout'));
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if (! $response->successful()) {
            throw new MoneybirdException($message);
        }
    }

    private function invoiceUrl(string $invoiceId): string
    {
        return rtrim((string) config('services.zeker_gemak_moneybird.dashboard_url'), '/')
            .'/'.MoneybirdSetting::resolved()['administration_id']
            .'/sales_invoices/'.$invoiceId;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shouldSendInvoice(Order $order, array $settings): bool
    {
        $status = data_get($order->original_checkout_payload, 'payment_method') === 'invoice'
            ? $settings['invoice_payment_status']
            : $settings['mollie_invoice_status'];

        return filled($status)
            ? $status === 'open'
            : (bool) $settings['auto_send_invoice_email'];
    }
}
