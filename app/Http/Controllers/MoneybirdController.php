<?php

namespace App\Http\Controllers;

use App\Exceptions\MoneybirdException;
use App\Models\MoneybirdSetting;
use App\Services\Moneybird\MoneybirdClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MoneybirdController extends Controller
{
    public function connect(): RedirectResponse
    {
        $clientId = config('services.zeker_gemak_moneybird.client_id');
        $redirectUri = config('services.zeker_gemak_moneybird.redirect_uri');

        if (blank($clientId) || blank(config('services.zeker_gemak_moneybird.client_secret')) || blank($redirectUri)) {
            return redirect()->route('moneybird.settings')
                ->with('error', __('Moneybird OAuth credentials are incomplete.'));
        }

        $state = Str::random(40);
        session()->put('zeker_gemak_moneybird.oauth_state', $state);

        return redirect()->away(config('services.zeker_gemak_moneybird.authorize_url').'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('services.zeker_gemak_moneybird.scopes')),
            'state' => $state,
        ]));
    }

    public function callback(Request $request, MoneybirdClient $client): RedirectResponse
    {
        $expectedState = (string) session()->pull('zeker_gemak_moneybird.oauth_state');
        $state = $request->string('state')->toString();

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()->route('moneybird.settings')
                ->with('error', __('Invalid Moneybird connection state.'));
        }

        if ($request->missing('code')) {
            return redirect()->route('moneybird.settings')
                ->with('error', __('No Moneybird authorization code was received.'));
        }

        $validated = $request->validate(['code' => ['required', 'string', 'max:2048']]);

        try {
            $tokens = $client->exchangeAuthorizationCode($validated['code']);
            $administrations = $client->administrations($tokens['access_token'] ?? null);
            $setting = MoneybirdSetting::current();
            $setting->configuration = array_replace(MoneybirdSetting::resolved(), [
                'connected' => filled($tokens['access_token'] ?? null),
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => now()->timestamp + (int) ($tokens['expires_in'] ?? 1200),
                'administration_id' => count($administrations) === 1 ? $administrations[0]['id'] : null,
            ]);
            $setting->save();

            return redirect()->route('moneybird.settings')
                ->with('success', __('Moneybird connected. Select an administration to finish setup.'));
        } catch (\Throwable $exception) {
            Log::warning('Zeker-Gemak Moneybird OAuth failed.', ['error' => $exception->getMessage()]);

            return redirect()->route('moneybird.settings')
                ->with('error', $exception instanceof MoneybirdException
                    ? $exception->getMessage()
                    : __('Failed to connect Moneybird. Please try again.'));
        }
    }
}
