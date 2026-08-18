<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmService
{
    public function sendToTokens(array $tokens, string $title, string $body, ?string $image = null): array
    {
        $tokens = array_values(array_unique(array_filter($tokens, fn ($token) => is_string($token) && $token !== '')));
        $responses = [];

        foreach ($tokens as $token) {
            $responses[] = $this->sendMessage([
                'token' => $token,
                'notification' => $this->notificationPayload($title, $body, $image),
                'android' => ['priority' => 'high'],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ]);
        }

        return $responses;
    }

    public function sendToTopic(string $topic, string $title, string $body, ?string $image = null): array
    {
        $topic = trim(str_replace('/topics/', '', $topic), '/');

        return $this->sendMessage([
            'topic' => $topic,
            'notification' => $this->notificationPayload($title, $body, $image),
            'android' => ['priority' => 'high'],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function sendMessage(array $message): array
    {
        $projectId = (string) config('firebase.project_id');

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => $message,
            ]);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function notificationPayload(string $title, string $body, ?string $image): array
    {
        $payload = [
            'title' => $title,
            'body' => $body,
        ];

        if (is_string($image) && $image !== '') {
            $payload['image'] = $image;
        }

        return $payload;
    }

    protected function accessToken(): string
    {
        return Cache::remember('firebase_fcm_access_token', 50 * 60, function () {
            $credentials = $this->credentials();
            $now = time();

            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ], JSON_THROW_ON_ERROR));

            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            $unsigned = $header . '.' . $claims;
            $signature = '';

            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign Firebase service-account JWT.');
            }

            $jwt = $unsigned . '.' . $this->base64UrlEncode($signature);

            $response = Http::asForm()->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Unable to obtain Firebase access token.');
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri?: string, project_id?: string}
     */
    protected function credentials(): array
    {
        $path = (string) config('firebase.credentials');

        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            throw new RuntimeException('Firebase service-account file is missing. Copy it to storage/app/firebase/service-account.json');
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            throw new RuntimeException('Firebase service-account file is invalid.');
        }

        return $decoded;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
