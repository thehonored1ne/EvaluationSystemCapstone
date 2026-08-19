<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles
        $roles = [
            'admin',
            'dean',
            'department head',
            'program head',
            'faculty',
            'student',
            'staff',
        ];

        $roleModels = [];
        foreach ($roles as $role) {
            $roleModels[$role] = Role::firstOrCreate(['name' => $role]);
        }

        // 2. Academic Year & Semester
        $ay = AcademicYear::create([
            'name' => '2026-2027',
            'is_active' => true,
        ]);

        $sem = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => '1st Semester',
            'is_active' => true,
            'is_evaluation_open' => true,
            'evaluation_starts_at' => now()->subDays(5),
            'evaluation_ends_at' => now()->addDays(25),
            'overall_max_points' => 200.00,
            'student_weight' => 40.00,
            'dean_weight' => 20.00,
            'ph_dh_weight' => 20.00,
            'peer_weight' => 15.00,
            'self_weight' => 5.00,
            'superior_weight' => 20.00,
            'upward_student_max_points' => 80.00,
            'peer_max_points' => 30.00,
            'self_max_points' => 10.00,
            'dean_max_points' => 40.00,
            'program_head_max_points' => 40.00,
        ]);

        // 3. Departments (4 Academic + 11 Administrative)
        $academicDeptDefs = [
            ['name' => 'College of Computer Studies', 'code' => 'CCS', 'type' => 'academic'],
            ['name' => 'College of Accountancy', 'code' => 'COA', 'type' => 'academic'],
            ['name' => 'College of Education', 'code' => 'COE', 'type' => 'academic'],
            ['name' => 'College of Business Administration and Entrepreneurship', 'code' => 'CBAE', 'type' => 'academic'],
        ];

        $adminDeptDefs = [
            ['name' => 'Accounting Office', 'code' => 'ACCT', 'type' => 'administrative'],
            ['name' => 'Admission Office', 'code' => 'ADMIS', 'type' => 'administrative'],
            ['name' => 'Clinic', 'code' => 'CLINIC', 'type' => 'administrative'],
            ['name' => 'General Service', 'code' => 'GENSERV', 'type' => 'administrative'],
            ['name' => 'Guidance Office', 'code' => 'GUID', 'type' => 'administrative'],
            ['name' => 'IT Office', 'code' => 'ITOFF', 'type' => 'administrative'],
            ['name' => 'Library', 'code' => 'LIB', 'type' => 'administrative'],
            ['name' => 'Office of Student Affairs', 'code' => 'OSA', 'type' => 'administrative'],
            ['name' => 'Registrar', 'code' => 'REG', 'type' => 'administrative'],
            ['name' => 'Research Community Extension', 'code' => 'RCE', 'type' => 'administrative'],
            ['name' => 'Scholarship Office', 'code' => 'SCHOL', 'type' => 'administrative'],
        ];

        $depts = [];
        foreach (array_merge($academicDeptDefs, $adminDeptDefs) as $dDef) {
            $depts[$dDef['code']] = Department::create($dDef);
        }

        // 4. Programs (13 Programs)
        $programDefs = [
            ['name' => 'BS Information Technology', 'code' => 'BSIT', 'department_id' => $depts['CCS']->id],
            ['name' => 'BS Accountancy', 'code' => 'BSA', 'department_id' => $depts['COA']->id],
            ['name' => 'BS Elementary Education', 'code' => 'BEED', 'department_id' => $depts['COE']->id],
            ['name' => 'BSED Major in English', 'code' => 'BSED-ENG', 'department_id' => $depts['COE']->id],
            ['name' => 'BSED Major in Filipino', 'code' => 'BSED-FIL', 'department_id' => $depts['COE']->id],
            ['name' => 'BSED Major in Social Studies', 'code' => 'BSED-SOC', 'department_id' => $depts['COE']->id],
            ['name' => 'BSED Major in Values Education', 'code' => 'BSED-VAL', 'department_id' => $depts['COE']->id],
            ['name' => 'Bachelor of Secondary Education', 'code' => 'BSED', 'department_id' => $depts['COE']->id],
            ['name' => 'TEACHING CERTIFICATE PROGRAM', 'code' => 'TCP', 'department_id' => $depts['COE']->id],
            ['name' => 'BS Entrepreneurship', 'code' => 'BSENT', 'department_id' => $depts['CBAE']->id],
            ['name' => 'BSBA Major in Financial Management', 'code' => 'BSBA-FM', 'department_id' => $depts['CBAE']->id],
            ['name' => 'BSBA Major in Human Resource Development Management', 'code' => 'BSBA-HR', 'department_id' => $depts['CBAE']->id],
            ['name' => 'BSBA Major in Marketing Management', 'code' => 'BSBA-MM', 'department_id' => $depts['CBAE']->id],
        ];

        $programs = [];
        foreach ($programDefs as $pDef) {
            $programs[$pDef['code']] = Program::create($pDef);
        }

        $passwordHash = Hash::make('password');

        // 5. System Administrator
        $adminEmp = Employee::create([
            'employee_number' => 'ADMIN-001',
            'first_name' => 'System',
            'last_name' => 'Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $adminUser = User::create([
            'name' => 'System Admin',
            'email' => 'dion.areglo1234@gmail.com',
            'employee_id' => $adminEmp->id,
            'password' => $passwordHash,
            'is_active' => true,
        ]);
        $adminUser->assignRole('admin');

        // 6. Dean (1)
        $deanEmp = Employee::create([
            'employee_number' => 'DEAN-001',
            'first_name' => 'Maricel',
            'last_name' => 'Santos',
            'middle_name' => 'G.',
            'role' => 'dean',
            'status' => 'active',
        ]);
        $deanUser = User::create([
            'name' => 'Dr. Maricel G. Santos',
            'email' => 'dean@grc.edu.ph',
            'employee_id' => $deanEmp->id,
            'password' => $passwordHash,
            'is_active' => true,
        ]);
        $deanUser->assignRole('dean');

        // 7. Program Heads (4) - For CCS, COA, COE, CBAE
        $programHeadDefs = [
            ['emp_no' => 'PH-001', 'first' => 'Rommel', 'last' => 'Lei', 'dept' => 'CCS', 'email' => 'ph.ccs@grc.edu.ph'],
            ['emp_no' => 'PH-002', 'first' => 'Roderick', 'last' => 'Ronidel', 'dept' => 'COA', 'email' => 'ph.coa@grc.edu.ph'],
            ['emp_no' => 'PH-003', 'first' => 'Jennifer', 'last' => 'Fronda-Tan', 'dept' => 'COE', 'email' => 'ph.coe@grc.edu.ph'],
            ['emp_no' => 'PH-004', 'first' => 'Telesforo', 'last' => 'Bernabe Jr.', 'dept' => 'CBAE', 'email' => 'ph.cbae@grc.edu.ph'],
        ];

        foreach ($programHeadDefs as $phDef) {
            $phEmp = Employee::create([
                'employee_number' => $phDef['emp_no'],
                'first_name' => $phDef['first'],
                'last_name' => $phDef['last'],
                'role' => 'program head',
                'status' => 'active',
                'department_id' => $depts[$phDef['dept']]->id,
            ]);

            $phUser = User::create([
                'name' => "Prof. {$phDef['first']} {$phDef['last']}",
                'email' => $phDef['email'],
                'employee_id' => $phEmp->id,
                'password' => $passwordHash,
                'is_active' => true,
            ]);
            $phUser->assignRole('program head');

            // Update department and program leadership pointers
            $depts[$phDef['dept']]->update(['program_head_id' => $phEmp->id]);
            Program::where('department_id', $depts[$phDef['dept']]->id)->update(['program_head_id' => $phEmp->id]);
        }

        // 8. Department Heads (11) - One for each of the 11 Administrative Departments
        $deptHeadDefs = [
            ['emp_no' => 'DH-001', 'first' => 'Edgar', 'last' => 'Sarabia', 'dept' => 'ACCT', 'email' => 'dh.accounting@grc.edu.ph'],
            ['emp_no' => 'DH-002', 'first' => 'Cristy', 'last' => 'Tolentino', 'dept' => 'ADMIS', 'email' => 'dh.admission@grc.edu.ph'],
            ['emp_no' => 'DH-003', 'first' => 'Carmela', 'last' => 'Vitales', 'dept' => 'CLINIC', 'email' => 'dh.clinic@grc.edu.ph'],
            ['emp_no' => 'DH-004', 'first' => 'Reymer', 'last' => 'Duco', 'dept' => 'GENSERV', 'email' => 'dh.genserv@grc.edu.ph'],
            ['emp_no' => 'DH-005', 'first' => 'Jean', 'last' => 'Marquez', 'dept' => 'GUID', 'email' => 'dh.guidance@grc.edu.ph'],
            ['emp_no' => 'DH-006', 'first' => 'Jay', 'last' => 'Evangelista', 'dept' => 'ITOFF', 'email' => 'dh.it@grc.edu.ph'],
            ['emp_no' => 'DH-007', 'first' => 'Sarah', 'last' => 'Evangelista', 'dept' => 'LIB', 'email' => 'dh.library@grc.edu.ph'],
            ['emp_no' => 'DH-008', 'first' => 'Ryan Romnick', 'last' => 'Sanchez', 'dept' => 'OSA', 'email' => 'dh.osa@grc.edu.ph'],
            ['emp_no' => 'DH-009', 'first' => 'Rowena', 'last' => 'Calangian', 'dept' => 'REG', 'email' => 'dh.registrar@grc.edu.ph'],
            ['emp_no' => 'DH-010', 'first' => 'Danilo', 'last' => 'Baraquiel', 'dept' => 'RCE', 'email' => 'dh.rce@grc.edu.ph'],
            ['emp_no' => 'DH-011', 'first' => 'Mary Grace', 'last' => 'Mendiola', 'dept' => 'SCHOL', 'email' => 'dh.scholarship@grc.edu.ph'],
        ];

        foreach ($deptHeadDefs as $dhDef) {
            $dhEmp = Employee::create([
                'employee_number' => $dhDef['emp_no'],
                'first_name' => $dhDef['first'],
                'last_name' => $dhDef['last'],
                'role' => 'department head',
                'status' => 'active',
                'department_id' => $depts[$dhDef['dept']]->id,
            ]);

            $dhUser = User::create([
                'name' => "{$dhDef['first']} {$dhDef['last']}",
                'email' => $dhDef['email'],
                'employee_id' => $dhEmp->id,
                'password' => $passwordHash,
                'is_active' => true,
            ]);
            $dhUser->assignRole('department head');

            $depts[$dhDef['dept']]->update(['department_head_id' => $dhEmp->id]);
        }

        // 9. Faculty Professors (50) - Distributed across 4 academic depts
        $facultyData = [
            // CCS (13 Faculty)
            ['first' => 'Jerome', 'last' => 'Macinas', 'dept' => 'CCS'],
            ['first' => 'Carmela', 'last' => 'Punzalan', 'dept' => 'CCS'],
            ['first' => 'Patricia', 'last' => 'Fernandez', 'dept' => 'CCS'],
            ['first' => 'Jude', 'last' => 'Salonga', 'dept' => 'CCS'],
            ['first' => 'Krizza', 'last' => 'Olvis', 'dept' => 'CCS'],
            ['first' => 'Mark Joseph', 'last' => 'Salazar', 'dept' => 'CCS'],
            ['first' => 'Rea', 'last' => 'Pabelario', 'dept' => 'CCS'],
            ['first' => 'Aielene', 'last' => 'Gallen', 'dept' => 'CCS'],
            ['first' => 'Christian', 'last' => 'Alariao', 'dept' => 'CCS'],
            ['first' => 'Kenneth', 'last' => 'Tanael', 'dept' => 'CCS'],
            ['first' => 'Clarisse', 'last' => 'Sunga', 'dept' => 'CCS'],
            ['first' => 'Ronald', 'last' => 'Umali', 'dept' => 'CCS'],
            ['first' => 'Joana', 'last' => 'Narciso', 'dept' => 'CCS'],

            // COA (12 Faculty)
            ['first' => 'Aaron', 'last' => 'Alonzo', 'dept' => 'COA'],
            ['first' => 'Henry', 'last' => 'Corrales', 'dept' => 'COA'],
            ['first' => 'Gezzle', 'last' => 'Marter', 'dept' => 'COA'],
            ['first' => 'Mark Anthony', 'last' => 'Soriano', 'dept' => 'COA'],
            ['first' => 'Michael Dave', 'last' => 'Sangco', 'dept' => 'COA'],
            ['first' => 'Rechel', 'last' => 'Arrabis', 'dept' => 'COA'],
            ['first' => 'Efren', 'last' => 'Dela Cruz', 'dept' => 'COA'],
            ['first' => 'Joland', 'last' => 'Layos', 'dept' => 'COA'],
            ['first' => 'Berly', 'last' => 'Gagarin', 'dept' => 'COA'],
            ['first' => 'Gabriel', 'last' => 'Palada', 'dept' => 'COA'],
            ['first' => 'Rommel', 'last' => 'Antonio', 'dept' => 'COA'],
            ['first' => 'Daisy', 'last' => 'Cuadra', 'dept' => 'COA'],

            // COE (13 Faculty)
            ['first' => 'Arnel', 'last' => 'Peralta', 'dept' => 'COE'],
            ['first' => 'Joy', 'last' => 'Badilla', 'dept' => 'COE'],
            ['first' => 'Isabel', 'last' => 'Garchitorena', 'dept' => 'COE'],
            ['first' => 'Ladislao', 'last' => 'Mercader', 'dept' => 'COE'],
            ['first' => 'Cressida', 'last' => 'Montebon', 'dept' => 'COE'],
            ['first' => 'Reymond', 'last' => 'Cuison', 'dept' => 'COE'],
            ['first' => 'Marvie', 'last' => 'Parto', 'dept' => 'COE'],
            ['first' => 'Lourivie', 'last' => 'Nabuab', 'dept' => 'COE'],
            ['first' => 'Rommel', 'last' => 'Bravo', 'dept' => 'COE'],
            ['first' => 'Lenard', 'last' => 'Tulod', 'dept' => 'COE'],
            ['first' => 'Linda', 'last' => 'Varca', 'dept' => 'COE'],
            ['first' => 'Vivian', 'last' => 'Acosta', 'dept' => 'COE'],
            ['first' => 'Francheska', 'last' => 'De Asis', 'dept' => 'COE'],

            // CBAE (12 Faculty)
            ['first' => 'Lovely Ann', 'last' => 'Esclarez', 'dept' => 'CBAE'],
            ['first' => 'Cristy', 'last' => 'Tolentino', 'dept' => 'CBAE'],
            ['first' => 'Michael John', 'last' => 'Silverio', 'dept' => 'CBAE'],
            ['first' => 'Dennis', 'last' => 'De Guzman', 'dept' => 'CBAE'],
            ['first' => 'John Paul', 'last' => 'Dela Cruz', 'dept' => 'CBAE'],
            ['first' => 'Alvin', 'last' => 'Bautista', 'dept' => 'CBAE'],
            ['first' => 'Rowena', 'last' => 'Calangian', 'dept' => 'CBAE'],
            ['first' => 'Reymer', 'last' => 'Duco', 'dept' => 'CBAE'],
            ['first' => 'Jean', 'last' => 'Marquez', 'dept' => 'CBAE'],
            ['first' => 'Jay', 'last' => 'Evangelista', 'dept' => 'CBAE'],
            ['first' => 'Sarah', 'last' => 'Evangelista', 'dept' => 'CBAE'],
            ['first' => 'Mary Grace', 'last' => 'Mendiola', 'dept' => 'CBAE'],
        ];

        $facultyEmps = [];
        foreach ($facultyData as $idx => $fData) {
            $num = str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);
            $fEmp = Employee::create([
                'employee_number' => "FAC-{$num}",
                'first_name' => $fData['first'],
                'last_name' => $fData['last'],
                'role' => 'faculty',
                'status' => 'active',
                'department_id' => $depts[$fData['dept']]->id,
            ]);

            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z]/', '', $fData['first']));
            $cleanLast = strtolower(preg_replace('/[^a-zA-Z]/', '', $fData['last']));
            $fUser = User::create([
                'name' => "{$fData['first']} {$fData['last']}",
                'email' => "{$cleanFirst}.{$cleanLast}{$num}@grc.edu.ph",
                'employee_id' => $fEmp->id,
                'password' => $passwordHash,
                'is_active' => true,
            ]);
            $fUser->assignRole('faculty');

            $facultyEmps[] = $fEmp;
        }

        // 10. Staff Members (57) - Distributed across 11 Administrative Departments
        $adminDeptKeys = array_keys($adminDeptDefs);
        $staffFirstNames = [
            'Maria', 'Juan', 'Jose', 'Mark', 'Mary', 'Ana', 'Grace', 'John', 'Paul', 'Rhea',
            'Angelica', 'Carlo', 'Christian', 'Daniel', 'Erica', 'Francis', 'Gabriel', 'Hannah', 'Ian', 'Jenny',
            'Kevin', 'Leah', 'Michael', 'Nicole', 'Oliver', 'Patricia', 'Quirino', 'Rachel', 'Samuel', 'Theresa',
            'Ulysses', 'Vanessa', 'William', 'Ximena', 'Yolanda', 'Zandro', 'Alfredo', 'Bernadette', 'Camille', 'Dexter',
            'Emmanuel', 'Felicia', 'Gino', 'Hazel', 'Ivan', 'Jocelyn', 'Karl', 'Lorraine', 'Manuel', 'Nadine',
            'Orlando', 'Pamela', 'Rico', 'Sheila', 'Tomas', 'Ursula', 'Victor',
        ];
        $staffLastNames = [
            'Reyes', 'Santos', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andrada',
            'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Castro', 'Rivera', 'Aquino', 'Navarro', 'Salazar', 'Mercado',
            'Pascual', 'Dela Rosa', 'Domingo', 'Valdez', 'Morales', 'Guerrero', 'Roxas', 'Soriano', 'Guzman', 'Aguilar',
            'Cordero', 'Del Rosario', 'Santiago', 'Corpuz', 'Enriquez', 'Magno', 'Padilla', 'Pineda', 'Sarmiento', 'Tolentino',
            'Velasco', 'Alcantara', 'Bernardo', 'Cervantes', 'David', 'Espiritu', 'Fajardo', 'Gallardo', 'Hidalgo', 'Ignacio',
            'Javier', 'Lacson', 'Manalo', 'Natividad', 'Ortega', 'Panganiban', 'Quizon',
        ];

        $adminDeptCodes = array_keys($depts);
        $adminOnlyCodes = array_slice($adminDeptCodes, 4); // Only the 11 admin depts

        for ($i = 0; $i < 57; $i++) {
            $num = str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $deptCode = $adminOnlyCodes[$i % count($adminOnlyCodes)];
            $fn = $staffFirstNames[$i];
            $ln = $staffLastNames[$i];

            $sEmp = Employee::create([
                'employee_number' => "STF-{$num}",
                'first_name' => $fn,
                'last_name' => $ln,
                'role' => 'staff',
                'status' => 'active',
                'department_id' => $depts[$deptCode]->id,
            ]);

            $sUser = User::create([
                'name' => "{$fn} {$ln}",
                'email' => strtolower("{$fn}.{$ln}{$num}@staff.grc.edu.ph"),
                'employee_id' => $sEmp->id,
                'password' => $passwordHash,
                'is_active' => true,
            ]);
            $sUser->assignRole('staff');
        }

        // 11. Evaluation Criteria & Questions Setup
        $criteriaSetup = [
            // Student Evaluation (80 pts / 40% weight in GRC Scorecard)
            [
                'evaluation_type' => 'upward_student',
                'name' => 'MASTERY OF THE SUBJECT',
                'order' => 1,
                'max_points' => 32.00,
                'questions' => [
                    'Explains the subject matter clearly.',
                    'Emphasizes important points of the lesson.',
                    'Answers students questions with clarity and throughness.',
                    'Comes to class prepared and seldom reads his lectures. notes and books in class.',
                    'Gives explanation and examples in addition to that found in the book.',
                    'Recommends good sources of reference materials.',
                ],
            ],
            [
                'evaluation_type' => 'upward_student',
                'name' => 'TEACHING SKILLS AND CLASS MANAGEMENT',
                'order' => 2,
                'max_points' => 32.00,
                'questions' => [
                    'Provides the students with a course syllabus and explains the objectives of the course and / or lesson for the day. Impliments teaching methods indicated in the course online.',
                    'Orients the students on the grading system and requirements of the course.',
                    'Motivates students to arouse their interest in the subject matter and relates present lesson to the past lesson.',
                    'Utilizes instructural aids, devices, examples and illustration of the subject matter.',
                    'Encourages students to react, discuss, seek clarifications and ask questions. Respects personal opinions and comments of the students.',
                    'Summarizes the lessons at the end of the class or at the end of every chapter.',
                    'Assigns challenging learning task.',
                    'Maintains classroom discipline and discourages cheating in class. Able to control and maintain order in class.',
                    'Returns to students corrected test papers and discusses answers within 3 class meetings.',
                    'Talks fluently and has a good command of the language of instruction.',
                    'Commends good work and gives encouraging suggestions for better student performance.',
                ],
            ],
            [
                'evaluation_type' => 'upward_student',
                'name' => 'PERSONAL TRAITS',
                'order' => 3,
                'max_points' => 16.00,
                'questions' => [
                    'Appropriate manner of dressing, neat, tidy and well-groomed.',
                    'Keeps proper balance of humor to maintain students interest and attention.',
                    'Objectivity: Deals with student fairly and objectively.',
                    'Punctuality: Comes to class on time.',
                    'Attendance: Attends class regularly.',
                ],
            ],

            // Self Evaluation (10 pts / 5% weight in GRC Scorecard)
            [
                'evaluation_type' => 'self',
                'name' => 'SELF EVALUATION',
                'order' => 1,
                'max_points' => 10.00,
                'questions' => [
                    'Helps promote the interest and welfare of GRC and faithfully complies with all its policies, rules and regulations.',
                    'Gets along well with fellow faculty and school officials.',
                    'Can be relied upon whenever there are concerns.',
                    'Is receptive of feedback (non-defensive).',
                    'Provides feedback constructively.',
                    'Communicates ideas effectively.',
                    'Maintains focus on team goals.',
                    'Encourages innovation among team members.',
                ],
            ],

            // Dean Evaluation (40 pts / 20% weight in GRC Scorecard)
            [
                'evaluation_type' => 'dean',
                'name' => 'MASTERY OF THE SUBJECT',
                'order' => 1,
                'max_points' => 12.00,
                'questions' => [
                    'Explains the subject matter clearly.',
                    'Emphasizes important points of the lesson.',
                    'Answers students questions with clarity and throughness.',
                    'Comes to class prepared and seldom reads his lectures. notes and books in class.',
                    'Gives explanation and examples in addition to that found in the book.',
                    'Recommends good sources of reference materials.',
                ],
            ],
            [
                'evaluation_type' => 'dean',
                'name' => 'TEACHING SKILLS AND CLASS MANAGEMENT',
                'order' => 2,
                'max_points' => 12.00,
                'questions' => [
                    'Provides the students with a course syllabus and explains the objectives of the course and / or lesson for the day. Impliments teaching methods indicated in the course online.',
                    'Orients the students on the grading system and requirements of the course.',
                    'Motivates students to arouse their interest in the subject matter and relates present lesson to the past lesson.',
                    'Utilizes instructural aids, devices, examples and illustration of the subject matter.',
                    'Encourages students to react, discuss, seek clarifications and ask questions. Respects personal opinions and comments of the students.',
                    'Summarizes the lessons at the end of the class or at the end of every chapter.',
                    'Assigns challenging learning task.',
                    'Maintains classroom discipline and discourages cheating in class. Able to control and maintain order in class.',
                    'Returns to students corrected test papers and discusses answers within 3 class meetings.',
                    'Talks fluently and has a good command of the language of instruction.',
                    'Commends good work and gives encouraging suggestions for better student performance.',
                ],
            ],
            [
                'evaluation_type' => 'dean',
                'name' => 'PERSONAL TRAITS',
                'order' => 3,
                'max_points' => 8.00,
                'questions' => [
                    'Appropriate manner of dressing, neat, tidy and well-groomed.',
                    'Keeps proper balance of humor to maintain students interest and attention.',
                    'Comes to classroom regularly on time.',
                    'Deals with students fairly and objectively.',
                ],
            ],
            [
                'evaluation_type' => 'dean',
                'name' => 'OTHER FACTORS',
                'order' => 4,
                'max_points' => 8.00,
                'questions' => [
                    'Exemplifies the mission, vision, core values of GRC. Follows rules and regulations, policies of the institution.',
                    'Shows respect to superior and colleagues.',
                    'Shows cooperation in curricular and co-curricular activities.',
                    'Shows responsibility in the performance of designated functions and puts extra time and effort in the completion of his tasks.',
                    'Shows initiative, resourcefulness and enthusiasm.',
                    'Maintains harmonious relationship with students,  colleagues, and with the entire GRC community.',
                    'Shows honesty in all his / her dealings with students, colleagues and GRC officials.',
                    'Submits reports and requirements promptly.',
                    'Attends GRC meetings and activities regularly.',
                ],
            ],

            // Program Head Evaluation (40 pts / 20% weight in GRC Scorecard)
            [
                'evaluation_type' => 'program_head',
                'name' => 'MASTERY OF THE SUBJECT',
                'order' => 1,
                'max_points' => 12.00,
                'questions' => [
                    'Explains the subject matter clearly.',
                    'Emphasizes important points of the lesson.',
                    'Answers students questions with clarity and throughness.',
                    'Comes to class prepared and seldom reads his lectures. notes and books in class.',
                    'Gives explanation and examples in addition to that found in the book.',
                    'Recommends good sources of reference materials.',
                ],
            ],
            [
                'evaluation_type' => 'program_head',
                'name' => 'TEACHING SKILLS AND CLASS MANAGEMENT',
                'order' => 2,
                'max_points' => 12.00,
                'questions' => [
                    'Provides the students with a course syllabus and explains the objectives of the course and / or lesson for the day. Impliments teaching methods indicated in the course online.',
                    'Orients the students on the grading system and requirements of the course.',
                    'Motivates students to arouse their interest in the subject matter and relates present lesson to the past lesson.',
                    'Utilizes instructural aids, devices, examples and illustration of the subject matter.',
                    'Encourages students to react, discuss, seek clarifications and ask questions. Respects personal opinions and comments of the students.',
                    'Summarizes the lessons at the end of the class or at the end of every chapter.',
                    'Assigns challenging learning task.',
                    'Maintains classroom discipline and discourages cheating in class. Able to control and maintain order in class.',
                    'Returns to students corrected test papers and discusses answers within 3 class meetings.',
                    'Talks fluently and has a good command of the language of instruction.',
                    'Commends good work and gives encouraging suggestions for better student performance.',
                ],
            ],
            [
                'evaluation_type' => 'program_head',
                'name' => 'PERSONAL TRAITS',
                'order' => 3,
                'max_points' => 8.00,
                'questions' => [
                    'Appropriate manner of dressing, neat, tidy and well-groomed.',
                    'Keeps proper balance of humor to maintain students interest and attention.',
                    'Comes to classroom regularly on time.',
                    'Deals with students fairly and objectively.',
                ],
            ],
            [
                'evaluation_type' => 'program_head',
                'name' => 'OTHER FACTORS',
                'order' => 4,
                'max_points' => 8.00,
                'questions' => [
                    'Exemplifies the mission, vision, core values of GRC. Follows rules and regulations, policies of the institution.',
                    'Shows respect to superior and colleagues.',
                    'Shows cooperation in curricular and co-curricular activities.',
                    'Shows responsibility in the performance of designated functions and puts extra time and effort in the completion of his tasks.',
                    'Shows initiative, resourcefulness and enthusiasm.',
                    'Maintains harmonious relationship with students,  colleagues, and with the entire GRC community.',
                    'Shows honesty in all his / her dealings with students, colleagues and GRC officials.',
                    'Submits reports and requirements promptly.',
                    'Attends GRC meetings and activities regularly.',
                ],
            ],

            // Peer Evaluation (30 pts / 15% weight in GRC Scorecard)
            [
                'evaluation_type' => 'peer',
                'name' => 'PROFESSIONAL COMPETENCE',
                'order' => 1,
                'max_points' => 10.00,
                'questions' => [
                    'Demonstrates competence and expertise in their subject discipline.',
                    'Shares instructional resources, materials, and best practices with colleagues.',
                    'Displays commitment to professional growth and institutional standards.',
                    'Submits departmental requirements and grades on schedule.',
                ],
            ],
            [
                'evaluation_type' => 'peer',
                'name' => 'COLLEGIALITY AND INTERPERSONAL RELATIONS',
                'order' => 2,
                'max_points' => 10.00,
                'questions' => [
                    'Cooperates actively and willingly in departmental tasks and school committees.',
                    'Communicates ideas constructively and listens respectfully to fellow colleagues.',
                    'Fosters a positive, respectful, and harmonious workplace environment.',
                    'Shows willingness to support colleagues when assistance is needed.',
                ],
            ],
            [
                'evaluation_type' => 'peer',
                'name' => 'ETHICAL CONDUCT AND CORE VALUES',
                'order' => 3,
                'max_points' => 10.00,
                'questions' => [
                    'Exemplifies the core values, mission, and vision of GRC in daily conduct.',
                    'Treats faculty, staff, and students with fairness, dignity, and respect.',
                    'Handles constructive feedback non-defensively and with emotional maturity.',
                    'Upholds honesty, integrity, and confidentiality in all institutional matters.',
                ],
            ],

            // Department Head Evaluation (50 pts / Downward Evaluation for Administrative Staff)
            [
                'evaluation_type' => 'department_head',
                'name' => 'JOB KNOWLEDGE AND QUALITY OF WORK',
                'order' => 1,
                'max_points' => 12.50,
                'questions' => [
                    'Demonstrates thorough knowledge of assigned office duties and responsibilities.',
                    'Produces accurate, well-organized, and dependable outputs.',
                    'Effectively utilizes office equipment, systems, and software tools.',
                    'Maintains orderly, updated, and secure departmental records.',
                ],
            ],
            [
                'evaluation_type' => 'department_head',
                'name' => 'CUSTOMER SERVICE AND COMMUNICATION',
                'order' => 2,
                'max_points' => 12.50,
                'questions' => [
                    'Attends to students, faculty, and visitors with promptness and courtesy.',
                    'Communicates clearly, professionally, and respectfully in person and in writing.',
                    'Listens attentively to inquiries and resolves complaints constructively.',
                    'Upholds a welcoming and service-oriented atmosphere in the office.',
                ],
            ],
            [
                'evaluation_type' => 'department_head',
                'name' => 'INITIATIVE AND TIME MANAGEMENT',
                'order' => 3,
                'max_points' => 12.50,
                'questions' => [
                    'Completes assigned tasks and deliverables within specified deadlines.',
                    'Shows initiative and resourcefulness in addressing daily office challenges.',
                    'Prioritizes workload effectively and requires minimal supervision.',
                    'Adapts readily to new office procedures and urgent departmental assignments.',
                ],
            ],
            [
                'evaluation_type' => 'department_head',
                'name' => 'ATTENDANCE AND PROFESSIONAL ETHICS',
                'order' => 4,
                'max_points' => 12.50,
                'questions' => [
                    'Comes to work on time and maintains regular attendance.',
                    'Follows the official GRC dress code and workplace grooming standards.',
                    'Observes honesty, ethical behavior, and confidentiality in office records.',
                    'Demonstrates cooperation, respect, and teamwork with fellow staff members.',
                ],
            ],

            // Superior / Upward Employee Evaluation (30 pts / Upward Evaluation by Subordinates)
            [
                'evaluation_type' => 'upward_employee',
                'name' => 'LEADERSHIP AND STRATEGIC DIRECTION',
                'order' => 1,
                'max_points' => 10.00,
                'questions' => [
                    'Provides clear goals, expectations, and priorities for the department.',
                    'Plans and organizes departmental projects and workflows systematically.',
                    'Leads by example in upholding institutional standards and GRC core values.',
                    'Encourages innovation, collaboration, and continuous operational improvement.',
                ],
            ],
            [
                'evaluation_type' => 'upward_employee',
                'name' => 'FAIRNESS AND DECISION MAKING',
                'order' => 2,
                'max_points' => 10.00,
                'questions' => [
                    'Makes objective, fair, and transparent administrative decisions.',
                    'Listens receptively to suggestions and feedback before making changes.',
                    'Resolves employee concerns and workplace issues impartially.',
                    'Treats all team members with equity, respect, and without favoritism.',
                ],
            ],
            [
                'evaluation_type' => 'upward_employee',
                'name' => 'COMMUNICATION AND TEAM SUPPORT',
                'order' => 3,
                'max_points' => 10.00,
                'questions' => [
                    'Keeps the department informed about institutional policies and updates.',
                    'Provides constructive feedback, timely guidance, and recognition for good work.',
                    'Is accessible and supportive when team members need guidance or assistance.',
                    'Fosters an encouraging environment that promotes employee growth and well-being.',
                ],
            ],
        ];

        foreach ($criteriaSetup as $cData) {
            $criterion = EvaluationCriterion::create([
                'evaluation_type' => $cData['evaluation_type'],
                'name' => $cData['name'],
                'order' => $cData['order'],
                'max_points' => $cData['max_points'],
            ]);

            foreach ($cData['questions'] as $qIndex => $qText) {
                EvaluationQuestion::create([
                    'criterion_id' => $criterion->id,
                    'question_text' => $qText,
                    'order' => $qIndex + 1,
                    'is_active' => true,
                ]);
            }
        }

        // 12. Subjects Master Catalog
        $subjectsData = [
            // General / Cross-disciplinary
            ['code' => 'UNDSELF', 'name' => 'Understanding the Self/Pagunawa sa Sarili', 'units' => 3],
            ['code' => 'PHILHIST', 'name' => 'Readings in Philippine History', 'units' => 3],
            ['code' => 'PURPCOMM', 'name' => 'Purposive Communication', 'units' => 3],
            ['code' => 'KOMFIL', 'name' => 'Kontekstwalisadong Komunikasyon sa Filipino', 'units' => 3],
            ['code' => 'MATHWRLD', 'name' => 'Mathematics in the Modern World', 'units' => 3],
            ['code' => 'MATHINV', 'name' => 'Mathematics of Investment', 'units' => 3],
            ['code' => 'NSTP 1', 'name' => 'National Service Training Program 1', 'units' => 3],
            ['code' => 'PATHFIT1', 'name' => 'Physical Activity Towards Health and Fitness', 'units' => 2],
            ['code' => 'PATHFIT3', 'name' => 'Dance and Fitness Wellness', 'units' => 2],
            ['code' => 'LEAD 1', 'name' => 'Leadership 1', 'units' => 1.5],
            ['code' => 'LEAD 3', 'name' => 'Leadership 3', 'units' => 1.5],
            ['code' => 'LEAD 5', 'name' => 'Leadership 5', 'units' => 1.5],
            ['code' => 'LEAD 7', 'name' => 'Leadership 7', 'units' => 1.5],
            ['code' => 'ARTAPP', 'name' => 'Art Appreciation', 'units' => 3],
            ['code' => 'ETHICS', 'name' => 'Ethics', 'units' => 3],
            ['code' => 'ENVISCI', 'name' => 'Environmental Science', 'units' => 3],
            ['code' => 'SOSLIT', 'name' => 'Sosyedad at Literatura', 'units' => 3],
            ['code' => 'RIZAL', 'name' => 'Life and Works of Dr. Jose Rizal', 'units' => 3],
            ['code' => 'CONWRLD', 'name' => 'The Contemporary World', 'units' => 3],

            // CCS
            ['code' => 'ITC', 'name' => 'Introduction to Computing LEC', 'units' => 2],
            ['code' => 'ITCL', 'name' => 'Introduction to Computing LAB', 'units' => 1],
            ['code' => 'ITP1', 'name' => 'Computer Hardware and Troubleshooting LEC', 'units' => 2],
            ['code' => 'ITP1L', 'name' => 'Computer Hardware and Troubleshooting LAB', 'units' => 1],
            ['code' => 'ITP2', 'name' => 'Fundamentals of Programming LEC', 'units' => 2],
            ['code' => 'ITP2L', 'name' => 'Fundamentals of Programming LAB', 'units' => 1],
            ['code' => 'AVE', 'name' => 'Animation and Video Editing LEC', 'units' => 2],
            ['code' => 'AVEL', 'name' => 'Animation and Video Editing LAB', 'units' => 1],
            ['code' => 'CPROG2', 'name' => 'Computer Programming 2 LEC', 'units' => 2],
            ['code' => 'CPROG2L', 'name' => 'Computer Programming 2 LAB', 'units' => 1],
            ['code' => 'DBMSYS', 'name' => 'Database Management System LEC', 'units' => 2],
            ['code' => 'DBMSYSL', 'name' => 'Database Management System LAB', 'units' => 1],
            ['code' => 'IPT1', 'name' => 'Integrative Programming Technologies 1 LEC', 'units' => 2],
            ['code' => 'IPT1L', 'name' => 'Integrative Programming Technologies 1 LAB', 'units' => 1],
            ['code' => 'NW1', 'name' => 'Networking 1 LEC', 'units' => 2],
            ['code' => 'NW1L', 'name' => 'Networking 1 LAB', 'units' => 1],
            ['code' => 'WST', 'name' => 'Web System and Technologies LEC', 'units' => 2],
            ['code' => 'WSTL', 'name' => 'Web System and Technologies LAB', 'units' => 1],
            ['code' => 'BMC', 'name' => 'Basic Mobile Computing LEC', 'units' => 2],
            ['code' => 'BMCL', 'name' => 'Basic Mobile Computing LAB', 'units' => 1],
            ['code' => 'CAO', 'name' => 'Computer Architecture and Organization LEC', 'units' => 2],
            ['code' => 'CAOL', 'name' => 'Computer Architecture and Organization LAB', 'units' => 1],
            ['code' => 'DMATH', 'name' => 'Discrete Mathematics', 'units' => 3],
            ['code' => 'PRELEC2', 'name' => 'Professional Elective 2 LEC', 'units' => 2],
            ['code' => 'PRELEC2L', 'name' => 'Professional Elective 2 LAB', 'units' => 1],
            ['code' => 'PT', 'name' => 'Platform Technologies LEC', 'units' => 2],
            ['code' => 'PTL', 'name' => 'Platform Technologies LAB', 'units' => 1],
            ['code' => 'SIA2', 'name' => 'System Integration and Architecture 2 LEC', 'units' => 2],
            ['code' => 'SIA2L', 'name' => 'System Integration and Architecture 2 LAB', 'units' => 1],
            ['code' => 'BUSANA', 'name' => 'Business Analytics', 'units' => 3],
            ['code' => 'CAPS2', 'name' => 'Capstone Project and Research 2 LEC', 'units' => 2],
            ['code' => 'CAPS2L', 'name' => 'Capstone Project and Research 2 LAB', 'units' => 1],
            ['code' => 'IAS2', 'name' => 'Information Assurance and Security 2 LEC', 'units' => 2],
            ['code' => 'IAS2L', 'name' => 'Information Assurance and Security 2 LAB', 'units' => 1],
            ['code' => 'SPI', 'name' => 'Social and Professional Issues', 'units' => 3],

            // COA
            ['code' => 'FINACC', 'name' => 'FINANCIAL ACCOUNTING AND REPORTING', 'units' => 3],
            ['code' => 'FUNDACC 1', 'name' => 'FUNDAMENTALS OF ACCOUNTING 1 & 2', 'units' => 3],
            ['code' => 'MANECO', 'name' => 'MANAGERIAL ECONOMICS', 'units' => 3],
            ['code' => 'QM-TQM', 'name' => 'OPERATIONS MANAGEMENT AND TQM', 'units' => 3],
            ['code' => 'BLAWREG', 'name' => 'BUSINESS LAW AND REGULATIONS', 'units' => 3],
            ['code' => 'INCTAX', 'name' => 'INCOME TAXATION', 'units' => 3],
            ['code' => 'INTACC 2', 'name' => 'INTERMEDIATE ACCOUNTING 2', 'units' => 3],
            ['code' => 'IT-ATB', 'name' => 'IT APPLICATION TOOLS IN BUSINESS', 'units' => 3],
            ['code' => 'MANSCI', 'name' => 'MANAGEMENT SCIENCE', 'units' => 3],
            ['code' => 'SCOSMAN', 'name' => 'STRATEGIC COST MANAGEMENT', 'units' => 3],
            ['code' => 'AACAP 1', 'name' => 'AUDITING & ASSURANCE: CONCEPTS AND PRINCIPLES 1', 'units' => 3],
            ['code' => 'AAPRIN', 'name' => 'AUDITING & ASSURANCE PRINCIPLES', 'units' => 3],
            ['code' => 'ACCBC', 'name' => 'ACCOUNTING FOR BUSINESS COMBINATION', 'units' => 3],
            ['code' => 'ACCST', 'name' => 'ACCOUNTING FOR SPECIAL TRANSACTION', 'units' => 3],
            ['code' => 'FINMAN', 'name' => 'FINANCIAL MANAGEMENT', 'units' => 3],
            ['code' => 'HUBEORG', 'name' => 'Human Behavior in Organization', 'units' => 3],
            ['code' => 'STASSAP', 'name' => 'STATISTICAL ANALYSIS WITH SOFTWARE APPLICATION', 'units' => 3],
            ['code' => 'ACCINTERN', 'name' => 'ACCOUNTING INTERNSHIP', 'units' => 6],
            ['code' => 'ACCRES', 'name' => 'ACCOUNTANCY RESEARCH', 'units' => 3],
            ['code' => 'SBUSANA', 'name' => 'STRATEGIC BUSINESS ANALYSIS', 'units' => 3],

            // COE
            ['code' => 'EDTECH 1', 'name' => 'Technology for Teaching and Learning 1', 'units' => 3],
            ['code' => 'TPROF', 'name' => 'The Teaching Profession', 'units' => 3],
            ['code' => 'FALECT', 'name' => 'Facilitating Learner-Centered Teaching', 'units' => 3],
            ['code' => 'FOSPED', 'name' => 'Foundation of Special and Inclusive Education', 'units' => 3],
            ['code' => 'TMATH 1', 'name' => 'Teaching Math in Primary Grades', 'units' => 3],
            ['code' => 'TSS 1', 'name' => 'Teaching Social Studies in the Elementary Grades (Culture and Geography)', 'units' => 3],
            ['code' => 'TFIL 1', 'name' => 'Pagtuturo ng Filipino sa Elementarya (1) (Estruktura at Gamit ng Wikang Filipno)', 'units' => 3],
            ['code' => 'TSCI 1', 'name' => 'Teaching Science in the Elementary Grades (Biology and Chemistry)', 'units' => 3],
            ['code' => 'ASSMNT 1', 'name' => 'Assessment in Learning 1', 'units' => 3],
            ['code' => 'TLARTS', 'name' => 'Teaching English in the Elementary Grades (Language Arts)', 'units' => 3],
            ['code' => 'TMUSIC', 'name' => 'Teaching Music in the Elementary Grades', 'units' => 3],
            ['code' => 'TLIT', 'name' => 'Teaching English in the Elementary Grades through Literature', 'units' => 3],
            ['code' => 'TEARTS', 'name' => 'Teaching Arts in the Elementary Grades', 'units' => 3],
            ['code' => 'EPP', 'name' => 'Edukasyong Pantahanan and Pangkabuhayan', 'units' => 3],
            ['code' => 'FERCE', 'name' => 'Foundation of Early Childhood Education', 'units' => 3],
            ['code' => 'TSS 2', 'name' => 'Teaching Social Studies in Elementary Grades (Philippine History and Government)', 'units' => 3],
            ['code' => 'RES 2', 'name' => 'Research in Elementary Education 2', 'units' => 3],
            ['code' => 'FS 1', 'name' => 'Field Study 1', 'units' => 3],
            ['code' => 'FS 2', 'name' => 'Field Study 2', 'units' => 3],
            ['code' => 'BENLAC', 'name' => 'Building and Enhancing New Literacies Across the Curriculum', 'units' => 3],
            ['code' => 'LINGGWIS', 'name' => 'Panimulang Linggwistika', 'units' => 3],
            ['code' => 'PANREH', 'name' => 'Panitikan ng Rehiyon', 'units' => 3],
            ['code' => 'SALIN', 'name' => 'Introduksyon sa Pagsasalin', 'units' => 3],
            ['code' => 'PANDAIGDIG', 'name' => 'Panitikang Pandaigdig', 'units' => 3],
            ['code' => 'INTROMID', 'name' => 'Introduksyon sa Pamamahayag', 'units' => 3],
            ['code' => 'BARWIKA', 'name' => 'Barayti at Baryasyon ng Wika', 'units' => 3],
            ['code' => 'DULA', 'name' => 'Dulaang Filipino', 'units' => 3],
            ['code' => 'OBRABASA', 'name' => 'Pagbasa ng mga Obra Maestrang Filipino', 'units' => 3],
            ['code' => 'PANPAM', 'name' => 'Panunuring Pampanitikan', 'units' => 3],
            ['code' => 'KWENBEL', 'name' => 'Maikling Kwento at Nobelang Filipino', 'units' => 3],
            ['code' => 'LCS', 'name' => 'Language, Culture and Society', 'units' => 3],
            ['code' => 'ESTRUCT', 'name' => 'Structure of English', 'units' => 3],
            ['code' => 'CHILDLIT', 'name' => 'Children and Adolescent Literature', 'units' => 3],
            ['code' => 'PROGPOL', 'name' => 'Language Program and Policies in Multilingual Societies', 'units' => 3],
            ['code' => 'MATDEV', 'name' => 'Preparation of Language Learning Materials', 'units' => 3],
            ['code' => 'TASLIT', 'name' => 'Teaching and Assessment of Literature', 'units' => 3],
            ['code' => 'CAMJOURN', 'name' => 'Campus Journalism', 'units' => 2],
            ['code' => 'CREWRIT', 'name' => 'Creative Writing', 'units' => 3],
            ['code' => 'TASGRAM', 'name' => 'Teaching and Assessment of Grammar', 'units' => 3],
            ['code' => 'TASMAC', 'name' => 'Teaching and Assessment of the Macroskills', 'units' => 3],
            ['code' => 'REMINST', 'name' => 'Remedial Instruction', 'units' => 3],
            ['code' => 'POLGOV', 'name' => 'Politics and Governance with Philippine Constitution', 'units' => 3],
            ['code' => 'PLANDWORLD', 'name' => 'Places and Landscape in a Changing World', 'units' => 3],
            ['code' => 'GEO 2', 'name' => 'Geography 2 - Physical Geography', 'units' => 3],
            ['code' => 'ASIA 2', 'name' => 'Asian Studies 2 - Contemporary Asia', 'units' => 3],
            ['code' => 'WORLDHIS1', 'name' => 'World History 1 - Ancient and Medieval Era', 'units' => 3],
            ['code' => 'PRODMAT', 'name' => 'Production of Social Studies Instructional Materials', 'units' => 3],
            ['code' => 'APPSOC', 'name' => 'Teaching Approaches in Secondary Social Studies', 'units' => 3],
            ['code' => 'MACROECO', 'name' => 'Macro Economics', 'units' => 3],
            ['code' => 'CONTPHIL', 'name' => 'Contemporary Philippine Problems and Issues', 'units' => 3],
            ['code' => 'TRENDSOC', 'name' => 'Trends and Issues in Social Studies', 'units' => 3],
            ['code' => 'PHILLET', 'name' => 'Philosophical and Ethical Foundations of Values Education', 'units' => 3],
            ['code' => 'PHILSOC', 'name' => 'Philippine Culture and Society', 'units' => 3],
            ['code' => 'CONTFALI', 'name' => 'Contemporary Family Life', 'units' => 3],
            ['code' => 'TRANSED', 'name' => 'Transformative Education', 'units' => 3],
            ['code' => 'CARDEV', 'name' => 'Career Development and Work Values', 'units' => 3],
            ['code' => 'APPVAL', 'name' => 'Teaching Approaches and Strategies in Values Education', 'units' => 3],
            ['code' => 'INTROGUIDE', 'name' => 'Introduction to Guidance and Counseling', 'units' => 3],
            ['code' => 'FATPRAC', 'name' => 'Facilitation: Theory and Practice', 'units' => 3],
            ['code' => 'DEVMAT', 'name' => 'Development of Values Education Instructional Materials and Assessment Tools', 'units' => 3],
            ['code' => 'TCP 1', 'name' => 'Technology for Teaching and Learning', 'units' => 3],
            ['code' => 'TCP 2', 'name' => 'Assessment in Learning', 'units' => 3],
            ['code' => 'TCP 3', 'name' => 'Principles of Teaching', 'units' => 3],

            // CBAE
            ['code' => 'FUNDACC', 'name' => 'Fundamentals of Accounting', 'units' => 3],
            ['code' => 'BUSLAW', 'name' => 'Business Law (OBLICON)', 'units' => 3],
            ['code' => 'GGSR', 'name' => 'Good Governance & Social Responsibility', 'units' => 3],
            ['code' => 'ADVCOM', 'name' => 'Adv Computer Application for Business', 'units' => 3],
            ['code' => 'FRANCH', 'name' => 'Franchising', 'units' => 3],
            ['code' => 'OPMAN', 'name' => 'Operations Management', 'units' => 3],
            ['code' => 'MOPCEB', 'name' => 'Monetary Policy and Central Banking', 'units' => 3],
            ['code' => 'BANFIN', 'name' => 'Banking and Financial Institutions', 'units' => 3],
            ['code' => 'STRAMAN', 'name' => 'Strategic Management', 'units' => 3],
            ['code' => 'STATRES', 'name' => 'Statistics for Research', 'units' => 3],
            ['code' => 'BEHFIN', 'name' => 'Behavioral Finance', 'units' => 3],
            ['code' => 'P.MGT', 'name' => 'Principles of Management', 'units' => 3],
            ['code' => 'P.MKTG', 'name' => 'Principles of Marketing', 'units' => 3],
            ['code' => 'MANACC', 'name' => 'Managerial Accounting', 'units' => 3],
            ['code' => 'MICECO', 'name' => 'Basic Microeconomics', 'units' => 3],
            ['code' => 'HRMAN', 'name' => 'Human Resource Management', 'units' => 3],
            ['code' => 'OPPOSE', 'name' => 'Opportunity Seeking', 'units' => 3],
            ['code' => 'ENTREBE', 'name' => 'Entrepreneurship Behavior', 'units' => 3],
            ['code' => 'TRACK 1', 'name' => 'Agribusiness', 'units' => 3],
            ['code' => 'SOCENT', 'name' => 'Social Entrepreneurship', 'units' => 3],
            ['code' => 'INOVMN', 'name' => 'Innovation Management', 'units' => 3],
            ['code' => 'ENTREMAR', 'name' => 'Entrepreneurship Marketing Strategies', 'units' => 3],
            ['code' => 'E-COMM', 'name' => 'E-Commerce & Internet Marketing', 'units' => 3],
            ['code' => 'TRACK 3', 'name' => 'Service Business', 'units' => 3],
            ['code' => 'BP IMPLE1', 'name' => 'Business Plan Implementation 1', 'units' => 5],
            ['code' => 'PRISTRAT', 'name' => 'Pricing Strategy', 'units' => 3],
            ['code' => 'FILDIS', 'name' => 'Filipino sa Ibat-ibang Disiplina', 'units' => 3],
            ['code' => 'ADVER', 'name' => 'Advertising', 'units' => 3],
            ['code' => 'INTEBUS', 'name' => 'International Business & Trade', 'units' => 3],
            ['code' => 'DISMAN', 'name' => 'Distribution Management', 'units' => 3],
            ['code' => 'MARKRES', 'name' => 'Marketing Research', 'units' => 3],
            ['code' => 'PRODMAN', 'name' => 'Product Management', 'units' => 3],
            ['code' => 'THESIS', 'name' => 'Thesis', 'units' => 3],
            ['code' => 'MARKDEV', 'name' => 'New Market Development', 'units' => 3],
            ['code' => 'TAX', 'name' => 'Taxation', 'units' => 3],
            ['code' => 'RECSEL', 'name' => 'Recruitment and Selection', 'units' => 3],
            ['code' => 'COMPAD', 'name' => 'Compensation Administration', 'units' => 3],
            ['code' => 'LOGMAN', 'name' => 'Logistics Management', 'units' => 3],
            ['code' => 'LABREL', 'name' => 'Labor Relations and Negotiations', 'units' => 3],
        ];

        $subjects = [];
        foreach ($subjectsData as $sData) {
            $subj = Subject::create([
                'code' => $sData['code'],
                'name' => $sData['name'],
                'units' => $sData['units'],
            ]);
            $subjects[$sData['code']] = $subj;
        }

        // 13. Seed Students (3,200 total: 800 per academic college)
        $firstNames = [
            'Aaron', 'Abigail', 'Adrian', 'Aira', 'Albert', 'Alexander', 'Alyssa', 'Angelo', 'Anna', 'Anthony',
            'Bea', 'Benjamin', 'Bernadette', 'Bryan', 'Camille', 'Carl', 'Carlos', 'Catherine', 'Charles', 'Christian',
            'Christine', 'Danica', 'Daniel', 'David', 'Dennis', 'Diana', 'Dominic', 'Eduardo', 'EJ', 'Elena',
            'Elijah', 'Elizabeth', 'Ella', 'Emmanuel', 'Eric', 'Erica', 'Ethan', 'Faith', 'Francis', 'Gabriel',
            'Genesis', 'Gillian', 'Grace', 'Hannah', 'Harold', 'Harvey', 'Hazel', 'Ian', 'Irish', 'Ivan',
            'James', 'Janine', 'Jasmine', 'Jasper', 'Jay', 'Jayson', 'Jean', 'Jefferson', 'Jeffrey', 'Jerald',
            'Jerome', 'Jessica', 'Joan', 'Joel', 'John', 'Jonathan', 'Jordan', 'Jose', 'Joseph', 'Joshua',
            'Joy', 'Joyce', 'Juan', 'Judith', 'Julia', 'Julian', 'Justin', 'Karen', 'Karl', 'Kate',
            'Kathleen', 'Kenneth', 'Kevin', 'Kim', 'Kimberly', 'Kristian', 'Kyle', 'Lance', 'Lawrence', 'Lea',
            'Leo', 'Liam', 'Louie', 'Luis', 'Luke', 'Mae', 'Manuel', 'Marco', 'Maria', 'Mark',
            'Mary', 'Matthew', 'Melvin', 'Michael', 'Michelle', 'Miguel', 'Nathan', 'Neil', 'Nicole', 'Noel',
            'Oliver', 'Patricia', 'Patrick', 'Paul', 'Paula', 'Peter', 'Princess', 'Rafael', 'Ramon', 'Raphael',
            'Raymund', 'Regine', 'Renz', 'Rey', 'Rhea', 'Rica', 'Richard', 'Rico', 'Robbie', 'Robert',
            'Rochelle', 'Roderick', 'Rodrigo', 'Rogelio', 'Rolando', 'Rommel', 'Ronald', 'Rose', 'Roxanne', 'Ruben',
            'Russell', 'Ryan', 'Sam', 'Samantha', 'Samuel', 'Sarah', 'Sean', 'Sebastian', 'Shane', 'Shiela',
            'Simon', 'Stephanie', 'Stephen', 'Steven', 'Timothy', 'Tristan', 'Valerie', 'Vanessa', 'Victor', 'Vincent',
        ];

        $lastNames = [
            'Abad', 'Agoncillo', 'Aguilar', 'Alcantara', 'Alonso', 'Alvarez', 'Andres', 'Aquino', 'Arcilla', 'Austria',
            'Balagtas', 'Bautista', 'Beltran', 'Bernardo', 'Bonifacio', 'Bravo', 'Buenaventura', 'Caballero', 'Cabrera', 'Calderon',
            'Campos', 'Canlas', 'Capistrano', 'Cariño', 'Castillo', 'Castro', 'Cervantes', 'Claudio', 'Collado', 'Concepcion',
            'Cornejo', 'Coronel', 'Corpuz', 'Cortez', 'Cruz', 'Cuenca', 'Custodio', 'Dacanay', 'David', 'De Castro',
            'De Guzman', 'De Jesus', 'De Leon', 'De Mesa', 'De Silva', 'Del Rosario', 'Dela Cruz', 'Dela Rosa', 'Diaz', 'Dimaculangan',
            'Dizon', 'Domingo', 'Enriquez', 'Escobar', 'Esguerra', 'Espiritu', 'Estrella', 'Evangelista', 'Fabian', 'Fajardo',
            'Fernandez', 'Ferrer', 'Flores', 'Francisco', 'Galan', 'Gallardo', 'Galvez', 'Garcia', 'Garrido', 'Gascon',
            'Gomez', 'Gonzales', 'Guerrero', 'Guevarra', 'Gutierrez', 'Guzman', 'Hermosa', 'Hernandez', 'Herrera', 'Hilario',
            'Ibáñez', 'Ignacio', 'Ilagan', 'Imperial', 'Inocencio', 'Jacinto', 'Javier', 'Jimenez', 'Joaquin', 'Jocson',
            'Labrador', 'Lacson', 'Lagman', 'Lansangan', 'Lapid', 'Laurel', 'Laxamana', 'Layson', 'Lazaro', 'Legaspi',
            'Lim', 'Linsangan', 'Liwanag', 'Lontoc', 'Lopez', 'Lorenzo', 'Loyola', 'Lozano', 'Lucero', 'Luna',
            'Mabini', 'Macapagal', 'Macaraeg', 'Magsaysay', 'Malabanan', 'Mallari', 'Manabat', 'Manalo', 'Manansala', 'Mangahas',
            'Manuel', 'Manzano', 'Marasigan', 'Marcelo', 'Mariano', 'Marquez', 'Martin', 'Martinez', 'Medina', 'Mendoza',
            'Mercado', 'Miranda', 'Molina', 'Montemayor', 'Montenegro', 'Montero', 'Montes', 'Morales', 'Moreno', 'Muñoz',
            'Nacion', 'Narciso', 'Natividad', 'Navarro', 'Nepomuceno', 'Nicolas', 'Nieto', 'Nolasco', 'Nuñez', 'Ocampo',
            'Ochoa', 'Oliveros', 'Ong', 'Ortega', 'Ortiz', 'Pacheco', 'Padilla', 'Palma', 'Panganiban', 'Pascual',
        ];

        // Section mappings per department & year level
        $deptSections = [
            'CCS' => [
                1 => ['IT 101', 'IT 102', 'IT 103', 'IT 104', 'IT 105', 'IT 106', 'IT 107', 'IT 108'],
                2 => ['IT 201', 'IT 202', 'IT 203', 'IT 204', 'IT 205', 'IT 206', 'IT 207'],
                3 => ['IT 301', 'IT 302', 'IT 303', 'IT 304', 'IT 305', 'IT 306', 'IT 307'],
                4 => ['IT 401', 'IT 402', 'IT 403', 'IT 404', 'IT 405', 'IT 406', 'IT 407'],
            ],
            'COA' => [
                1 => ['ACC 101', 'ACC 102', 'ACC 103'],
                2 => ['ACC 201', 'ACC 202', 'ACC 203'],
                3 => ['ACC 301', 'ACC 302', 'ACC 303'],
                4 => ['ACC 401', 'ACC 402', 'ACC 403'],
            ],
            'COE' => [
                1 => ['EDUC 101', 'EDUC 102', 'EDUC 103', 'EDUC 104', 'EDUC 105', 'EDUC 106', 'EDUC 107'],
                2 => ['ELEM 201', 'ELEM 202', 'FIL 201', 'ENG 201', 'SOCSCI 201', 'VAL 201'],
                3 => ['ELEM 301', 'ELEM 302', 'FIL 301', 'FIL 302', 'ENG 301', 'SOC 301', 'VAL 301'],
                4 => ['ELEM 401', 'ELEM 402', 'ENG 401', 'ENG 402', 'TCP'],
            ],
            'CBAE' => [
                1 => ['FM 101', 'FM 102', 'EN 101', 'MM 101', 'MM 102', 'MM 103', 'HR 101', 'HR 102', 'HR 103'],
                2 => ['FM 201', 'FM 202', 'EN 201', 'MM 201', 'MM 202', 'MM 203', 'MM 204', 'HR 201', 'HR 202', 'HR 203'],
                3 => ['FM 301', 'FM 302', 'EN 301', 'MM 301', 'MM 302', 'MM 303', 'HR 301', 'HR 302'],
                4 => ['EN 401', 'MM 401', 'MM 402', 'MM 403', 'MM 404', 'HR 401', 'HR 402', 'HR 403'],
            ],
        ];

        $deptPrograms = [
            'CCS' => $programs['BSIT']->id,
            'COA' => $programs['BSA']->id,
            'COE' => $programs['BEED']->id,
            'CBAE' => $programs['BSBA-MM']->id,
        ];

        // Specific Program mapping based on section code prefix
        $getSectionProgramId = function ($deptCode, $sectionName) use ($programs, $deptPrograms) {
            $prefix = strtoupper(explode(' ', $sectionName)[0]);

            return match ($prefix) {
                'IT' => $programs['BSIT']->id,
                'ACC' => $programs['BSA']->id,
                'ELEM', 'EDUC' => $programs['BEED']->id,
                'FIL' => $programs['BSED-FIL']->id,
                'ENG' => $programs['BSED-ENG']->id,
                'SOC', 'SOCSCI' => $programs['BSED-SOC']->id,
                'VAL' => $programs['BSED-VAL']->id,
                'TCP' => $programs['TCP']->id,
                'FM' => $programs['BSBA-FM']->id,
                'HR' => $programs['BSBA-HR']->id,
                'MM' => $programs['BSBA-MM']->id,
                'EN' => $programs['BSENT']->id,
                default => $deptPrograms[$deptCode],
            };
        };

        $allStudentsBySection = [];
        $studentInsertData = [];
        $studentUserInsertData = [];

        $now = now()->toDateTimeString();
        $studentIdCounter = 1;

        foreach ($deptSections as $deptCode => $yearSections) {
            $deptStudentCount = 0;

            foreach ($yearSections as $yearLevel => $secList) {
                $studentsPerYear = 200;
                $secCount = count($secList);
                $basePerSec = (int) floor($studentsPerYear / $secCount);
                $remainder = $studentsPerYear % $secCount;

                foreach ($secList as $sIdx => $secName) {
                    $countForThisSec = $basePerSec + ($sIdx < $remainder ? 1 : 0);
                    $programId = $getSectionProgramId($deptCode, $secName);

                    for ($k = 0; $k < $countForThisSec; $k++) {
                        $deptStudentCount++;
                        $numStr = str_pad((string) $deptStudentCount, 4, '0', STR_PAD_LEFT);
                        $studentNumber = "2026-{$deptCode}-{$numStr}";

                        $fn = $firstNames[($studentIdCounter + $k) % count($firstNames)];
                        $ln = $lastNames[($studentIdCounter * 3 + $k) % count($lastNames)];

                        $studentInsertData[] = [
                            'id' => $studentIdCounter,
                            'student_number' => $studentNumber,
                            'first_name' => $fn,
                            'last_name' => $ln,
                            'program_id' => $programId,
                            'year_level' => $yearLevel,
                            'section' => $secName,
                            'status' => 'regular',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $studentUserInsertData[] = [
                            'name' => "{$fn} {$ln}",
                            'email' => strtolower("{$studentNumber}@student.grc.edu.ph"),
                            'student_id' => $studentIdCounter,
                            'employee_id' => null,
                            'password' => $passwordHash,
                            'is_active' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $allStudentsBySection[$secName][] = $studentIdCounter;
                        $studentIdCounter++;
                    }
                }
            }
        }

        // Chunked insert for 3,200 students and student users
        foreach (array_chunk($studentInsertData, 500) as $chunk) {
            DB::table('students')->insert($chunk);
        }

        foreach (array_chunk($studentUserInsertData, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        // Assign 'student' role to all created student users
        $studentRole = $roleModels['student'];
        $studentUserRecords = DB::table('users')->whereNotNull('student_id')->select('id')->get();
        $roleAssignments = $studentUserRecords->map(fn ($u) => [
            'role_id' => $studentRole->id,
            'model_type' => User::class,
            'model_id' => $u->id,
        ])->toArray();

        foreach (array_chunk($roleAssignments, 500) as $chunk) {
            DB::table('model_has_roles')->insert($chunk);
        }

        // 14. Section Subjects Mapping & Class Allocations
        // Define representative subjects per section
        $sectionSubjectsMap = [
            // CCS 1st Year (IT 101 - IT 108)
            'IT 1' => ['ITC', 'ITCL', 'ITP1', 'ITP1L', 'ITP2', 'ITP2L', 'KOMFIL', 'LEAD 1', 'MATHWRLD', 'NSTP 1', 'PATHFIT1', 'PHILHIST', 'PURPCOMM', 'UNDSELF'],
            // CCS 2nd Year (IT 201 - IT 207)
            'IT 2' => ['AVE', 'AVEL', 'CPROG2', 'CPROG2L', 'DBMSYS', 'DBMSYSL', 'ENVISCI', 'IPT1', 'IPT1L', 'LEAD 3', 'NW1', 'NW1L', 'PATHFIT3', 'WST', 'WSTL'],
            // CCS 3rd Year (IT 301 - IT 307)
            'IT 3' => ['ARTAPP', 'BMC', 'BMCL', 'CAO', 'CAOL', 'DMATH', 'LEAD 5', 'PRELEC2', 'PRELEC2L', 'PT', 'PTL', 'SIA2', 'SIA2L'],
            // CCS 4th Year (IT 401 - IT 407)
            'IT 4' => ['BUSANA', 'CAPS2', 'CAPS2L', 'IAS2', 'IAS2L', 'LEAD 7', 'SPI'],

            // COA 1st Year (ACC 101 - ACC 103)
            'ACC 1' => ['CONWRLD', 'FINACC', 'FUNDACC 1', 'KOMFIL', 'LEAD 1', 'MANECO', 'NSTP 1', 'QM-TQM', 'PATHFIT1', 'PHILHIST', 'UNDSELF'],
            // COA 2nd Year (ACC 201 - ACC 203)
            'ACC 2' => ['BLAWREG', 'ENVISCI', 'ETHICS', 'INCTAX', 'INTACC 2', 'IT-ATB', 'LEAD 3', 'MANSCI', 'PATHFIT3', 'SCOSMAN'],
            // COA 3rd Year (ACC 301 - ACC 303)
            'ACC 3' => ['AACAP 1', 'AAPRIN', 'ACCBC', 'ACCST', 'FINMAN', 'HUBEORG', 'LEAD 5', 'STASSAP'],
            // COA 4th Year (ACC 401 - ACC 403)
            'ACC 4' => ['ACCINTERN', 'ACCRES', 'LEAD 7', 'SBUSANA'],

            // COE 1st Year (EDUC 101 - EDUC 107)
            'EDUC 1' => ['UNDSELF', 'PHILHIST', 'MATHINV', 'PURPCOMM', 'ENVISCI', 'KOMFIL', 'ARTAPP', 'ETHICS', 'NSTP 1', 'PATHFIT1', 'LEAD 1'],
            // COE 2nd Year
            'ELEM 2' => ['EDTECH 1', 'TPROF', 'FALECT', 'FOSPED', 'SOSLIT', 'TMATH 1', 'TSS 1', 'TFIL 1', 'TSCI 1', 'PATHFIT3', 'LEAD 3'],
            'FIL 2' => ['EDTECH 1', 'BENLAC', 'TPROF', 'FALECT', 'FOSPED', 'LINGGWIS', 'RIZAL', 'PANREH', 'SOSLIT', 'PATHFIT3', 'LEAD 3'],
            'ENG 2' => ['EDTECH 1', 'BENLAC', 'TPROF', 'FOSPED', 'FALECT', 'LCS', 'ESTRUCT', 'SOSLIT', 'PATHFIT3', 'LEAD 3'],
            'SOCSCI 2' => ['EDTECH 1', 'BENLAC', 'TPROF', 'FALECT', 'FOSPED', 'POLGOV', 'PLANDWORLD', 'SOSLIT', 'RIZAL', 'PATHFIT3', 'LEAD 3'],
            'VAL 2' => ['EDTECH 1', 'BENLAC', 'TPROF', 'FALECT', 'FOSPED', 'PHILLET', 'PHILSOC', 'SOSLIT', 'PATHFIT3', 'LEAD 3'],
            // COE 3rd Year
            'ELEM 3' => ['ASSMNT 1', 'TLARTS', 'TMUSIC', 'TLIT', 'TEARTS', 'EPP', 'FERCE', 'TSS 2', 'LEAD 5'],
            'FIL 3' => ['ASSMNT 1', 'SALIN', 'PANDAIGDIG', 'INTROMID', 'BARWIKA', 'DULA', 'OBRABASA', 'PANPAM', 'KWENBEL', 'LEAD 5'],
            'ENG 3' => ['ASSMNT 1', 'CHILDLIT', 'PROGPOL', 'MATDEV', 'TASLIT', 'CAMJOURN', 'CREWRIT', 'TASGRAM', 'TASMAC', 'LEAD 5'],
            'SOC 3' => ['ASSMNT 1', 'GEO 2', 'ASIA 2', 'WORLDHIS1', 'PRODMAT', 'APPSOC', 'MACROECO', 'CONTPHIL', 'TRENDSOC', 'LEAD 5'],
            'VAL 3' => ['ASSMNT 1', 'CONTFALI', 'TRANSED', 'CARDEV', 'APPVAL', 'INTROGUIDE', 'FATPRAC', 'DEVMAT', 'LEAD 5'],
            // COE 4th Year
            'ELEM 4' => ['RES 2', 'FS 1', 'FS 2', 'LEAD 7'],
            'ENG 4' => ['REMINST', 'RES 2', 'FS 1', 'FS 2', 'LEAD 7'],
            'TCP' => ['TCP 1', 'TCP 2', 'TCP 3'],

            // CBAE 1st Year
            'FM 1' => ['UNDSELF', 'PHILHIST', 'PURPCOMM', 'KOMFIL', 'MATHWRLD', 'NSTP 1', 'PATHFIT1', 'LEAD 1', 'FUNDACC'],
            'EN 1' => ['UNDSELF', 'PHILHIST', 'PURPCOMM', 'KOMFIL', 'ARTAPP', 'ETHICS', 'NSTP 1', 'PATHFIT1', 'LEAD 1', 'P.MGT', 'P.MKTG'],
            'MM 1' => ['UNDSELF', 'PHILHIST', 'GGSR', 'SOSLIT', 'MATHINV', 'ARTAPP', 'NSTP 1', 'PATHFIT1', 'LEAD 1', 'P.MGT', 'P.MKTG'],
            'HR 1' => ['UNDSELF', 'PHILHIST', 'PURPCOMM', 'KOMFIL', 'MATHINV', 'ETHICS', 'NSTP 1', 'PATHFIT1', 'LEAD 1', 'P.MGT', 'P.MKTG'],
            // CBAE 2nd Year
            'FM 2' => ['BUSLAW', 'GGSR', 'ADVCOM', 'FINMAN', 'FRANCH', 'RIZAL', 'CONWRLD', 'PATHFIT3', 'LEAD 3'],
            'EN 2' => ['MANACC', 'CONWRLD', 'MICECO', 'HRMAN', 'OPPOSE', 'ENTREBE', 'SOSLIT', 'PATHFIT3', 'LEAD 3'],
            'MM 2' => ['PRISTRAT', 'ETHICS', 'HRMAN', 'FILDIS', 'ADVER', 'RIZAL', 'ADVCOM', 'OPMAN', 'PATHFIT3', 'LEAD 3'],
            'HR 2' => ['OPMAN', 'BUSLAW', 'MARMAN', 'GGSR', 'TAX', 'SOSLIT', 'RECSEL', 'PATHFIT3', 'LEAD 3'],
            // CBAE 3rd Year
            'FM 3' => ['OPMAN', 'MOPCEB', 'BANFIN', 'STRAMAN', 'STATRES', 'BEHFIN', 'LEAD 5'],
            'EN 3' => ['TRACK 1', 'SOCENT', 'INOVMN', 'ENTREMAR', 'E-COMM', 'OPMAN', 'LEAD 5'],
            'MM 3' => ['INTEBUS', 'DISMAN', 'MICECO', 'MARKRES', 'PRODMAN', 'LEAD 5'],
            'HR 3' => ['COMPAD', 'INTEBUS', 'STATRES', 'STRAMAN', 'LOGMAN', 'LEAD 5'],
            // CBAE 4th Year
            'EN 4' => ['TRACK 3', 'BP IMPLE1', 'LEAD 7'],
            'MM 4' => ['THESIS', 'MARKDEV', 'LEAD 7'],
            'HR 4' => ['THESIS', 'LABREL', 'LEAD 7'],
        ];

        $getSubjectListForSection = function ($secName) use ($sectionSubjectsMap) {
            foreach ($sectionSubjectsMap as $key => $subjs) {
                if (str_starts_with($secName, $key)) {
                    return $subjs;
                }
            }

            return ['PURPCOMM', 'UNDSELF', 'PHILHIST', 'PATHFIT1'];
        };

        // Create Classes for all sections and enroll students
        $classPivotData = [];
        $classIdCounter = 1;
        $classInsertData = [];

        $allSectionNames = array_keys($allStudentsBySection);
        $facultyCount = count($facultyEmps);

        foreach ($allSectionNames as $sIdx => $secName) {
            $subjCodes = $getSubjectListForSection($secName);
            $enrolledStudentIds = $allStudentsBySection[$secName] ?? [];

            foreach ($subjCodes as $cIdx => $sCode) {
                if (! isset($subjects[$sCode])) {
                    continue;
                }

                $assignedFaculty = $facultyEmps[($sIdx * 7 + $cIdx) % $facultyCount];

                $classInsertData[] = [
                    'id' => $classIdCounter,
                    'subject_id' => $subjects[$sCode]->id,
                    'semester_id' => $sem->id,
                    'teacher_id' => $assignedFaculty->id,
                    'section' => $secName,
                    'schedule' => 'Mon/Wed 09:00 AM - 10:30 AM',
                    'room' => 'Room '.(101 + ($classIdCounter % 20)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($enrolledStudentIds as $stId) {
                    $classPivotData[] = [
                        'class_id' => $classIdCounter,
                        'student_id' => $stId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $classIdCounter++;
            }
        }

        // Insert classes
        foreach (array_chunk($classInsertData, 300) as $chunk) {
            DB::table('classes')->insert($chunk);
        }

        // Insert class_student enrollments
        foreach (array_chunk($classPivotData, 1000) as $chunk) {
            DB::table('class_student')->insert($chunk);
        }

        // Call Phase 2 Seeder
        $this->call(EvaluationPhase2Seeder::class);
    }
}
