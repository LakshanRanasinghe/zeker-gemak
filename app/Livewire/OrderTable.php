<?php

namespace App\Livewire;

use App\Exceptions\ShippingDocumentException;
use App\Services\DropboxClient;
use App\Services\GenerateDhlShippingDocuments;
use App\Services\OrderExportService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Vanilo\Order\Contracts\Order;
use Vanilo\Order\Models\OrderProxy;

final class OrderTable extends PowerGridComponent
{
    public string $tableName = 'orders';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public ?int $orderToShipId = null;

    public ?int $labelOrderId = null;

    public array $labelRecipient = [];

    public string $labelCarrier = 'DHL-PARCEL';

    public string $labelParcelType = 'SMALL';

    public string $labelShippingMethod = 'DFY-B2C';

    public string $bulkStatus = '';

    public string $statusFilter = '';

    public string $exportFilter = '';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->includeViewOnTop('livewire.order-table-header'),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
            PowerGrid::footer()
                ->includeViewOnBottom('livewire.order-table-modals'),
        ];
    }

    public function datasource(): Builder
    {
        return OrderProxy::query()
            ->with(['billpayer', 'user', 'adjustmentsRelation'])
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->exportFilter === 'exported', fn ($query) => $query->where('xml_exported', true))
            ->when($this->exportFilter === 'unexported', fn ($query) => $query->where('xml_exported', false));
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('number')
            ->add('status_label', fn ($row) => ucfirst($row->status->value()))
            ->add('customer', function ($row) {
                if ($row->billpayer) {
                    return $row->billpayer->getName();
                }

                return $row->user ? $row->user->name : '-';
            })
            ->add('formatted_total', fn ($row) => config('app.currency_symbol', '$').number_format((float) $row->total(), 2))
            ->add('xml_exported_label', fn ($row) => $row->xml_exported ? __('Yes') : __('No'))
            ->add('debitor_no', fn ($row) => $row->user?->debitor_no ?? '-')
            ->add('created_at', fn ($row) => $row->created_at instanceof Carbon ? $row->created_at->format('d/m/Y H:i:s') : $row->created_at);
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->searchable()
                ->sortable(),

            Column::make(__('Number'), 'number')
                ->sortable()
                ->searchable(),

            Column::make(__('Status'), 'status_label', 'status')
                ->sortable()
                ->searchable(),

            Column::make(__('Customer'), 'customer'),

            Column::make(__('Total'), 'formatted_total'),

            Column::make(__('Exported'), 'xml_exported_label', 'xml_exported')
                ->sortable(),

            Column::make(__('Debitor #'), 'debitor_no'),

            Column::make(__('Created at'), 'created_at')
                ->sortable(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
        ];
    }

    public function header(): array
    {
        $buttons = [];

        if ($this->hasActiveFilters()) {
            $buttons[] = Button::add('clear-filters')
                ->slot(__('Clear Filters'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "\$wire.clearAllFilters(); \$wire.set('search', '');",
                ]);
        }

        $buttons[] = Button::add('bulk-delete')
            ->slot(__('Bulk Delete'))
            ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
            ->attributes([
                'x-data' => '',
                'x-show' => "\$store.pgBulkActions.count('{$this->tableName}') > 0",
                'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected orders?')."')) { \$wire.bulkDelete() }",
            ]);

        $buttons[] = Button::add('bulk-export-xml')
            ->slot(__('Export XML'))
            ->class('px-2 py-1 bg-zinc-900 dark:bg-zinc-100 border border-transparent rounded-md text-sm shadow-sm hover:bg-zinc-800 dark:hover:bg-zinc-200 text-white dark:text-zinc-900 transition-colors')
            ->attributes([
                'x-data' => '',
                'x-show' => "\$store.pgBulkActions.count('{$this->tableName}') > 0",
                'x-on:click' => "\$wire.dispatch('bulkExportXml')",
            ]);

        $buttons[] = Button::add('bulk-change-status')
            ->slot(__('Change Status'))
            ->class('px-2 py-1 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
            ->attributes([
                'x-data' => '',
                'x-show' => "\$store.pgBulkActions.count('{$this->tableName}') > 0",
                'x-on:click' => "\$wire.set('bulkStatus', ''); \$flux.modal('bulk-status-modal').show()",
            ]);

        return $buttons;
    }

    protected function hasActiveFilters(): bool
    {
        if (filled($this->search)) {
            return true;
        }

        foreach ($this->filters as $filter) {
            if (is_array($filter)) {
                foreach ($filter as $value) {
                    if (filled($value)) {
                        return true;
                    }
                }
            } elseif (filled($filter)) {
                return true;
            }
        }

        return false;
    }

    public function actions(Order $row): array
    {
        $labelPath = data_get($row->dhl_data, 'label_path');

        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->route('orders.edit', ['order' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteOrder', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this order? This action cannot be undone.')),

            Button::add('export-xml')
                ->slot(__('XML'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->dispatch('exportXml', ['id' => $row->id])
                ->can(! $row->xml_exported),

            Button::add('ship')
                ->slot(__('Ship'))
                ->class('px-2 py-1 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md text-sm shadow-sm hover:bg-green-100 dark:hover:bg-green-900/40 text-green-600 dark:text-green-400 transition-colors')
                ->call('confirmShipOrder', [$row->id])
                ->can($row->status->value() !== 'shipped' && $row->status->value() !== 'completed' && $row->status->value() !== 'cancelled'),

            Button::add('generate-shipping-label')
                ->slot(__('Generate shipping label'))
                ->class('px-2 py-1 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md text-sm shadow-sm hover:bg-yellow-100 dark:hover:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300 transition-colors')
                ->call('openShippingLabelModal', [$row->id])
                ->can($row->status->value() !== 'shipped' && $row->status->value() !== 'completed' && $row->status->value() !== 'cancelled'),

            Button::add('download-shipping-label')
                ->slot(__('Download label'))
                ->class('px-2 py-1 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md text-sm shadow-sm hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 transition-colors')
                ->call('downloadShippingLabel', [$row->id])
                ->can(is_string($labelPath)
                    && str_starts_with($labelPath, '/Labels Arthur/')
                    && str_ends_with($labelPath, '.pdf')),
        ];
    }

    public function openShippingLabelModal(int|array $id): void
    {
        if (is_array($id)) {
            $id = $id[0] ?? $id['id'] ?? null;
        }

        $orderClass = OrderProxy::modelClass();
        $order = $orderClass::with(['billpayer.address', 'shippingAddress'])->findOrFail((int) $id);
        $address = $order->shippingAddress ?? $order->billpayer?->address;
        [$street, $houseNumber, $addition] = $this->splitAddress(
            (string) $address?->address,
            (string) $address?->address2,
        );

        $this->resetValidation();
        $this->labelOrderId = $order->id;
        $this->labelRecipient = [
            'first_name' => (string) ($address?->firstname ?: $order->billpayer?->firstname),
            'last_name' => (string) ($address?->lastname ?: $order->billpayer?->lastname),
            'company' => (string) ($address?->company_name ?: $order->billpayer?->company_name),
            'is_business' => false,
            'email' => (string) $order->billpayer?->email,
            'phone' => (string) ($address?->phone ?: $order->billpayer?->phone),
            'street' => $street,
            'house_number' => $houseNumber,
            'addition' => $addition,
            'postal_code' => (string) $address?->postalcode,
            'city' => (string) $address?->city,
            'country_code' => strtoupper((string) $address?->country_id),
        ];
        $this->labelCarrier = 'DHL-PARCEL';
        $this->labelParcelType = 'SMALL';
        $this->labelShippingMethod = 'DFY-B2C';

        Flux::modal('shipping-label-modal')->show();
    }

    public function updated(string $propertyName): void
    {
        if ($propertyName === 'labelRecipient.is_business' && $this->labelCarrier === 'DHL-PARCEL') {
            $this->labelShippingMethod = $this->labelRecipient['is_business'] ? 'EPL' : 'DFY-B2C';
        }
    }

    public function generateShippingLabel(): void
    {
        $validated = $this->validate([
            'labelOrderId' => ['required', 'integer'],
            'labelRecipient.first_name' => ['required', 'string', 'max:30'],
            'labelRecipient.last_name' => ['required', 'string', 'max:30'],
            'labelRecipient.company' => ['nullable', 'string', 'max:35'],
            'labelRecipient.is_business' => ['required', 'boolean'],
            'labelRecipient.email' => ['required', 'email', 'max:80'],
            'labelRecipient.phone' => ['nullable', 'string', 'max:25'],
            'labelRecipient.street' => ['required', 'string', 'max:40'],
            'labelRecipient.house_number' => ['required', 'string', 'max:10'],
            'labelRecipient.addition' => ['nullable', 'string', 'max:10'],
            'labelRecipient.postal_code' => ['required', 'string', 'max:12'],
            'labelRecipient.city' => ['required', 'string', 'max:30'],
            'labelRecipient.country_code' => ['required', 'string', 'size:2'],
            'labelCarrier' => ['required', Rule::in(['DHL-PARCEL', 'DHL-EXPRESS'])],
            'labelParcelType' => ['required', 'string', 'max:50'],
            'labelShippingMethod' => ['required', 'string', 'max:50'],
        ]);

        try {
            $orderClass = OrderProxy::modelClass();
            $order = $orderClass::findOrFail($validated['labelOrderId']);

            app(GenerateDhlShippingDocuments::class)->handle($order, [
                'recipient' => $validated['labelRecipient'],
                'carrier' => $validated['labelCarrier'],
                'parcel_type' => $validated['labelParcelType'],
                'shipping_method' => $validated['labelShippingMethod'],
            ]);
        } catch (ShippingDocumentException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');

            return;
        }

        Flux::modal('shipping-label-modal')->close();
        $this->labelOrderId = null;

        Flux::toast(__('Shipping label generated and the order was marked as shipped.'), variant: 'success');
    }

    public function downloadShippingLabel(int|array $id): ?StreamedResponse
    {
        if (is_array($id)) {
            $id = $id[0] ?? $id['id'] ?? null;
        }

        $orderClass = OrderProxy::modelClass();
        $order = $orderClass::findOrFail((int) $id);
        $labelPath = (string) data_get($order->dhl_data, 'label_path');

        if ($labelPath === '') {
            Flux::toast(__('No generated shipping label was found for this order.'), variant: 'warning');

            return null;
        }

        try {
            $pdf = app(DropboxClient::class)->get($labelPath);
        } catch (Throwable $exception) {
            Log::error('Zeker Gemak DHL label download failed', [
                'order_id' => $order->id,
                'label_path' => $labelPath,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            Flux::toast(__('The shipping label could not be downloaded from Dropbox.'), variant: 'danger');

            return null;
        }

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $order->number);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, "DHL-label-{$safeNumber}.pdf", [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one order.'), variant: 'warning');

            return;
        }

        foreach ($ids as $id) {
            $this->deleteOrder((int) $id);
        }

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected orders deleted successfully.'), variant: 'success');
    }

    public function bulkChangeStatus(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one order.'), variant: 'warning');

            return;
        }

        if (empty($this->bulkStatus)) {
            Flux::toast(__('Please select a status.'), variant: 'warning');

            return;
        }

        $orderClass = OrderProxy::modelClass();
        $orderClass::whereIn('id', $ids)
            ->get()
            ->each(fn (Model $order) => $this->updateOrderStatus($order, $this->bulkStatus));

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->bulkStatus = '';
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::modal('bulk-status-modal')->close();
        Flux::toast(__('Status updated for selected orders.'), variant: 'success');
    }

    public function confirmShipOrder(int|array $id): void
    {
        if (is_array($id)) {
            $id = $id[0] ?? $id['id'] ?? null;
        }

        $this->orderToShipId = (int) $id;

        Flux::modal('ship-order-modal')->show();
    }

    #[On('shipOrder')]
    public function shipOrder(int $id): void
    {
        $orderClass = OrderProxy::modelClass();
        $order = $orderClass::findOrFail($id);

        $this->updateOrderStatus($order, 'shipped');

        Flux::modal('ship-order-modal')->close();
        $this->orderToShipId = null;

        Flux::toast(__('Order marked as shipped.'), variant: 'success');
    }

    #[On('deleteOrder')]
    public function deleteOrder(int $id): void
    {
        $orderClass = OrderProxy::modelClass();
        $order = $orderClass::with([
            'billpayer',
            'shippingAddress',
            'payments.history',
            'shipments',
            'items.shipments',
        ])->findOrFail($id);

        DB::transaction(function () use ($order) {
            $billpayer = $order->billpayer;
            $billpayerId = $billpayer?->id;
            $billingAddressId = $billpayer?->address_id;
            $shippingAddressId = $order->shipping_address_id;
            $shipments = $order->shipments
                ->merge($order->items->flatMap(fn ($item) => $item->shipments))
                ->unique('id')
                ->values();
            $shipmentAddressIds = collect();

            $order->payments->each(function ($payment) {
                $payment->history()->delete();
                $payment->delete();
            });

            $order->adjustments()->clear();

            $order->items->each(function ($item) {
                $item->adjustments()->clear();
                $item->shipments()->detach();
            });

            $order->shipments()->detach();

            if ($shipments->isNotEmpty()) {
                $shipmentIds = $shipments->pluck('id')->unique()->values();
                $remainingShipmentIds = DB::table('shippables')
                    ->whereIn('shipment_id', $shipmentIds)
                    ->pluck('shipment_id')
                    ->unique();

                $orphanShipmentIds = $shipmentIds->diff($remainingShipmentIds)->values();

                if ($orphanShipmentIds->isNotEmpty()) {
                    $shipmentAddressIds = $shipments
                        ->filter(fn ($shipment) => $orphanShipmentIds->contains($shipment->getKey()))
                        ->pluck('address_id')
                        ->filter()
                        ->unique()
                        ->values();

                    DB::table('shipments')
                        ->whereIn('id', $orphanShipmentIds)
                        ->delete();
                }
            }

            $order->delete();

            if ($billpayerId && ! DB::table('orders')->where('billpayer_id', $billpayerId)->exists()) {
                $billpayer?->delete();
            } else {
                $billingAddressId = null;
            }

            collect([$billingAddressId, $shippingAddressId])
                ->merge($shipmentAddressIds)
                ->filter()
                ->unique()
                ->each(fn ($addressId) => $this->deleteAddressIfUnused((int) $addressId));
        });

        Flux::toast(__('Order deleted successfully.'), variant: 'success');
    }

    private function deleteAddressIfUnused(int $addressId): void
    {
        $isStillUsedByOrder = DB::table('orders')->where('shipping_address_id', $addressId)->exists();
        $isStillUsedByBillpayer = DB::table('billpayers')->where('address_id', $addressId)->exists();
        $isStillUsedByShipment = DB::table('shipments')->where('address_id', $addressId)->exists();

        if ($isStillUsedByOrder || $isStillUsedByBillpayer || $isStillUsedByShipment) {
            return;
        }

        DB::table('addresses')->where('id', $addressId)->delete();
    }

    #[On('exportXml')]
    public function exportXml(int $id)
    {
        $order = OrderProxy::with(['user', 'items.product', 'shippingAddress', 'billpayer.address'])->findOrFail($id);

        if (! $order->user || empty($order->user->debitor_no)) {
            Flux::toast(__('Can not proceed. Debitor number needs to be filled first'), variant: 'danger');

            return;
        }

        $service = new OrderExportService;
        $xml = $service->toKingXml($order);

        $order->xml_exported = true;
        $order->xml_exported_at = Carbon::now();
        $this->updateOrderStatus($order, 'shipped');

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, "order_{$order->number}.xml", [
            'Content-Type' => 'application/xml',
        ]);
    }

    #[On('bulkExportXml')]
    public function bulkExportXml()
    {
        $ids = $this->checkboxValues;
        if (empty($ids)) {
            Flux::toast(__('Please select at least one order.'), variant: 'warning');

            return;
        }

        $ordersQuery = OrderProxy::with(['user', 'items.product', 'shippingAddress', 'billpayer.address'])->whereIn('id', $ids)->where('xml_exported', false);
        $orders = $ordersQuery->get();

        $missingDebitor = [];
        foreach ($orders as $order) {
            if (! $order->user || empty($order->user->debitor_no)) {
                $missingDebitor[] = $order->number;
            }
        }

        if (! empty($missingDebitor)) {
            $message = count($missingDebitor) > 1
                ? __('Orders :numbers: Can not proceed. Debitor number needs to be filled first', ['numbers' => implode(', ', $missingDebitor)])
                : __('Order :number: Can not proceed. Debitor number needs to be filled first', ['number' => $missingDebitor[0]]);

            Flux::toast($message, variant: 'danger');

            return;
        }

        $service = new OrderExportService;
        $xml = $service->toKingXml($orders);

        $orders->each(function (Model $order): void {
            $order->xml_exported = true;
            $order->xml_exported_at = Carbon::now();

            $this->updateOrderStatus($order, 'shipped');
        });

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, 'orders_export.xml', [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function updatedExportFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    private function updateOrderStatus(Model $order, string $status): void
    {
        $order->status = $status;
        $order->save();
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
}
