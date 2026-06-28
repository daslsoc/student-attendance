<?php

namespace App\Http\Controllers;

use App\Exceptions\RegistrationApiException;
use App\Models\IntegrationSyncState;
use App\Services\RegistrationSyncService;
use Illuminate\Support\Facades\Log;

/**
 * Shows when the app last synced allocations from student-registration and lets
 * a teacher trigger a sync on demand. (Routine syncing is done by cron calling
 * `php artisan registration:sync`.)
 */
class RegistrationSyncController extends Controller
{
    public function show()
    {
        $state = IntegrationSyncState::current();

        return view('integration.status', compact('state'));
    }

    public function run(RegistrationSyncService $sync)
    {
        try {
            $result = $sync->sync();
        } catch (RegistrationApiException $e) {
            Log::error('Registration sync failed', ['error' => $e->getMessage()]);

            return redirect()->route('integration.status')->withErrors($e->getMessage());
        }

        $message = "Sync complete: {$result['received']} received, {$result['enrolled']} newly enrolled, {$result['moved']} moved.";

        return redirect()->route('integration.status')
            ->with('message', $message)
            ->with('warnings', $result['warnings']);
    }
}
