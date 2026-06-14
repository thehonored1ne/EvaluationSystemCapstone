<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Define the roles
        $roles = [
            'admin',
            'dean',
            'program head',
            'faculty',
            'student',
            'staff',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // 2. Seed Academic Year & Semester
        $ay = AcademicYear::firstOrCreate(
            ['name' => '2025-2026'],
            ['is_active' => true]
        );

        $sem = Semester::firstOrCreate(
            [
                'academic_year_id' => $ay->id,
                'name' => '1st Semester',
            ],
            ['is_active' => true]
        );

        // 3. Seed Evaluation Criteria and Questions
        $criteria = [
            // Student Evaluation: 90 points total
            [
                'evaluation_type' => 'student',
                'name' => 'Part 1: Instructional Delivery & Subject Expertise',
                'order' => 1,
                'max_points' => 36.00,
                'questions' => [
                    'The instructor displays a thorough understanding of the subject matter.',
                    'The instructor explains complex topics clearly and understandably.',
                    'The instructor encourages class discussion and active participation.',
                    'The instructor uses relevant examples and teaching methods to facilitate learning.',
                ],
            ],
            [
                'evaluation_type' => 'student',
                'name' => 'Part 2: Classroom Management & Professionalism',
                'order' => 2,
                'max_points' => 36.00,
                'questions' => [
                    'The instructor starts and ends classes on schedule.',
                    'The instructor treats students with respect and professionalism.',
                    'The instructor is accessible to students outside of class hours (consultation hours).',
                    'The instructor maintains order and a conducive learning environment in the classroom.',
                ],
            ],
            [
                'evaluation_type' => 'student',
                'name' => 'Part 3: Assessment & Grading fairness',
                'order' => 3,
                'max_points' => 18.00,
                'questions' => [
                    'The instructor clearly outlines grading policies and evaluation metrics.',
                    'The instructor provides timely and constructive feedback on exams and projects.',
                    'The instructor conducts quizzes, tests, and tasks that are aligned with course objectives.',
                ],
            ],

            // Peer Evaluation: 50 points total
            [
                'evaluation_type' => 'peer',
                'name' => 'Part 1: Teaching Effectiveness & Delivery',
                'order' => 1,
                'max_points' => 15.00,
                'questions' => [
                    'The instructor demonstrates expertise in course planning and preparation.',
                    'The instructor delivers instruction using effective methodology aligned with course syllabi.',
                    'The instructor encourages academic standards and intellectual curiosity.',
                ],
            ],
            [
                'evaluation_type' => 'peer',
                'name' => 'Part 2: Professional Development & Research',
                'order' => 2,
                'max_points' => 15.00,
                'questions' => [
                    'The instructor conforms to academic policies, duties, and consultation hours.',
                    'The instructor displays professionalism and mutual respect with colleagues and staff.',
                ],
            ],
            [
                'evaluation_type' => 'peer',
                'name' => 'Part 3: Administrative Compliance & Reports',
                'order' => 3,
                'max_points' => 10.00,
                'questions' => [
                    'The instructor submits administrative and academic reports on time.',
                ],
            ],
            [
                'evaluation_type' => 'peer',
                'name' => 'Part 4: Community Service & Collaboration',
                'order' => 4,
                'max_points' => 10.00,
                'questions' => [
                    'The instructor actively participates in extension services and community development.',
                ],
            ],

            // Self Evaluation: 10 points total
            [
                'evaluation_type' => 'self',
                'name' => 'Part 1: Self Evaluation',
                'order' => 1,
                'max_points' => 10.00,
                'questions' => [
                    'I explain course concepts with clarity and use engaging teaching methods.',
                    'I update course materials regularly to integrate new developments in my field.',
                    'I maintain a professional and supportive relationship with students.',
                    'I manage class time productively and keep precise attendance and academic reports.',
                    'I explain grading procedures clearly and apply assessment criteria consistently.',
                    'I provide students with prompt feedback on their assessments.',
                ],
            ],
        ];

        foreach ($criteria as $cData) {
            $criterion = EvaluationCriterion::firstOrCreate(
                [
                    'evaluation_type' => $cData['evaluation_type'],
                    'name' => $cData['name'],
                ],
                [
                    'order' => $cData['order'],
                    'max_points' => $cData['max_points'],
                ]
            );

            foreach ($cData['questions'] as $index => $qText) {
                EvaluationQuestion::firstOrCreate(
                    [
                        'criterion_id' => $criterion->id,
                        'question_text' => $qText,
                    ],
                    [
                        'order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }

        // 4. Create an initial Admin employee and user
        $adminEmployee = Employee::firstOrCreate(
            ['employee_number' => 'ADMIN-001'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'dion.areglo1234@gmail.com'],
            [
                'name' => 'System Admin',
                'employee_id' => $adminEmployee->id,
                'password' => Hash::make('password'),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // 5. Create an initial Dean employee and user
        $deanEmployee = Employee::firstOrCreate(
            ['employee_number' => 'DEAN-001'],
            [
                'first_name' => 'College',
                'last_name' => 'Dean',
                'role' => 'dean',
                'status' => 'active',
            ]
        );

        $dean = User::firstOrCreate(
            ['email' => 'dean@example.com'],
            [
                'name' => 'College Dean',
                'employee_id' => $deanEmployee->id,
                'password' => Hash::make('password'),
            ]
        );

        if (! $dean->hasRole('dean')) {
            $dean->assignRole('dean');
        }

        // Call the DemoDataSeeder to seed program heads, faculty, and students
        $this->call(DemoDataSeeder::class);
    }
}
