<?php

namespace Tests\Feature;

use App\Console\Commands\DbConvertTimestamps;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * db:convert-timestamps rewrites legacy TIMESTAMP values (wall-clock
 * strings stored under the server's zone) as the instants they meant.
 * The table is created with DDL, so no transaction trait: it is dropped
 * in tearDown.
 */
class DbConvertTimestampsTest extends TestCase
{
    private const ZONE = 'Australia/Sydney';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Schema::dropIfExists('tz_probe');
        Schema::create('tz_probe', function (Blueprint $t) {
            $t->id();
            $t->timestamp('at')->nullable();
            $t->timestamp('touched')->useCurrent()->useCurrentOnUpdate();
        });
        // Legacy state by construction, whatever DB_TIMEZONE the test env has:
        // write the wall-clock strings with the server's own zone in effect.
        DB::statement("SET time_zone = 'SYSTEM'");
        DB::table('tz_probe')->insert([
            ['at' => '2026-01-15 10:00:00', 'touched' => '2026-01-15 10:00:00'],   // AEDT, +11
            ['at' => '2026-07-15 10:00:00', 'touched' => '2026-07-15 10:00:00'],   // AEST, +10
            ['at' => null, 'touched' => '2026-07-15 10:00:00'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tz_probe');
        parent::tearDown();
    }

    /** @return array<int, array{at: ?int, touched: int}> keyed by id */
    private function instants(): array
    {
        // UNIX_TIMESTAMP(<timestamp col>) is the stored instant, independent of session zone.
        $out = [];
        foreach (DB::select('SELECT id, UNIX_TIMESTAMP(at) AS at, UNIX_TIMESTAMP(touched) AS touched FROM tz_probe ORDER BY id') as $r) {
            $out[(int) $r->id] = ['at' => $r->at === null ? null : (int) $r->at, 'touched' => (int) $r->touched];
        }

        return $out;
    }

    public function test_rewrites_wall_clock_values_as_instants_in_the_given_zone(): void
    {
        $this->artisan('db:convert-timestamps', ['--from' => self::ZONE])->assertSuccessful();

        $summer = Carbon::parse('2026-01-15 10:00:00', self::ZONE)->getTimestamp();
        $winter = Carbon::parse('2026-07-15 10:00:00', self::ZONE)->getTimestamp();
        $rows = $this->instants();

        $this->assertSame($summer, $rows[1]['at']);
        $this->assertSame($winter, $rows[2]['at']);
        $this->assertNull($rows[3]['at']);
        // ON UPDATE CURRENT_TIMESTAMP columns are converted, not bumped to now.
        $this->assertSame($summer, $rows[1]['touched']);
        $this->assertSame($winter, $rows[3]['touched']);
        Storage::disk('local')->assertExists(DbConvertTimestamps::MARKER);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $before = $this->instants();
        $this->artisan('db:convert-timestamps', ['--from' => self::ZONE, '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame($before, $this->instants());
        Storage::disk('local')->assertMissing(DbConvertTimestamps::MARKER);
    }

    public function test_refuses_to_run_twice_unless_forced(): void
    {
        $this->artisan('db:convert-timestamps', ['--from' => self::ZONE])->assertSuccessful();
        $once = $this->instants();

        $this->artisan('db:convert-timestamps', ['--from' => self::ZONE])->assertFailed();
        $this->assertSame($once, $this->instants());

        $this->artisan('db:convert-timestamps', ['--from' => 'Asia/Kolkata', '--force' => true])->assertSuccessful();
        $this->assertNotSame($once, $this->instants());
    }

    public function test_rejects_an_unknown_zone(): void
    {
        $this->artisan('db:convert-timestamps', ['--from' => 'Mars/Olympus'])->assertFailed();
    }
}
