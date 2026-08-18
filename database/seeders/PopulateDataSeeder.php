<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class PopulateDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'department head']);
        Role::firstOrCreate(['name' => 'staff']);
        Role::firstOrCreate(['name' => 'faculty']);

        $fakerPH = Faker::create('en_PH');
        $faker = Faker::create();

        // Curated authentic Filipino first and last names for realistic generation
        $firstNamesMale = [
            'Danilo', 'Eduardo', 'Roberto', 'Arnel', 'Ferdinand',
            'Renato', 'Gabriel', 'Ramon', 'Alejandro', 'Enrique',
            'Angelo', 'Christian', 'Dominic', 'Manuel', 'Vicente',
            'Rodolfo', 'Jaime', 'Ricardo', 'Antonio', 'Emilio',
            'Leonardo', 'Salvador', 'Alfonso', 'Bernardo', 'Cesar',
            'Ernesto', 'Guillermo', 'Hector', 'Ignacio', 'Jerome',
            'Kenneth', 'Lorenzo', 'Nestor', 'Orlando', 'Paolo',
            'Rafael', 'Sergio', 'Tristan', 'Valentin', 'Wilfredo',
            'Alexander', 'Benedict', 'Claudio', 'Dexter', 'Edgardo',
            'Francis', 'Gerardo', 'Homer', 'Ivan', 'Joel',
        ];

        $firstNamesFemale = [
            'Maria Corazon', 'Carmela', 'Theresa', 'Lourdes', 'Rosalinda',
            'Elena', 'Marilou', 'Rowena', 'Bernadette', 'Kristine',
            'Jocelyn', 'Maricel', 'Patricia', 'Divina', 'Leticia',
            'Consuelo', 'Clarissa', 'Veronica', 'Angelica', 'Beatriz',
            'Camille', 'Diane', 'Estrella', 'Felicia', 'Gemma',
            'Hazel', 'Irene', 'Janice', 'Katrina', 'Lorraine',
            'Miriam', 'Noemi', 'Ophelia', 'Pauline', 'Rochelle',
            'Sheila', 'Tess', 'Ursula', 'Victoria', 'Wendy',
            'Abigail', 'Bianca', 'Cynthia', 'Daisy', 'Evelyn',
            'Fatima', 'Grace', 'Hannah', 'Isabel', 'Judith',
        ];

        $lastNames = [
            'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo',
            'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andrada',
            'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Castro',
            'Rivera', 'Aquino', 'Navarro', 'Salazar', 'Mercado',
            'De La Cruz', 'Del Rosario', 'San Jose', 'Tolentino', 'Corpuz',
            'Soriano', 'Manalo', 'Valdez', 'Pascual', 'Gutierrez',
            'Ignacio', 'Ferrer', 'Domingo', 'Santiago', 'Cabrera',
            'Morales', 'Perez', 'Velasco', 'Estrada', 'Aguilar',
            'Guerrero', 'Padilla', 'Cortez', 'Vergara', 'Alcantara',
            'Pineda', 'Sarmiento', 'Villar', 'Lagman', 'Esguerra',
            'Miranda', 'Dizon', 'Bernardo', 'Salas', 'Tan',
            'Lim', 'Chua', 'Sy', 'Gomez', 'Fernandez',
        ];

        $usedEmails = User::pluck('email')->flip()->toArray();

        $generateUniqueEmail = function (string $firstName, string $lastName) use (&$usedEmails): string {
            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z]/', '', explode(' ', $firstName)[0]));
            $cleanLast = strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName));
            $base = "{$cleanFirst}.{$cleanLast}";
            $email = "{$base}@grc.edu.ph";
            $counter = 1;
            while (isset($usedEmails[$email])) {
                $email = "{$base}{$counter}@grc.edu.ph";
                $counter++;
            }
            $usedEmails[$email] = true;

            return $email;
        };

        $getNextEmployeeNumber = function (string $prefix): string {
            $count = Employee::where('employee_number', 'like', "{$prefix}-%")->count() + 1;

            return sprintf('%s-%04d', $prefix, $count);
        };

        // -------------------------------------------------------------
        // 1. POPULATE 10 DEPARTMENT HEADS (Assign all 11 Admin Depts)
        // -------------------------------------------------------------
        $adminDepartments = Department::where('type', 'administrative')->orderBy('id')->get();
        $existingDh = Employee::where('role', 'department head')->first();

        // If existing DH has generic name, rename to a proper full name and assign to first admin dept
        if ($existingDh && $adminDepartments->isNotEmpty()) {
            $firstAdminDept = $adminDepartments->first();
            $existingDh->update([
                'first_name' => 'Roberto',
                'last_name' => 'Alcantara',
                'middle_name' => 'Santos',
                'department_id' => $firstAdminDept->id,
            ]);
            if ($existingDh->user) {
                $existingDh->user->update([
                    'name' => 'Roberto S. Alcantara',
                    'email' => 'roberto.alcantara@grc.edu.ph',
                ]);
            }
            $firstAdminDept->update(['department_head_id' => $existingDh->id]);
            $this->command?->info("Assigned existing Department Head Roberto Alcantara to {$firstAdminDept->name}");
        }

        // Create 10 new Department Heads for the remaining administrative departments
        $remainingAdminDepts = $adminDepartments->slice(1)->values();
        $dhNames = [
            ['Maria Corazon', 'Del Rosario', 'Bautista'],
            ['Danilo', 'Villanueva', 'Reyes'],
            ['Carmela', 'Ocampo', 'Cruz'],
            ['Eduardo', 'Mendoza', 'Garcia'],
            ['Theresa', 'Castillo', 'Torres'],
            ['Arnel', 'Salazar', 'Ramos'],
            ['Elena', 'Navarro', 'Aquino'],
            ['Ferdinand', 'Mercado', 'Castro'],
            ['Rosalinda', 'Tolentino', 'Flores'],
            ['Gabriel', 'Manalo', 'San Jose'],
        ];

        foreach ($remainingAdminDepts as $index => $dept) {
            $nameData = $dhNames[$index] ?? [
                $firstNamesMale[array_rand($firstNamesMale)],
                $lastNames[array_rand($lastNames)],
                $lastNames[array_rand($lastNames)],
            ];

            $firstName = $nameData[0];
            $lastName = $nameData[1];
            $middleName = $nameData[2] ?? null;
            $middleInitial = $middleName ? substr($middleName, 0, 1).'.' : '';
            $fullName = trim("{$firstName} {$middleInitial} {$lastName}");
            $email = $generateUniqueEmail($firstName, $lastName);
            $empNum = $getNextEmployeeNumber('DH');

            $emp = Employee::create([
                'employee_number' => $empNum,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_name' => $middleName,
                'role' => 'department head',
                'status' => 'active',
                'department_id' => $dept->id,
            ]);

            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'employee_id' => $emp->id,
                'password' => Hash::make('password'),
                'is_active' => true,
                'show_ai_pipeline' => true,
            ]);
            $user->assignRole('department head');

            $dept->update(['department_head_id' => $emp->id]);
            $this->command?->info("Created Department Head: {$fullName} ({$empNum}) -> {$dept->name}");
        }

        // -------------------------------------------------------------
        // 2. POPULATE 55 STAFF MEMBERS (Distributed across 11 Admin Depts)
        // -------------------------------------------------------------
        // 55 staff / 11 admin departments = 5 staff per administrative department
        $staffCount = 0;
        foreach ($adminDepartments as $dept) {
            for ($i = 1; $i <= 5; $i++) {
                $isFemale = ($staffCount % 2 === 0);
                $firstName = $isFemale
                    ? $firstNamesFemale[$staffCount % count($firstNamesFemale)]
                    : $firstNamesMale[$staffCount % count($firstNamesMale)];
                $lastName = $lastNames[($staffCount * 3 + $i) % count($lastNames)];
                $middleName = $lastNames[($staffCount * 7 + $i) % count($lastNames)];
                $middleInitial = substr($middleName, 0, 1).'.';
                $fullName = "{$firstName} {$middleInitial} {$lastName}";
                $email = $generateUniqueEmail($firstName, $lastName);
                $empNum = $getNextEmployeeNumber('STF');

                $emp = Employee::create([
                    'employee_number' => $empNum,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'middle_name' => $middleName,
                    'role' => 'staff',
                    'status' => 'active',
                    'department_id' => $dept->id,
                ]);

                $user = User::create([
                    'name' => $fullName,
                    'email' => $email,
                    'employee_id' => $emp->id,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'show_ai_pipeline' => true,
                ]);
                $user->assignRole('staff');

                $staffCount++;
            }
        }
        $this->command?->info("Created {$staffCount} Staff members (5 assigned to each of the 11 administrative departments).");

        // -------------------------------------------------------------
        // 3. POPULATE 30 PROFESSORS (FACULTY)
        // -------------------------------------------------------------
        $academicDepartments = Department::where('type', 'academic')->orWhereNull('type')->orderBy('id')->get();
        $academicDeptCount = $academicDepartments->count() > 0 ? $academicDepartments->count() : 1;

        $profCount = 0;
        for ($p = 0; $p < 30; $p++) {
            $isFemale = ($p % 2 === 1);
            $firstName = $isFemale
                ? $firstNamesFemale[($p + 15) % count($firstNamesFemale)]
                : $firstNamesMale[($p + 15) % count($firstNamesMale)];
            $lastName = $lastNames[($p * 5 + 7) % count($lastNames)];
            $middleName = $lastNames[($p * 11 + 3) % count($lastNames)];
            $middleInitial = substr($middleName, 0, 1).'.';
            $fullName = "{$firstName} {$middleInitial} {$lastName}";
            $email = $generateUniqueEmail($firstName, $lastName);
            $empNum = $getNextEmployeeNumber('FAC');

            // Distribute evenly among academic departments
            $dept = $academicDepartments[$p % $academicDeptCount];

            $emp = Employee::create([
                'employee_number' => $empNum,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_name' => $middleName,
                'role' => 'faculty',
                'status' => 'active',
                'department_id' => $dept->id,
            ]);

            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'employee_id' => $emp->id,
                'password' => Hash::make('password'),
                'is_active' => true,
                'show_ai_pipeline' => true,
            ]);
            $user->assignRole('faculty');

            $profCount++;
        }
        $this->command?->info("Created {$profCount} Professors (Faculty) distributed across academic departments.");
    }
}
