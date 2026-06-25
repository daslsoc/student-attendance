# Setup history & scratch queries

This is the original scaffolding log and ad-hoc SQL that used to live in the
README, preserved verbatim for reference. It is **history, not instructions** —
for current setup see the [README](../README.md), and for curated admin queries
see [operations.md](operations.md).

## All commands used to set up this repository

```bash
git clone https://github.com/daslsoc/student-attendance.git
mv student-attendance/ student-attendance1
docker run --mount type=bind,src=./,dst=/app composer:2 create-project laravel/laravel student-attendance
sudo mv student-attendance1/.git/ student-attendance
sudo mv student-attendance1/LICENSE student-attendance
rmdir student-attendance1

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:migration create_students_table --create=students
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:migration create_subjects_table --create=subjects
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:migration create_classes_table --create=classes
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:migration create_attendance_table --create=attendance
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan migrate

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:model Student
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:model Subject
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:model ClassModel
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:model Attendance
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:model Enrollment -m

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:controller AuthController
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:controller DashboardController
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:controller AttendanceController

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:mail LoginLinkMail --markdown=emails.loginlink

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:middleware EnsureTeacherAuthenticated

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:factory SubjectFactory --model=Subject
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:factory ClassModelFactory --model=ClassModel

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:model BookDistribution -m
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:controller BookDistributionController

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan make:migration add_login_token_fields_to_users_table --table=users

docker-compose up --build
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan migrate --seed

docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan config:clear
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan view:clear
docker run --mount type=bind,src=./,dst=/var/www/html php:8-fpm php artisan cache:clear
```

## Custom seed (subjects / classes / sample students)

```php
\App\Models\Subject::create(['name' => 'Buddhism']);
\App\Models\Subject::create(['name' => 'Sinhala']);

\App\Models\ClassModel::create(['name' => 'Class 1 (A)']);
\App\Models\ClassModel::create(['name' => 'Class 1 (B)']);
\App\Models\ClassModel::create(['name' => 'Class 2 (C)']);
\App\Models\ClassModel::create(['name' => 'Class 3 (D)']);
\App\Models\ClassModel::create(['name' => 'Class 4 (E)']);

\App\Models\Student::create(['student_number' => '1', 'first_name' => 'Jo', 'last_name' => 'Blogs']);
\App\Models\Student::create(['student_number' => '2', 'first_name' => 'Jane', 'last_name' => 'Blogs']);
\App\Models\Enrollment::create(['student_number' => '1', 'class_id' => 1, 'subject_id' => 1]);
\App\Models\Enrollment::create(['student_number' => '1', 'class_id' => 1, 'subject_id' => 2]);
\App\Models\Enrollment::create(['student_number' => '2', 'class_id' => 2, 'subject_id' => 1]);
```

## Scratch reporting queries

```sql
-- Enrolled students for subject 1, with their class
SELECT `enrollments`.student_number AS "Student #", students.first_name, students.last_name, classes.name AS "Class"
FROM `enrollments`, students, classes
WHERE `enrollments`.`student_number` = students.`student_number` AND subject_id = 1 AND classes.id = `enrollments`.class_id;

-- List of student numbers and names (numeric sort)
SELECT student_number, concat(first_name, " ", last_name) FROM `students` ORDER BY student_number + 0;

-- Distinct attendance dates
SELECT DISTINCT date(`created_at`) FROM attendances ORDER BY date(`created_at`) ASC;

-- Student attendance dates
SELECT DISTINCT `attendances`.student_number, concat(`students`.first_name, " ", `students`.last_name) AS "Name", date(`attendances`.`created_at`)
FROM `attendances`, `students`
WHERE `students`.student_number = `attendances`.student_number
ORDER BY date(`created_at`) ASC;
```

## Open notes

- intake has to also cover a mature-age student including drop-down lists
