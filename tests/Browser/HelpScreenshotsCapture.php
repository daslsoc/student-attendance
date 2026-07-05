<?php

namespace Tests\Browser;

use App\Models\ClassModel;
use App\Models\IntegrationSyncState;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Captures the screenshots shown on the in-app teacher Help page
 * (resources/views/help/index.blade.php). This is a documentation tool, NOT a
 * regression test.
 *
 * It is guarded by an env flag so it never runs (and never overwrites the
 * committed screenshots) during a normal `make test-dusk` / CI run. To refresh
 * the images, bring up the Dusk stack and run it explicitly:
 *
 *   make dusk-up
 *   make dusk ARGS="--env=DOCS_SCREENSHOTS=1 tests/Browser/HelpScreenshotsCapture.php"
 *   make dusk-down
 *
 * (or, directly:
 *   docker compose -f docker-compose.yml -f docker-compose.dusk.yml run --rm \
 *     -e DOCS_SCREENSHOTS=1 app php artisan dusk tests/Browser/HelpScreenshotsCapture.php)
 *
 * Each shot is written straight into public/images/help/<name>.png, exactly
 * where the Help page's <img src="/images/help/..."> references it — no copy
 * step. Filenames must stay in sync with the Help blade if you rename a shot.
 */
class HelpScreenshotsCapture extends DuskTestCase
{
    use DatabaseMigrations;

    /** A valid, unexpired magic-link token so the login path signs us in. */
    private const LOGIN_TOKEN = 'docs-screenshots-token';

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('DOCS_SCREENSHOTS')) {
            $this->markTestSkipped(
                'Documentation capture. Run with DOCS_SCREENSHOTS=1 to (re)generate '.
                'the Help page screenshots — see the class docblock.'
            );
        }
    }

    public function test_capture_help_pages(): void
    {
        $this->seedDemoData();

        $buddhism = Subject::where('name', 'Buddhism')->firstOrFail();
        $classA = ClassModel::where('name', 'Class A')->firstOrFail();

        $this->browse(function (Browser $browser) use ($buddhism, $classA) {
            $browser->resize(1366, 900);

            // The login screen, as a signed-out teacher sees it.
            $browser->visit('/login')
                ->waitForText('Teacher Login')
                ->pause(300);
            $this->capture($browser, 'login.png');

            // Follow a valid magic link — the app's real login path. It drops us
            // on New Attendance (the subject + class picker).
            $browser->visit('/login/'.self::LOGIN_TOKEN)
                ->waitForText('Select Subject and Class')
                ->pause(300);
            $this->capture($browser, 'select-subject-class.png');

            // Mark attendance for a class with some students already present.
            $browser->visit("/attendance?subject_id={$buddhism->id}&class_id={$classA->id}")
                ->waitForText('Mark Attendance')
                ->waitFor('button.student-btn')
                ->pause(500);
            $this->capture($browser, 'mark-attendance.png');

            // Book distribution — same tap-to-mark layout.
            $browser->visit("/book-distribution?subject_id={$buddhism->id}&class_id={$classA->id}")
                ->waitForText('Mark Books Given')
                ->waitFor('button.student-btn')
                ->pause(500);
            $this->capture($browser, 'book-distribution.png');

            // Edit / back-fill grid (DataTable — give it a moment to render).
            $browser->visit("/attendance-edit?subject_id={$buddhism->id}&class_id={$classA->id}")
                ->waitForText('Edit Attendance')
                ->waitFor('#edit-table')
                ->pause(800);
            $this->capture($browser, 'edit-attendance.png');

            // Today's Report (subject cards).
            $browser->visit('/attendance-summary')
                ->waitForText("Today's Report")
                ->pause(500);
            $this->capture($browser, 'todays-report.png');

            // Full Year Report (DataTable).
            $browser->visit("/attendance-report?subject_id={$buddhism->id}")
                ->waitForText('Full Year Report')
                ->waitFor('#report-table')
                ->pause(800);
            $this->capture($browser, 'full-year-report.png');

            // Registration Sync status.
            $browser->visit('/registration-sync')
                ->waitForText('Registration Sync')
                ->pause(400);
            $this->capture($browser, 'registration-sync.png');
        });
    }

    /**
     * Write the current viewport to public/images/help/<name>.
     */
    private function capture(Browser $browser, string $name): void
    {
        $absolute = public_path('images/help/'.$name);
        File::ensureDirectoryExists(dirname($absolute));

        file_put_contents($absolute, $browser->driver->takeScreenshot());
    }

    /**
     * A small, realistic roster (the demo seeder) plus a teacher who can log in
     * via magic link and a populated sync-state row, so every Help screenshot
     * has something meaningful to show.
     */
    private function seedDemoData(): void
    {
        $this->seed(DemoDataSeeder::class);

        User::factory()->create([
            'name' => 'Ms Fernando',
            'email' => 'ms.fernando@example.test',
            'login_token' => self::LOGIN_TOKEN,
            'login_token_expires_at' => now()->addHour(),
        ]);

        IntegrationSyncState::create([
            'last_synced_at' => now()->subHours(2),
            'last_checked_at' => now()->subMinutes(15),
            'last_count' => 147,
        ]);
    }
}
