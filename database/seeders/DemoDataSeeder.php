<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo / click-around data: a couple of subjects, a few classes each, students
 * enrolled into them, and today's attendance for some of them.
 *
 * Idempotent — every row is created with firstOrCreate, so running it twice
 * does not duplicate data. Run it explicitly:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * It does NOT touch real production data beyond adding these demo rows; it is
 * not wired into DatabaseSeeder so a bare `db:seed` won't run it.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->toDateString();

        // A teacher to own the attendance records.
        $teacher = User::firstOrCreate(
            ['email' => 'demo.teacher@example.com'],
            ['name' => 'Demo Teacher', 'password' => Hash::make('password')],
        );

        // Subjects.
        $buddhism = Subject::firstOrCreate(['name' => 'Buddhism']);
        $sinhala = Subject::firstOrCreate(['name' => 'Sinhala']);

        // Classes A–E — the names the registration allocation rule produces, so
        // synced allocations resolve here by name.
        $classA = ClassModel::firstOrCreate(['name' => 'Class A']);
        $classB = ClassModel::firstOrCreate(['name' => 'Class B']);
        $classC = ClassModel::firstOrCreate(['name' => 'Class C']);
        ClassModel::firstOrCreate(['name' => 'Class D']);
        ClassModel::firstOrCreate(['name' => 'Class E']);

        // Students.
        $students = [
            '1001' => ['Amara', 'Perera'],
            '1002' => ['Nimal', 'Silva'],
            '1003' => ['Kavya', 'Fernando'],
            '1004' => ['Sanduni', 'Jayasuriya'],
            '1005' => ['Tharindu', 'Bandara'],
            '1006' => ['Ishara', 'Wickramasinghe'],
            '1007' => ['Dilani', 'Gunawardena'],
            '1008' => ['Ruwan', 'Dissanayake'],
        ];
        foreach ($students as $number => [$first, $last]) {
            Student::firstOrCreate(
                ['student_number' => (string) $number],
                ['first_name' => $first, 'last_name' => $last],
            );
        }

        // Enrollments: (student, class, subject).
        //   Buddhism -> Class A and Class B
        //   Sinhala  -> Class B and Class C
        $enrollments = [
            ['1001', $classA->id, $buddhism->id],
            ['1002', $classA->id, $buddhism->id],
            ['1003', $classA->id, $buddhism->id],
            ['1004', $classB->id, $buddhism->id],
            ['1005', $classB->id, $buddhism->id],

            ['1004', $classB->id, $sinhala->id],
            ['1005', $classB->id, $sinhala->id],
            ['1006', $classC->id, $sinhala->id],
            ['1007', $classC->id, $sinhala->id],
            ['1008', $classC->id, $sinhala->id],
        ];
        foreach ($enrollments as [$number, $classId, $subjectId]) {
            Enrollment::firstOrCreate([
                'student_number' => $number,
                'class_id' => $classId,
                'subject_id' => $subjectId,
            ]);
        }

        // Attendance across several weekly sessions (the school meets weekly),
        // so reports and the summary have some history to show. Each session
        // marks a subset of each (subject, class) group present, with the
        // who-showed-up varying week to week.
        //   classes meet: Buddhism -> A, B ; Sinhala -> B, C
        $sessions = [
            // today (date offset in days => who was present)
            0 => [
                ['1001', $classA->id, $buddhism->id],
                ['1003', $classA->id, $buddhism->id],
                ['1004', $classB->id, $buddhism->id],
                ['1004', $classB->id, $sinhala->id],
                ['1005', $classB->id, $sinhala->id],
                ['1006', $classC->id, $sinhala->id],
                ['1007', $classC->id, $sinhala->id],
            ],
            // one week ago — full house in Buddhism A, a couple away elsewhere
            7 => [
                ['1001', $classA->id, $buddhism->id],
                ['1002', $classA->id, $buddhism->id],
                ['1003', $classA->id, $buddhism->id],
                ['1004', $classB->id, $buddhism->id],
                ['1005', $classB->id, $buddhism->id],
                ['1004', $classB->id, $sinhala->id],
                ['1006', $classC->id, $sinhala->id],
                ['1007', $classC->id, $sinhala->id],
                ['1008', $classC->id, $sinhala->id],
            ],
            // two weeks ago
            14 => [
                ['1001', $classA->id, $buddhism->id],
                ['1002', $classA->id, $buddhism->id],
                ['1005', $classB->id, $buddhism->id],
                ['1005', $classB->id, $sinhala->id],
                ['1007', $classC->id, $sinhala->id],
                ['1008', $classC->id, $sinhala->id],
            ],
            // three weeks ago
            21 => [
                ['1002', $classA->id, $buddhism->id],
                ['1003', $classA->id, $buddhism->id],
                ['1004', $classB->id, $buddhism->id],
                ['1004', $classB->id, $sinhala->id],
                ['1005', $classB->id, $sinhala->id],
                ['1006', $classC->id, $sinhala->id],
            ],
        ];

        $attendanceCount = 0;
        foreach ($sessions as $daysAgo => $present) {
            $date = now()->subDays($daysAgo)->toDateString();
            foreach ($present as [$number, $classId, $subjectId]) {
                Attendance::firstOrCreate([
                    'date' => $date,
                    'subject_id' => $subjectId,
                    'class_id' => $classId,
                    'student_number' => $number,
                ], [
                    'teacher_id' => $teacher->id,
                ]);
                $attendanceCount++;
            }
        }

        $this->command?->info('Demo data seeded: 2 subjects, 3 classes, '
            .count($students).' students, '.count($enrollments).' enrollments, '
            .$attendanceCount.' attendance records across '.count($sessions)
            .' sessions (most recent '.$today.').');
    }
}
