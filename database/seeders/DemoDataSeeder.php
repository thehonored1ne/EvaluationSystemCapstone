<?php

namespace Database\Seeders;

use App\Models\AcademicClass;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Departments (Colleges)
        $ccs = Department::firstOrCreate(
            ['code' => 'CCS'],
            ['name' => 'College of Computer Studies']
        );

        $coed = Department::firstOrCreate(
            ['code' => 'COED'],
            ['name' => 'College of Education']
        );

        // Update Dean seeded in DatabaseSeeder to manage CCS
        $deanEmployee = Employee::where('employee_number', 'DEAN-001')->first();
        if ($deanEmployee) {
            $ccs->dean_id = $deanEmployee->id;
            $ccs->save();
            $deanEmployee->update(['department_id' => $ccs->id]);
        }

        // 2. Create 4 Programs (BSCS, BSIT, BSED, BEED)
        $bscs = Program::firstOrCreate(
            ['code' => 'BSCS'],
            [
                'name' => 'Bachelor of Science in Computer Science',
                'department_id' => $ccs->id,
            ]
        );

        $bsit = Program::firstOrCreate(
            ['code' => 'BSIT'],
            [
                'name' => 'Bachelor of Science in Information Technology',
                'department_id' => $ccs->id,
            ]
        );

        $bsed = Program::firstOrCreate(
            ['code' => 'BSED'],
            [
                'name' => 'Bachelor of Secondary Education',
                'department_id' => $coed->id,
            ]
        );

        $beed = Program::firstOrCreate(
            ['code' => 'BEED'],
            [
                'name' => 'Bachelor of Elementary Education',
                'department_id' => $coed->id,
            ]
        );

        // 3. Create 4 Program Heads (one for each program)
        $programHeadEmployees = [];
        $phData = [
            ['name' => 'BSCS Head', 'program' => $bscs, 'code' => 'BSCS'],
            ['name' => 'BSIT Head', 'program' => $bsit, 'code' => 'BSIT'],
            ['name' => 'BSED Head', 'program' => $bsed, 'code' => 'BSED'],
            ['name' => 'BEED Head', 'program' => $beed, 'code' => 'BEED'],
        ];

        foreach ($phData as $index => $data) {
            $empNumber = 'PH-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            $firstName = explode(' ', $data['name'])[0];
            $lastName = explode(' ', $data['name'])[1];

            $employee = Employee::firstOrCreate(
                ['employee_number' => $empNumber],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'role' => 'program head',
                    'status' => 'active',
                    'department_id' => $data['program']->department_id,
                ]
            );
            $employee->update(['department_id' => $data['program']->department_id]);
            $programHeadEmployees[] = $employee;

            $email = strtolower($data['code']).'.head@example.com';
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'employee_id' => $employee->id,
                    'password' => Hash::make('password'),
                ]
            );

            if (! $user->hasRole('program head')) {
                $user->assignRole('program head');
            }

            // Assign program head to program
            $data['program']->program_head_id = $employee->id;
            $data['program']->save();
        }

        // 4. Create 20 Faculty professors
        $facultyEmployees = [];
        for ($i = 1; $i <= 20; $i++) {
            $empNumber = 'FAC-'.str_pad($i, 4, '0', STR_PAD_LEFT);
            $firstName = fake()->firstName();
            $lastName = fake()->lastName();

            $deptId = ($i <= 9) ? $ccs->id : $coed->id;
            $employee = Employee::firstOrCreate(
                ['employee_number' => $empNumber],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'role' => 'faculty',
                    'status' => 'active',
                    'department_id' => $deptId,
                ]
            );
            $employee->update(['department_id' => $deptId]);
            $facultyEmployees[] = $employee;

            $user = User::firstOrCreate(
                ['email' => strtolower($firstName.'.'.$lastName).'@example.com'],
                [
                    'name' => "$firstName $lastName",
                    'employee_id' => $employee->id,
                    'password' => Hash::make('password'),
                ]
            );

            if (! $user->hasRole('faculty')) {
                $user->assignRole('faculty');
            }
        }

        // 5. Create 40 Students
        $students = [];
        $programsList = [$bscs, $bsit, $bsed, $beed];
        for ($i = 1; $i <= 40; $i++) {
            $studNumber = 'STU-'.str_pad($i, 4, '0', STR_PAD_LEFT);
            $firstName = fake()->firstName();
            $lastName = fake()->lastName();
            $program = $programsList[($i - 1) % 4]; // Distribute evenly across BSCS, BSIT, BSED, BEED
            $yearLevel = fake()->numberBetween(1, 4);
            $sectionSuffix = ($i % 2 === 0) ? 'A' : 'B';
            $section = $program->code.'-'.$yearLevel.$sectionSuffix;

            $student = Student::firstOrCreate(
                ['student_number' => $studNumber],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'program_id' => $program->id,
                    'year_level' => $yearLevel,
                    'section' => $section,
                    'status' => 'regular',
                ]
            );
            $students[] = $student;

            $user = User::firstOrCreate(
                ['email' => strtolower($firstName.'.'.$lastName).'@example.com'],
                [
                    'name' => "$firstName $lastName",
                    'student_id' => $student->id,
                    'password' => Hash::make('password'),
                ]
            );

            if (! $user->hasRole('student')) {
                $user->assignRole('student');
            }
        }

        // 6. Create Subjects
        $subjectsData = [
            // Computer Studies Subjects
            ['code' => 'CS101', 'name' => 'Introduction to Computing', 'units' => 3],
            ['code' => 'CS202', 'name' => 'Data Structures and Algorithms', 'units' => 3],
            ['code' => 'IT102', 'name' => 'Web Development I', 'units' => 3],
            ['code' => 'IT202', 'name' => 'Database Management Systems', 'units' => 3],

            // Education Subjects
            ['code' => 'ED101', 'name' => 'Child and Adolescent Development', 'units' => 3],
            ['code' => 'ED202', 'name' => 'Facilitating Learner-Centered Teaching', 'units' => 3],
            ['code' => 'ED303', 'name' => 'Assessment in Learning I', 'units' => 3],

            // General Education Subjects
            ['code' => 'MATH101', 'name' => 'College Algebra', 'units' => 3],
            ['code' => 'ENGL101', 'name' => 'Purposive Communication', 'units' => 3],
            ['code' => 'HIST101', 'name' => 'Readings in Philippine History', 'units' => 3],
        ];

        $subjects = [];
        foreach ($subjectsData as $sData) {
            $subjects[$sData['code']] = Subject::firstOrCreate(
                ['code' => $sData['code']],
                ['name' => $sData['name'], 'units' => $sData['units']]
            );
        }

        // 7. Create Classes for the active Semester
        $activeSemester = Semester::where('is_active', true)->first();
        if ($activeSemester) {
            // Assign classes to our 20 faculty professors and 4 program heads
            $teachers = array_merge($facultyEmployees, $programHeadEmployees);

            $classesData = [
                // BSCS Classes
                ['subject' => 'CS101', 'section' => 'BSCS-1A', 'teacher' => $teachers[0], 'schedule' => 'Mon/Wed 9:00 AM - 10:30 AM', 'room' => 'Lab 1'],
                ['subject' => 'MATH101', 'section' => 'BSCS-1A', 'teacher' => $teachers[1], 'schedule' => 'Mon/Wed 10:30 AM - 12:00 PM', 'room' => 'Room 302'],
                ['subject' => 'ENGL101', 'section' => 'BSCS-1A', 'teacher' => $teachers[2], 'schedule' => 'Tue/Thu 9:00 AM - 10:30 AM', 'room' => 'Room 201'],
                ['subject' => 'CS202', 'section' => 'BSCS-2A', 'teacher' => $teachers[3], 'schedule' => 'Mon/Wed 1:00 PM - 2:30 PM', 'room' => 'Lab 2'],
                ['subject' => 'IT202', 'section' => 'BSCS-2A', 'teacher' => $teachers[4], 'schedule' => 'Tue/Thu 1:00 PM - 2:30 PM', 'room' => 'Lab 3'],

                // BSIT Classes
                ['subject' => 'IT102', 'section' => 'BSIT-1A', 'teacher' => $teachers[5], 'schedule' => 'Tue/Thu 10:30 AM - 12:00 PM', 'room' => 'Lab 1'],
                ['subject' => 'MATH101', 'section' => 'BSIT-1A', 'teacher' => $teachers[6], 'schedule' => 'Mon/Wed 1:00 PM - 2:30 PM', 'room' => 'Room 302'],
                ['subject' => 'HIST101', 'section' => 'BSIT-1A', 'teacher' => $teachers[7], 'schedule' => 'Tue/Thu 9:00 AM - 10:30 AM', 'room' => 'Room 202'],
                ['subject' => 'IT202', 'section' => 'BSIT-2A', 'teacher' => $teachers[8], 'schedule' => 'Mon/Wed 10:30 AM - 12:00 PM', 'room' => 'Lab 3'],

                // BSED Classes
                ['subject' => 'ED101', 'section' => 'BSED-1A', 'teacher' => $teachers[9], 'schedule' => 'Mon/Wed 9:00 AM - 10:30 AM', 'room' => 'Room 401'],
                ['subject' => 'MATH101', 'section' => 'BSED-1A', 'teacher' => $teachers[10], 'schedule' => 'Mon/Wed 10:30 AM - 12:00 PM', 'room' => 'Room 302'],
                ['subject' => 'ENGL101', 'section' => 'BSED-1A', 'teacher' => $teachers[11], 'schedule' => 'Tue/Thu 9:00 AM - 10:30 AM', 'room' => 'Room 201'],
                ['subject' => 'ED202', 'section' => 'BSED-2A', 'teacher' => $teachers[12], 'schedule' => 'Tue/Thu 1:00 PM - 2:30 PM', 'room' => 'Room 402'],
                ['subject' => 'ED303', 'section' => 'BSED-3A', 'teacher' => $teachers[13], 'schedule' => 'Mon/Wed 1:00 PM - 2:30 PM', 'room' => 'Room 403'],

                // BEED Classes
                ['subject' => 'ED101', 'section' => 'BEED-1A', 'teacher' => $teachers[14], 'schedule' => 'Tue/Thu 10:30 AM - 12:00 PM', 'room' => 'Room 401'],
                ['subject' => 'HIST101', 'section' => 'BEED-1A', 'teacher' => $teachers[15], 'schedule' => 'Mon/Wed 2:30 PM - 4:00 PM', 'room' => 'Room 202'],
                ['subject' => 'ENGL101', 'section' => 'BEED-1A', 'teacher' => $teachers[16], 'schedule' => 'Tue/Thu 9:00 AM - 10:30 AM', 'room' => 'Room 201'],
                ['subject' => 'ED202', 'section' => 'BEED-2A', 'teacher' => $teachers[17], 'schedule' => 'Mon/Wed 10:30 AM - 12:00 PM', 'room' => 'Room 402'],
            ];

            $createdClasses = [];
            foreach ($classesData as $cData) {
                $createdClasses[] = AcademicClass::firstOrCreate(
                    [
                        'subject_id' => $subjects[$cData['subject']]->id,
                        'semester_id' => $activeSemester->id,
                        'section' => $cData['section'],
                    ],
                    [
                        'teacher_id' => $cData['teacher']->id,
                        'schedule' => $cData['schedule'],
                        'room' => $cData['room'],
                    ]
                );
            }

            // Enroll students into their program classes based on program and year level
            foreach ($students as $student) {
                $progCode = $student->program->code;

                // Map program code and year level to appropriate sections
                $targetSections = [];
                if ($progCode === 'BSCS') {
                    if ($student->year_level === 1) {
                        $targetSections = ['BSCS-1A'];
                    } else {
                        $targetSections = ['BSCS-2A'];
                    }
                } elseif ($progCode === 'BSIT') {
                    if ($student->year_level === 1) {
                        $targetSections = ['BSIT-1A'];
                    } else {
                        $targetSections = ['BSIT-2A'];
                    }
                } elseif ($progCode === 'BSED') {
                    if ($student->year_level === 1) {
                        $targetSections = ['BSED-1A'];
                    } elseif ($student->year_level === 2) {
                        $targetSections = ['BSED-2A'];
                    } else {
                        $targetSections = ['BSED-3A'];
                    }
                } elseif ($progCode === 'BEED') {
                    if ($student->year_level === 1) {
                        $targetSections = ['BEED-1A'];
                    } else {
                        $targetSections = ['BEED-2A'];
                    }
                }

                // Filter classes that match the target sections
                $sectionClasses = array_filter($createdClasses, function ($class) use ($targetSections) {
                    return in_array($class->section, $targetSections);
                });

                // Attach student to each class
                foreach ($sectionClasses as $class) {
                    $class->students()->syncWithoutDetaching([$student->id]);
                }
            }
        }
    }
}
