
# All commands used to setup this repository
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

# Custom Seed

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

SELECT `enrollments`.student_number as "Student #", students.first_name, students.last_name, classes.name as "Class"
FROM `enrollments`, students, classes
where `enrollments`.`student_number`= students.`student_number` and subject_id=1 and classes.id=`enrollments`.class_id;

* list of students numbers and their names
SELECT student_number, concat(first_name," ",last_name) FROM `students` order by student_number+0;

* distinct dates
select DISTINCT date(`created_at`) from attendances order by date(`created_at`) asc

* student attendance dates
SELECT distinct `attendances`.student_number, concat(`students`.first_name, " ", `students`.last_name) as "Name", date(`attendances`.`created_at`)
FROM `attendances`, `students`
where `students`.student_number=`attendances`.student_number
order by date(`created_at`) asc;

* intake has to also cover a mature age student including drop down lists