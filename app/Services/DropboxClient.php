<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DropboxClient
{
    private ?string $accessToken = null;

    public function put(string $folder, string $filename, string $contents): string
    {
        Validator::make([
            'folder' => $folder,
            'filename' => $filename,
            'contents' => $contents,
        ], [
            'folder' => ['required', 'string', 'not_regex:/[\/\\\\]/'],
            'filename' => ['required', 'string', 'not_regex:/[\/\\\\]/'],
            'contents' => ['required', 'string'],
        ])->validate();

        $this->ensureFolder($folder);

        $path = "/{$folder}/{$filename}";
        $response = $this->request()
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'path' => $path,
                    'mode' => 'overwrite',
                    'autorename' => false,
                    'mute' => true,
                ], JSON_THROW_ON_ERROR),
            ])
            ->withBody($contents, 'application/octet-stream')
            ->post(rtrim((string) config('services.dropbox.content_url'), '/').'/2/files/upload')
            ->throw();

        return (string) ($response->json('path_display') ?: $path);
    }

    public function get(string $path): string
    {
        Validator::make(['path' => $path], [
            'path' => ['required', 'string', 'starts_with:/Labels Arthur/', 'ends_with:.pdf'],
        ])->validate();

        $contents = $this->request()
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode(['path' => $path], JSON_THROW_ON_ERROR),
            ])
            ->post(rtrim((string) config('services.dropbox.content_url'), '/').'/2/files/download')
            ->throw()
            ->body();

        if (! str_starts_with($contents, '%PDF')) {
            throw new RuntimeException('Dropbox returned invalid shipping-label PDF data.');
        }

        return $contents;
    }

    private function ensureFolder(string $folder): void
    {
        $response = $this->request()
            ->asJson()
            ->post(rtrim((string) config('services.dropbox.api_url'), '/').'/2/files/create_folder_v2', [
                'path' => "/{$folder}",
                'autorename' => false,
            ]);

        if ($response->successful() || $this->isExistingFolder($response)) {
            return;
        }

        $response->throw();
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->resolveToken())
            ->acceptJson()
            ->connectTimeout((int) config('services.dropbox.connect_timeout', 2))
            ->timeout((int) config('services.dropbox.timeout', 3));
    }

    private function resolveToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $appKey = (string) config('services.dropbox.app_key');
        $appSecret = (string) config('services.dropbox.app_secret');
        $refreshToken = (string) config('services.dropbox.refresh_token');

        if ($appKey !== '' && $appSecret !== '' && $refreshToken !== '') {
            $response = Http::withBasicAuth($appKey, $appSecret)
                ->asForm()
                ->connectTimeout((int) config('services.dropbox.connect_timeout', 2))
                ->timeout((int) config('services.dropbox.timeout', 3))
                ->post((string) config('services.dropbox.oauth_url'), [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ])
                ->throw();

            $accessToken = $response->json('access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Dropbox returned no access token.');
            }

            return $this->accessToken = $accessToken;
        }

        $accessToken = (string) config('services.dropbox.authorization_token');

        if ($accessToken === '') {
            throw new RuntimeException('Dropbox credentials are not configured.');
        }

        return $this->accessToken = $accessToken;
    }

    private function isExistingFolder(Response $response): bool
    {
        return $response->status() === 409
            && str_starts_with((string) $response->json('error_summary'), 'path/conflict/folder');
    }
}
