<?php

namespace App\Services;

use App\Exceptions\RegistrationApiException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the student-registration integration API. Read-only: it fetches the
 * paid students and their class allocations. Authentication is a shared bearer
 * token.
 */
class RegistrationClient
{
    /**
     * The integration delta. Pass the last-synced high-water mark as $since to
     * get only what changed since then; omit it for a full pull. Returns:
     *   ['last_changed_at' => ?string, 'count' => int, 'students' => array]
     * where each student is student_number, first_name, last_name,
     * allocated_dhamma_class, allocated_sinhala_class.
     *
     * @throws RegistrationApiException
     */
    public function changes(?string $since = null): array
    {
        $url = (string) config('integration.registration_url');
        $token = (string) config('integration.registration_token');

        if ($url === '' || $token === '') {
            throw new RegistrationApiException(
                'The registration integration is not configured. Set REGISTRATION_API_URL and REGISTRATION_API_TOKEN.'
            );
        }

        try {
            $response = Http::baseUrl(rtrim($url, '/'))
                ->withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get('/api/integration/changes', $since ? ['since' => $since] : []);
        } catch (\Throwable $e) {
            throw new RegistrationApiException('Could not reach the registration app: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 401) {
            throw new RegistrationApiException(
                'The registration app rejected our token (401). Check REGISTRATION_API_TOKEN matches the registration side.'
            );
        }

        if (! $response->successful()) {
            throw new RegistrationApiException('The registration app returned an error ('.$response->status().').');
        }

        return [
            'last_changed_at' => $response->json('last_changed_at'),
            'count' => (int) $response->json('count', 0),
            'students' => $response->json('students', []),
        ];
    }
}
