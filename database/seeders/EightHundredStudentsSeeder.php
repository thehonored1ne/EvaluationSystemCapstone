<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class EightHundredStudentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure 'student' role exists
        Role::firstOrCreate(['name' => 'student']);

        // 2. Ensure Department & BSIT Program exist
        $department = Department::firstOrCreate(
            ['code' => 'CCS'],
            ['name' => 'College of Computer Studies']
        );

        $bsitProgram = Program::firstOrCreate(
            ['code' => 'BSIT'],
            [
                'name' => 'Bachelor of Science in Information Technology',
                'department_id' => $department->id,
            ]
        );

        // Realistic name pools
        $firstNamesMale = [
            'Juan', 'Mark', 'John', 'Paul', 'Christian', 'Angelo', 'Joshua', 'Michael', 'Gabriel', 'Daniel',
            'Alexander', 'James', 'David', 'Joseph', 'Anthony', 'Christopher', 'Andrew', 'Ryan', 'Kevin', 'Justin',
            'Kenneth', 'Bryan', 'Carl', 'Ethan', 'Liam', 'Noah', 'Lucas', 'Oliver', 'Elijah', 'Benjamin',
            'Mason', 'Logan', 'Jacob', 'Jackson', 'Aiden', 'Samuel', 'Matthew', 'Henry', 'Sebastian', 'Owen',
        ];

        $firstNamesFemale = [
            'Maria', 'Angel', 'Mary', 'Princess', 'Samantha', 'Nicole', 'Patricia', 'Christine', 'Bea', 'Sofia',
            'Chloe', 'Alyssa', 'Camille', 'Hannah', 'Jasmine', 'Grace', 'Andrea', 'Erica', 'Denise', 'Kaye',
            'Emma', 'Ava', 'Charlotte', 'Amelia', 'Mia', 'Harper', 'Evelyn', 'Abigail', 'Emily', 'Ella',
            'Elizabeth', 'Camila', 'Luna', 'Sofia', 'Avery', 'Mila', 'Scarlett', 'Eleanor', 'Madison', 'Layla',
        ];

        $lastNames = [
            'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Castillo', 'Villanueva', 'Ramos',
            'Castro', 'Rivera', 'Bautista', 'Aquino', 'Navarro', 'Salazar', 'Mercado', 'Valenzuela', 'Domingo', 'Del Rosario',
            'Corpuz', 'Tolentino', 'Cruz', 'Morales', 'Aguilar', 'Pineda', 'Soriano', 'Santiago', 'Perez', 'Manalo',
            'Fernandez', 'Hernandez', 'Lopez', 'Gonzalez', 'Rodriguez', 'Martinez', 'Sanchez', 'Perez', 'Gomez', 'Martin',
        ];

        $middleNames = [
            'Alcantara', 'Barrios', 'Cabrera', 'Dizon', 'Enriquez', 'Francisco', 'Gonzales', 'Hermoso', 'Ignacio', 'Jimenez',
            'Katigbak', 'Lacson', 'Magno', 'Nolasco', 'Ocampo', 'Padilla', 'Quinto', 'Rosales', 'Sevilla', 'Tiongson',
            'Urbano', 'Velasco', 'Wenceslao', 'Yambao', 'Zamora', 'Aragon', 'Beltran', 'Cordero', 'Delgado', 'Esperanza',
        ];

        $suffixes = [null, null, null, null, null, null, null, null, 'Jr.', 'Sr.', 'III', 'IV', 'V'];
        $sections = ['A', 'B', 'C', 'D'];
        $passwordHash = Hash::make('password');

        DB::beginTransaction();

        try {
            $studentCount = 0;

            // 4 Year Levels x 200 Students each = 800 total
            for ($yearLevel = 1; $yearLevel <= 4; $yearLevel++) {
                for ($i = 1; $i <= 200; $i++) {
                    $studentCount++;

                    $isMale = (rand(0, 1) === 1);
                    $firstName = $isMale
                        ? $firstNamesMale[array_rand($firstNamesMale)]
                        : $firstNamesFemale[array_rand($firstNamesFemale)];

                    $lastName = $lastNames[array_rand($lastNames)];
                    $middleName = $middleNames[array_rand($middleNames)];
                    $suffix = $isMale ? $suffixes[array_rand($suffixes)] : null;

                    // Section calculation: BSIT-1A, 1B, 1C, 1D (50 students per section)
                    $sectionIndex = min((int) floor(($i - 1) / 50), 3);
                    $sectionCode = 'BSIT-'.$yearLevel.$sections[$sectionIndex];

                    $studentNumber = sprintf('2026-IT-%04d', $studentCount);
                    $email = sprintf('student.%04d@evaluationsystem.test', $studentCount);

                    $student = Student::create([
                        'student_number' => $studentNumber,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'middle_name' => $middleName,
                        'suffix' => $suffix,
                        'program_id' => $bsitProgram->id,
                        'year_level' => $yearLevel,
                        'section' => $sectionCode,
                        'status' => 'regular',
                    ]);

                    $user = User::create([
                        'name' => $student->formatted_name,
                        'email' => $email,
                        'password' => $passwordHash,
                        'student_id' => $student->id,
                    ]);

                    $user->assignRole('student');
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
