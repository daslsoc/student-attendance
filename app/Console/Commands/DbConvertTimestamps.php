<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One-off: make every TIMESTAMP column hold the instant the app meant.
 *
 * Before DB_TIMEZONE existed, Laravel wrote created_at & co. as wall-clock
 * strings in the app's timezone over a connection that never set a MySQL
 * session time zone, so MySQL interpreted them in *its* zone (UTC in the
 * docker image, US Pacific on DreamHost). The app read them back the same
 * way, so nothing looked wrong, but the stored instants are off by the
 * difference between the two zones, DB-generated defaults
 * (CURRENT_TIMESTAMP) are in the server's zone, and the moment the session
 * zone is set to the app's zone every old value displays shifted.
 *
 * This command re-stores each value as "that wall-clock in --from" so that
 * DB_TIMEZONE can then be set. Run it ONCE per database, with the app in
 * maintenance mode, and set DB_TIMEZONE straight after:
 *
 *   php artisan down
 *   php artisan db:convert-timestamps --dry-run     # what would change
 *   php artisan db:convert-timestamps               # --from=UTC if the app ran in UTC
 *   # add DB_TIMEZONE=<the app timezone> to .env (php artisan config:clear if cached)
 *   php artisan up
 *
 * The UPDATE is CONVERT_TZ(col, from, 'SYSTEM'). Under the old session
 * (SYSTEM) a column shows the wall-clock the app wrote; converting that to
 * the server's zone and storing it back yields the intended instant. The
 * same expression is also right when DB_TIMEZONE is already set, so the
 * order of the two steps does not matter. Running it TWICE does (every
 * value shifts again), hence the marker file and --force. Every TIMESTAMP
 * column of a table is assigned in one statement so ON UPDATE
 * CURRENT_TIMESTAMP columns are not bumped to "now".
 */
class DbConvertTimestamps extends Command
{
    protected $signature = 'db:convert-timestamps
        {--from= : Zone the existing values were written in (default: the app timezone).}
        {--dry-run : List tables, columns and row counts; change nothing.}
        {--force : Run even though the marker says this database was converted already.}';

    protected $description = 'One-off: re-store TIMESTAMP columns so DB_TIMEZONE can be set to the app timezone';

    /** Written to the local storage disk after a successful run. */
    public const MARKER = 'timestamps-converted.json';

    public function handle(): int
    {
        $from = $this->option('from') ?: config('app.timezone');
        $dry = (bool) $this->option('dry-run');

        $probe = $this->preflight($from, $dry);
        $result = $probe === null ? null : $this->convert($this->timestampColumnsByTable(), $from, $dry);
        if ($result === null) {
            return self::FAILURE;
        }
        $this->report($result, $from, $probe, $dry);

        return self::SUCCESS;
    }

    /** The server/session zones, or null (after printing why) when the run must not go ahead. */
    private function preflight(string $from, bool $dry): ?object
    {
        if (! $dry && ! $this->option('force') && Storage::disk('local')->exists(self::MARKER)) {
            $this->error('Already converted ('.self::MARKER.' on the local disk). Running twice shifts every value again; use --force only if you know the marker is stale.');

            return null;
        }

        $probe = DB::selectOne(
            "SELECT @@system_time_zone AS server, @@session.time_zone AS session, CONVERT_TZ('2026-01-01 00:00:00', ?, 'SYSTEM') AS ok",
            [$from]
        );
        if ($probe->ok === null) {
            $this->error("MySQL cannot convert from '{$from}': time zone tables not loaded or zone unknown.");

            return null;
        }
        $this->info("Server zone {$probe->server}, session {$probe->session}; existing values were written in {$from}.");

        return $probe;
    }

    /**
     * Every TIMESTAMP column, plus any DATETIME column with ON UPDATE
     * CURRENT_TIMESTAMP (assigned to itself so the UPDATE does not bump it).
     *
     * @return array<string, list<object>>
     */
    private function timestampColumnsByTable(): array
    {
        $columns = DB::select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c, DATA_TYPE AS type
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND (DATA_TYPE = 'timestamp' OR EXTRA LIKE '%on update%')
              ORDER BY 1, 2"
        );
        $byTable = [];
        foreach ($columns as $col) {
            $byTable[$col->t][] = $col;
        }

        return $byTable;
    }

    /**
     * Convert (or, on a dry run, count) every table in one transaction.
     *
     * @param  array<string, list<object>>  $byTable
     * @return array{rows: int, converted: array<string, list<string>>, empty: bool}|null null when it failed and nothing changed
     */
    private function convert(array $byTable, string $from, bool $dry): ?array
    {
        $result = ['rows' => 0, 'converted' => [], 'empty' => $byTable === []];
        if ($result['empty']) {
            return $result;
        }

        DB::beginTransaction();
        try {
            foreach ($byTable as $table => $cols) {
                [$sets, $names] = $this->assignments($cols);
                if ($names === []) {
                    continue;
                }
                $n = (int) DB::selectOne('SELECT COUNT(*) AS n FROM '.self::quote($table))->n;
                if (! $dry && $n > 0) {
                    DB::update('UPDATE '.self::quote($table).' SET '.implode(', ', $sets), array_fill(0, count($names), $from));
                }
                $this->line(sprintf('  %-32s %8d rows   %s', $table, $n, implode(', ', $names)));
                $result['rows'] += $n;
                $result['converted'][$table] = $names;
            }
            $dry ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Failed, nothing changed: '.$e->getMessage());

            return null;
        }

        return $result;
    }

    /**
     * SET clauses for one table, and the TIMESTAMP columns they convert.
     *
     * @param  list<object>  $cols
     * @return array{0: list<string>, 1: list<string>}
     */
    private function assignments(array $cols): array
    {
        $sets = [];
        $names = [];
        foreach ($cols as $col) {
            $c = self::quote($col->c);
            if ($col->type === 'timestamp') {
                // TIMESTAMP cannot hold anything before 1970-01-01 00:00:01 UTC;
                // leave the zero/epoch sentinels some apps use alone.
                $sets[] = "{$c} = IF({$c} >= '1971-01-01', CONVERT_TZ({$c}, ?, 'SYSTEM'), {$c})";
                $names[] = $col->c;
            } else {
                $sets[] = "{$c} = {$c}";
            }
        }

        return [$sets, $names];
    }

    /** @param  array{rows: int, converted: array<string, list<string>>, empty: bool}  $result */
    private function report(array $result, string $from, object $probe, bool $dry): void
    {
        $rows = $result['rows'];
        $tables = count($result['converted']);
        if ($result['empty']) {
            $this->info('No TIMESTAMP columns in this database; nothing to do.');
        } elseif ($dry) {
            $this->info("Dry run: {$rows} rows across {$tables} tables would be converted.");
        } else {
            Storage::disk('local')->put(self::MARKER, json_encode([
                'converted_at' => now()->toIso8601String(),
                'from' => $from,
                'server_zone' => $probe->server,
                'tables' => $result['converted'],
                'rows' => $rows,
            ], JSON_PRETTY_PRINT));
            $this->info("Converted {$rows} rows across {$tables} tables.");
            $this->warn("Now set DB_TIMEZONE={$from} in .env (then php artisan config:clear if config is cached) and bring the app back up.");
        }
    }

    private static function quote(string $ident): string
    {
        return '`'.str_replace('`', '', $ident).'`';
    }
}
