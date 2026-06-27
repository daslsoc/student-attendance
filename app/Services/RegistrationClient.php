<?php

namespace App\Services;

use App\Exceptions\RegistrationApiException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the student-registration integration API. Read-only: it only ever
 * fetches the list of paid students. Authentication is a shared bearer token.
 */
class RegistrationClient
{
    /**
     * The children whose family has paid, as returned by registration. Each row:
     * student_number, first_name, last_name, dhamma_class, sinhala_class.
     *
     * @return array<int, array<string, string>>
     *
     * @throws RegistrationApiException
     */
    public function paidStudents(): array
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
                ->get('/api/integration/paid-students');
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

        return $response->json('data', []);
    }
}
