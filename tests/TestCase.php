<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Put a logged-in teacher into the session the same way
     * AuthController::loginUsingToken does, so routes behind
     * EnsureTeacherAuthenticated are reachable in tests.
     */
    protected function actingAsTeacher(?User $teacher = null): User
    {
        $teacher ??= User::factory()->create();

        $this->withSession([
            'teacher_logged_in' => true,
            'login_token_expires_at' => now()->addHour(),
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->name,
        ]);

        return $teacher;
    }

    /**
     * Databases the test suite is allowed to migrate / refresh against.
     *
     * Anything else (most importantly the dev sqlite file or the production
     * MySQL database) is rejected before RefreshDatabase can run
     * `migrate:fresh`.
     */
    protected array $allowedTestDatabases = ['attendance_test', 'attendance_dusk'];

    /**
     * Boot the application, assert we are pointed at a test database, and
     * only THEN let the parent run the trait setup (RefreshDatabase, which
     * wipes + migrates). Booting first makes config() available; running the
     * guard before parent::setUp() means a misconfigured run aborts before
     * any destructive migrate:fresh can touch a real database.
     */
    protected function setUp(): void
    {
        $this->refreshApplication();
        $this->guardAgainstNonTestDatabase();

        parent::setUp();

        // The layout pulls in the Vite bundle via @vite. Stub it so feature
        // tests don't require `npm run build` to have run (keeps the PHP CI job
        // independent of the JS build).
        $this->withoutVite();
    }

    protected function guardAgainstNonTestDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        // In-memory SQLite is always safe (nothing persistent to destroy).
        $isInMemorySqlite = $connection === 'sqlite'
            && in_array($database, [':memory:', null], true);

        if ($isInMemorySqlite || in_array($database, $this->allowedTestDatabases, true)) {
            return;
        }

        throw new \RuntimeException(sprintf(
            "Refusing to run migrations against database '%s'. The test suite may only ".
            'touch: %s. Check that phpunit.xml pins DB_DATABASE with force=\"true\".',
            $database ?? '(null)',
            implode(', ', $this->allowedTestDatabases),
        ));
    }
}
