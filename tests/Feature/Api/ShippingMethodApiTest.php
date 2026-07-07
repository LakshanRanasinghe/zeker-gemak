<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Konekt\Address\Models\Zone;
use Vanilo\Shipment\Models\Carrier;
use Vanilo\Shipment\Models\ShippingMethod;

uses(RefreshDatabase::class);

it('returns active shipping methods with active carriers', function (): void {
    $activeCarrier = Carrier::create([
        'name' => 'PostNL',
        'is_active' => true,
    ]);

    $inactiveCarrier = Carrier::create([
        'name' => 'Hidden Carrier',
        'is_active' => false,
    ]);

    ShippingMethod::create([
        'name' => 'Standard Shipping',
        'carrier_id' => $activeCarrier->id,
        'calculator' => 'flat_fee',
        'configuration' => [
            'title' => 'Shipping fee',
            'cost' => 7.95,
            'free_threshold' => 100,
        ],
        'eta_min' => 1,
        'eta_max' => 3,
        'is_active' => true,
    ]);

    ShippingMethod::create([
        'name' => 'Inactive Method',
        'carrier_id' => $activeCarrier->id,
        'calculator' => 'flat_fee',
        'configuration' => ['cost' => 4.95],
        'is_active' => false,
    ]);

    ShippingMethod::create([
        'name' => 'Inactive Carrier Method',
        'carrier_id' => $inactiveCarrier->id,
        'calculator' => 'flat_fee',
        'configuration' => ['cost' => 4.95],
        'is_active' => true,
    ]);

    $this->getJson('/api/shipping-methods')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Standard Shipping')
        ->assertJsonPath('data.0.carrier.name', 'PostNL')
        ->assertJsonPath('data.0.cost', 7.95)
        ->assertJsonPath('data.0.free_threshold', 100)
        ->assertJsonPath('data.0.eta.min', 1)
        ->assertJsonPath('data.0.eta.max', 3);
});

it('filters shipping methods by zone while keeping global methods', function (): void {
    $nlZone = Zone::create(['name' => 'Netherlands']);
    $beZone = Zone::create(['name' => 'Belgium']);

    ShippingMethod::create([
        'name' => 'Global Shipping',
        'calculator' => 'flat_fee',
        'configuration' => ['cost' => 5],
        'is_active' => true,
    ]);

    ShippingMethod::create([
        'name' => 'NL Shipping',
        'zone_id' => $nlZone->id,
        'calculator' => 'flat_fee',
        'configuration' => ['cost' => 7],
        'is_active' => true,
    ]);

    ShippingMethod::create([
        'name' => 'BE Shipping',
        'zone_id' => $beZone->id,
        'calculator' => 'flat_fee',
        'configuration' => ['cost' => 8],
        'is_active' => true,
    ]);

    $this->getJson('/api/shipping-methods?zone_id='.$nlZone->id)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Global Shipping')
        ->assertJsonPath('data.1.name', 'NL Shipping');
});
