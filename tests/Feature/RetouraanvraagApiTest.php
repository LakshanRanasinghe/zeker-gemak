<?php

use App\Mail\RetouraanvraagAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

test('retouraanvraag sends an admin notification and returns an rma number', function () {
    Mail::fake();
    config(['app.admin_emails' => ['admin@example.com']]);

    $file = UploadedFile::fake()->image('defect.jpg');

    $response = $this->post('/api/retouraanvraag', [
        'name' => 'Jan Jansen',
        'email' => 'jan@example.com',
        'phone' => '0612345678',
        'organisation' => 'Jansen BV',
        'generalReasons' => ['Product is defect', 'Verkeerd besteld'],
        'subject' => 'Retour printerlabels',
        'message' => 'Graag retour verwerken.',
        'naam1' => 'Epson Label',
        'artikelnummer1' => 'SKU-123',
        'aantal1' => '2',
        'factuurnummer1' => 'INV-10042',
        'factuurdatum1' => '2026-07-30',
        'probleem1' => 'De rol is beschadigd geleverd.',
        'reden1' => ['Product is defect'],
        'toelichting1' => 'Foto toegevoegd.',
        'file' => $file,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Retouraanvraag succesvol ontvangen.')
        ->assertJsonStructure(['rma_number']);

    expect($response->json('rma_number'))->toMatch('/^RMA-2026-[A-Z0-9]{5}$/');

    Mail::assertSent(RetouraanvraagAdmin::class, function (RetouraanvraagAdmin $mail) {
        expect($mail->data['name'])->toBe('Jan Jansen')
            ->and($mail->data['factuurnummer1'])->toBe('INV-10042')
            ->and($mail->file?->getClientOriginalName())->toBe('defect.jpg')
            ->and($mail->envelope()->subject)->toBe('[Retouraanvraag] Nieuwe retouraanvraag van Jan Jansen - Factuur #INV-10042');

        return $mail->hasTo('admin@example.com');
    });
});

test('retouraanvraag requires a valid name and email', function () {
    Mail::fake();

    $this->postJson('/api/retouraanvraag', [
        'name' => '',
        'email' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'De opgegeven gegevens zijn ongeldig.')
        ->assertJsonValidationErrors(['name', 'email']);

    Mail::assertNothingSent();
});
