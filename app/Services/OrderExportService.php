<?php

namespace App\Services;

use App\Models\GroupProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SimpleXMLElement;
use Vanilo\Order\Models\Order;

class OrderExportService
{
    /**
     * Export one or more orders to King XML format.
     *
     * @param  Order|Collection  $orders
     */
    public function toKingXml($orders): string
    {
        if ($orders instanceof Order) {
            $orders = collect([$orders]);
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><KING_ORDERS/>');

        $ordersContainer = $xml->addChild('ORDERS');

        foreach ($orders as $order) {
            $orderNode = $ordersContainer->addChild('ORDER');
            $kop = $orderNode->addChild('ORDERKOP');

            $kop->addChild('ORK_ORDERNUMMER', $order->number);

            // Attempt to find a debtor number.
            $debtorNumber = $order->user?->debitor_no ?? '0';
            $kop->addChild('ORK_DEBITEURNUMMER', $debtorNumber);

            $kop->addChild('ORK_REFERENTIE', $order->number);
            $orderDate = $order->created_at;
            if (! $orderDate instanceof Carbon) {
                try {
                    $orderDate = Carbon::parse(str_replace('/', '-', $orderDate));
                } catch (\Exception $e) {
                    $orderDate = Carbon::now();
                }
            }
            $kop->addChild('ORK_ORDERDATUM', $orderDate->format('Y-m-d'));

            $shippingAddress = $order->shippingAddress;
            $billpayer = $order->billpayer;

            // Use shipping address if available, otherwise billing address
            $address = $shippingAddress ?: ($billpayer ? $billpayer->address : null);

            $verzendAdres = $kop->addChild('ORK_VERZENDADRES');

            if ($address) {
                $naam1 = $address->company_name ?? ($billpayer->company_name ?? '');
                $naam2 = $address->name ?? ($address->firstname.' '.$address->lastname);

                if (empty($naam1)) {
                    $naam1 = $naam2;
                    $naam2 = '';
                }

                $verzendAdres->addChild('ADR_NAAM1', $naam1);
                $verzendAdres->addChild('ADR_NAAM2', $naam2);

                $fullAddress = $address->address ?? '';
                // Simple regex to split street and house number (common in NL: "Streetname 123")
                preg_match('/^(.+)\s(\d+.*)$/', $fullAddress, $matches);
                $street = $matches[1] ?? $fullAddress;
                $houseNumber = $matches[2] ?? '';

                $verzendAdres->addChild('ADR_STRAAT', $street);
                $verzendAdres->addChild('ADR_HUISNUMMER', $houseNumber);
                $verzendAdres->addChild('ADR_POSTCODE', $address->postalcode ?? '');
                $verzendAdres->addChild('ADR_WOONPLAATS', $address->city ?? '');
                $verzendAdres->addChild('ADR_LAND', $address->country_id ?? 'NL');
                $verzendAdres->addChild('ADR_EMAIL', $address->email ?? ($order->user->email ?? ''));
                $verzendAdres->addChild('ADR_TELEFOON', $address->phone ?? '');
            } else {
                // Fallback for missing address
                $verzendAdres->addChild('ADR_NAAM1', $order->user->name ?? 'Unknown');
                $verzendAdres->addChild('ADR_NAAM2', '');
                $verzendAdres->addChild('ADR_STRAAT', '');
                $verzendAdres->addChild('ADR_HUISNUMMER', '');
                $verzendAdres->addChild('ADR_POSTCODE', '');
                $verzendAdres->addChild('ADR_WOONPLAATS', '');
                $verzendAdres->addChild('ADR_LAND', 'NL');
                $verzendAdres->addChild('ADR_EMAIL', $order->user->email ?? '');
                $verzendAdres->addChild('ADR_TELEFOON', '');
            }

            $kop->addChild('ORK_GOEDGEKEURD', 'true');
            $kop->addChild('ORK_VRIJVOORVERZAMELLIJST', 'false');

            $regelsNode = $orderNode->addChild('ORDERREGELS');
            foreach ($order->items as $index => $item) {
                $regel = $regelsNode->addChild('ORDERREGEL');
                $regel->addChild('ORR_REGELNUMMER', $index + 1);

                // Get SKU or article number
                $articleNumber = '';
                if ($item->product) {
                    $articleNumber = $item->product->article_number ?? $item->product->sku ?? '';
                }
                if ($item->source_group_product_id) {
                    $groupProduct = GroupProduct::find($item->source_group_product_id);
                    if ($groupProduct) {
                        $regel->addChild('ORR_TEKSTOPFACTUUR', $groupProduct->title);
                        $regel->addChild('ORR_DISCOUNT', $groupProduct->discount);
                    }
                }
                $regel->addChild('ORR_ARTIKELNUMMER', $articleNumber);
                $regel->addChild('ORR_AANTALBESTELD', (int) $item->quantity);
                $regel->addChild('ORR_PRIJS', number_format((float) $item->price, 2, '.', ''));
                $regel->addChild('ORR_KORTINGSPERCENTAGE1', '0');
                $regel->addChild('ORR_KORTINGSPERCENTAGE2', '0');
                $regel->addChild('ORR_BTWCODE', '1');
            }
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->encoding = 'UTF-8';
        $dom->formatOutput = true;

        // Add the custom comment after the XML declaration
        $comment = $dom->createComment(' Generated by King Export ');
        $dom->insertBefore($comment, $dom->documentElement);

        return $dom->saveXML();
    }
}
