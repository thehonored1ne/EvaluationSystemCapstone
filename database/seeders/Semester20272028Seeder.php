<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Semester20272028Seeder extends Seeder
{
    /**
     * Run the database seed for A.Y. 2025-2026 - 2nd Semester (Completed Historical Term).
     */
    public function run(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        // 1. Ensure Academic Year & Semester exist
        $ay = AcademicYear::firstOrCreate(
            ['name' => '2025-2026'],
            ['is_active' => false]
        );

        $sem = Semester::firstOrCreate(
            [
                'academic_year_id' => $ay->id,
                'name' => '2nd Semester',
            ],
            [
                'is_active' => false,
                'is_evaluation_open' => false,
                'evaluation_starts_at' => '2026-01-10 08:00:00',
                'evaluation_ends_at' => '2026-02-28 17:00:00',
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
                'department_head_max_points' => 50.00,
                'upward_employee_max_points' => 50.00,
            ]
        );

        // Ensure evaluation dates and closed status are strictly enforced
        $sem->update([
            'is_active' => false,
            'is_evaluation_open' => false,
            'evaluation_starts_at' => '2026-01-10 08:00:00',
            'evaluation_ends_at' => '2026-02-28 17:00:00',
        ]);

        $this->command->info("Target Semester: {$ay->name} - {$sem->name} (ID: {$sem->id})");

        // 2. Clean up any existing records for this specific semester (Idempotency)
        $oldEvalIds = DB::table('evaluations')->where('semester_id', $sem->id)->pluck('id');
        if ($oldEvalIds->isNotEmpty()) {
            $this->command->info('Cleaning up existing evaluations for this semester...');
            foreach ($oldEvalIds->chunk(500) as $chunk) {
                DB::table('evaluation_answers')->whereIn('evaluation_id', $chunk)->delete();
                DB::table('evaluation_sentiments')->whereIn('evaluation_id', $chunk)->delete();
            }
            DB::table('evaluations')->where('semester_id', $sem->id)->delete();
        }

        $oldClassIds = DB::table('classes')->where('semester_id', $sem->id)->pluck('id');
        if ($oldClassIds->isNotEmpty()) {
            $this->command->info('Cleaning up existing classes for this semester...');
            foreach ($oldClassIds->chunk(500) as $chunk) {
                DB::table('class_student')->whereIn('class_id', $chunk)->delete();
            }
            DB::table('classes')->where('semester_id', $sem->id)->delete();
        }

        // 3. Cache criteria and question point definitions
        $criteriaByType = EvaluationCriterion::with('questions')->get()->groupBy('evaluation_type');
        $questionsData = [];
        foreach ($criteriaByType as $type => $criteriaList) {
            $questionsData[$type] = [];
            foreach ($criteriaList as $crit) {
                $qCount = $crit->questions->count();
                $cMax = (float) $crit->max_points;
                foreach ($crit->questions as $q) {
                    $questionsData[$type][] = [
                        'id' => $q->id,
                        'criterion_id' => $crit->id,
                        'max_points' => $cMax,
                        'q_count' => $qCount,
                    ];
                }
            }
        }

        // Qualitative Comments Banks
        $studentComments = [
            'positive' => [
                'Explains the subject matter clearly and provides relevant industry examples.',
                'Very approachable and dedicated teacher. Learned practical skills in every session.',
                'Lectures are organized, clear, and engaging. Great instructional delivery.',
                'Encourages active discussion and returns quizzes promptly with clear feedback.',
                'Always on time, patient with student questions, and inspires critical thinking.',
            ],
            'neutral' => [
                'Covers all syllabus topics adequately. More interactive activities would be beneficial.',
                'Lectures follow the textbook closely. Pacing is generally steady and organized.',
                'Fair grading standards and reasonable workload throughout the term.',
            ],
            'negative' => [
                'Lectures feel rushed at times and explanations could be clearer on complex concepts.',
                'Would appreciate quicker return of test results and assignments.',
            ],
        ];

        $peerComments = [
            'positive' => 'A supportive and reliable departmental colleague who actively shares teaching resources.',
            'neutral' => 'Consistently fulfills academic duties and attends departmental meetings.',
            'negative' => 'Could collaborate more proactively on shared curriculum tasks.',
        ];

        $supervisorComments = [
            'positive' => 'Demonstrates professional competence, clear curriculum mastery, and diligence.',
            'neutral' => 'Meets expected performance benchmarks in instructional responsibilities.',
            'negative' => 'Needs to focus on more timely submission of academic requirements.',
        ];

        $selfComments = [
            'positive' => 'Continuously committed to enhancing instructional delivery and student engagement.',
            'neutral' => 'Maintained satisfactory performance across all assigned teaching duties.',
        ];

        $now = now()->toDateTimeString();

        // 4. Helper for answers and scoring
        $generateAnswersAndScore = function (string $evalType, string $sentiment) use ($questionsData, $sem) {
            $questions = $questionsData[$evalType] ?? [];
            if (empty($questions)) {
                return ['answers' => [], 'rating_average' => 4.0, 'raw_score' => 40.0, 'max_score' => 40.0, 'weighted_score' => 20.0];
            }

            $answers = [];
            $totalRating = 0;
            $rawScore = 0.0;

            foreach ($questions as $q) {
                if ($sentiment === 'positive') {
                    // Historical positive baseline averages ~4.40
                    $rating = mt_rand(1, 100) <= 42 ? 5 : 4;
                } elseif ($sentiment === 'neutral') {
                    // Historical neutral baseline averages ~3.35
                    $rand = mt_rand(1, 100);
                    $rating = $rand <= 60 ? 3 : ($rand <= 90 ? 4 : 5);
                } else {
                    // Historical negative baseline averages ~2.10
                    $rand = mt_rand(1, 100);
                    $rating = $rand <= 60 ? 2 : ($rand <= 85 ? 1 : 3);
                }

                $answers[$q['id']] = $rating;
                $totalRating += $rating;
                $qPoints = ($rating / 5.0) * ($q['max_points'] / max(1, $q['q_count']));
                $rawScore += $qPoints;
            }

            $count = count($questions);
            $avgRating = round($totalRating / max(1, $count), 2);
            $rawScore = round($rawScore, 2);
            $maxScore = (float) $sem->getCategoryMaxPoints($evalType);
            if ($maxScore <= 0) {
                $maxScore = (float) array_sum(array_column($questions, 'max_points'));
            }
            $weight = (float) $sem->getCategoryWeight($evalType);
            if ($weight <= 0) {
                $weight = $maxScore;
            }
            $weightedScore = $maxScore > 0 ? round(($rawScore / $maxScore) * $weight, 2) : 0.0;

            return [
                'answers' => $answers,
                'rating_average' => $avgRating,
                'raw_score' => $rawScore,
                'max_score' => $maxScore,
                'weighted_score' => $weightedScore,
            ];
        };

        $determineSentimentData = function (string $sentiment, float $ratingAvg) {
            if ($sentiment === 'positive') {
                $vaderScore = round(0.40 + (mt_rand(0, 50) / 100), 4);
                $dtLabel = 'positive';
                $vaderLabel = 'positive';
            } elseif ($sentiment === 'neutral') {
                $vaderScore = round(-0.05 + (mt_rand(0, 30) / 100), 4);
                $dtLabel = 'neutral';
                $vaderLabel = 'neutral';
            } else {
                $vaderScore = round(-0.85 + (mt_rand(0, 50) / 100), 4);
                $dtLabel = 'negative';
                $vaderLabel = 'negative';
            }

            return [
                'vader_score' => $vaderScore,
                'vader_label' => $vaderLabel,
                'dt_label' => $dtLabel,
            ];
        };

        // 5. Fetch Users & Employees
        $facultyUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'faculty'))->with('employee.department')->get();
        $programHeadUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'program head'))->with('employee.department')->get();
        $deanUser = User::whereHas('employee', fn ($q) => $q->where('role', 'dean'))->first();
        $deptHeadUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'department head'))->with('employee.department')->get();
        $staffUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'staff'))->with('employee.department')->get();
        $students = Student::with('user', 'program')->where('status', 'regular')->get();
        $subjects = Subject::all();

        // 6. Generate Academic Classes (2-3 classes per faculty = 120 classes)
        $this->command->info('Creating Classes and Enrollments for 2027-2028 2nd Semester...');
        $classInserts = [];
        $enrollmentInserts = [];
        $classIdCounter = (int) DB::table('classes')->max('id') + 1;

        $sections = ['A', 'B', 'C', 'D'];
        $times = [
            'Mon/Wed 07:30 AM - 09:00 AM',
            'Mon/Wed 09:00 AM - 10:30 AM',
            'Mon/Wed 10:30 AM - 12:00 PM',
            'Tue/Thu 01:00 PM - 02:30 PM',
            'Tue/Thu 02:30 PM - 04:00 PM',
            'Fri 08:00 AM - 11:00 AM',
        ];
        $rooms = ['Room 101', 'Room 102', 'Room 201', 'Room 202', 'Room 301', 'Lab 1', 'Lab 2', 'AVR'];

        // 6. Generate Academic Classes with authentic 2nd Semester curriculum schedules
        $studentsBySection = $students->groupBy('section');
        $this->command->info('Creating Classes and Enrollments for all 3,200 students across '.$studentsBySection->count().' sections (2nd Semester curriculum schedules)...');

        $sectionSubjectsMap = [
            // CCS (IT / CS) 2nd Semester Curricula
            'IT 1' => ['ITP2', 'ITP2L', 'AVE', 'AVEL', 'KOMFIL', 'MATHWRLD', 'PATHFIT1', 'PURPCOMM'],
            'IT 2' => ['DBMSYS', 'DBMSYSL', 'IPT1', 'IPT1L', 'NW1', 'NW1L', 'WST', 'WSTL'],
            'IT 3' => ['SIA2', 'SIA2L', 'PRELEC2', 'PRELEC2L', 'PT', 'PTL', 'DMATH', 'CAO'],
            'IT 4' => ['CAPS2', 'CAPS2L', 'IAS2', 'IAS2L', 'BUSANA', 'SPI', 'LEAD 7'],

            // COA (Accountancy) 2nd Semester Curricula
            'ACC 1' => ['FINACC', 'FUNDACC 1', 'MANECO', 'KOMFIL', 'QM-TQM', 'PATHFIT1', 'UNDSELF', 'CONWRLD'],
            'ACC 2' => ['INTACC 2', 'SCOSMAN', 'BLAWREG', 'INCTAX', 'IT-ATB', 'MANSCI', 'ETHICS'],
            'ACC 3' => ['AACAP 1', 'AAPRIN', 'ACCBC', 'ACCST', 'FINMAN', 'STASSAP', 'HUBEORG'],
            'ACC 4' => ['ACCINTERN', 'ACCRES', 'SBUSANA', 'LEAD 7', 'AACAP 1', 'FINMAN'],

            // COE (Education) 2nd Semester Curricula
            'EDUC 1' => ['EDTECH 1', 'TPROF', 'FALECT', 'FOSPED', 'ENVISCI', 'ARTAPP', 'MATHINV', 'KOMFIL'],
            'ELEM 2' => ['TMATH 1', 'TSS 1', 'TFIL 1', 'TSCI 1', 'SOSLIT', 'PATHFIT3', 'LEAD 3'],
            'FIL 2' => ['LINGGWIS', 'RIZAL', 'PANREH', 'SOSLIT', 'PATHFIT3', 'LEAD 3', 'EDTECH 1'],
            'ENG 2' => ['LCS', 'ESTRUCT', 'SOSLIT', 'PATHFIT3', 'LEAD 3', 'EDTECH 1', 'BENLAC'],
            'SOCSCI 2' => ['POLGOV', 'PLANDWORLD', 'SOSLIT', 'RIZAL', 'PATHFIT3', 'LEAD 3', 'EDTECH 1'],
            'VAL 2' => ['PHILLET', 'PHILSOC', 'SOSLIT', 'PATHFIT3', 'LEAD 3', 'EDTECH 1', 'BENLAC'],
            'ELEM 3' => ['EPP', 'FERCE', 'TSS 2', 'LEAD 5', 'TLARTS', 'TMUSIC', 'TLIT'],
            'FIL 3' => ['BARWIKA', 'DULA', 'OBRABASA', 'PANPAM', 'KWENBEL', 'LEAD 5', 'SALIN'],
            'ENG 3' => ['TASLIT', 'CAMJOURN', 'CREWRIT', 'TASGRAM', 'TASMAC', 'LEAD 5', 'CHILDLIT'],
            'SOC 3' => ['WORLDHIS1', 'PRODMAT', 'APPSOC', 'MACROECO', 'CONTPHIL', 'TRENDSOC', 'LEAD 5'],
            'VAL 3' => ['APPVAL', 'INTROGUIDE', 'FATPRAC', 'DEVMAT', 'LEAD 5', 'CONTFALI', 'TRANSED'],
            'ELEM 4' => ['FS 2', 'RES 2', 'LEAD 7', 'FS 1'],
            'ENG 4' => ['FS 2', 'RES 2', 'LEAD 7', 'REMINST'],
            'TCP' => ['TCP 2', 'TCP 3', 'TCP 1'],

            // CBAE (Business & Entrep) 2nd Semester Curricula
            'FM 1' => ['FUNDACC', 'P.MGT', 'P.MKTG', 'MATHWRLD', 'NSTP 1', 'PATHFIT1', 'PURPCOMM', 'UNDSELF'],
            'EN 1' => ['P.MGT', 'P.MKTG', 'ARTAPP', 'ETHICS', 'NSTP 1', 'PATHFIT1', 'LEAD 1', 'UNDSELF'],
            'MM 1' => ['P.MGT', 'P.MKTG', 'GGSR', 'SOSLIT', 'MATHINV', 'ARTAPP', 'PATHFIT1', 'UNDSELF'],
            'HR 1' => ['P.MGT', 'P.MKTG', 'MATHINV', 'ETHICS', 'NSTP 1', 'PATHFIT1', 'KOMFIL', 'UNDSELF'],
            'FM 2' => ['FINMAN', 'FRANCH', 'RIZAL', 'CONWRLD', 'PATHFIT3', 'LEAD 3', 'BUSLAW'],
            'EN 2' => ['HRMAN', 'OPPOSE', 'ENTREBE', 'SOSLIT', 'PATHFIT3', 'LEAD 3', 'MANACC'],
            'MM 2' => ['FILDIS', 'ADVER', 'RIZAL', 'ADVCOM', 'OPMAN', 'PATHFIT3', 'LEAD 3'],
            'HR 2' => ['MARMAN', 'GGSR', 'TAX', 'SOSLIT', 'RECSEL', 'PATHFIT3', 'LEAD 3'],
            'FM 3' => ['BANFIN', 'STRAMAN', 'STATRES', 'BEHFIN', 'LEAD 5', 'OPMAN', 'MOPCEB'],
            'EN 3' => ['ENTREMAR', 'E-COMM', 'OPMAN', 'LEAD 5', 'TRACK 1', 'SOCENT', 'INOVMN'],
            'MM 3' => ['MARKRES', 'PRODMAN', 'LEAD 5', 'PRISTRAT', 'INTEBUS', 'DISMAN', 'MICECO'],
            'HR 3' => ['STRAMAN', 'LOGMAN', 'LEAD 5', 'OPMAN', 'COMPAD', 'INTEBUS', 'STATRES'],
            'EN 4' => ['TRACK 3', 'BP IMPLE1', 'LEAD 7'],
            'MM 4' => ['THESIS', 'MARKDEV', 'LEAD 7'],
            'HR 4' => ['THESIS', 'LABREL', 'LEAD 7'],
        ];

        $getSubjectListForSection = function ($secName) use ($sectionSubjectsMap, $subjects) {
            foreach ($sectionSubjectsMap as $key => $subjs) {
                if (str_starts_with($secName, $key)) {
                    return $subjs;
                }
            }

            return $subjects->take(7)->pluck('code')->all();
        };

        $subjectsByCode = $subjects->keyBy('code');
        $classInserts = [];
        $enrollmentInserts = [];
        $createdClasses = [];
        $classIdCounter = (int) DB::table('classes')->max('id') + 1;
        $secIndex = 0;

        foreach ($studentsBySection as $secName => $secStudents) {
            $firstStudent = $secStudents->first();
            $deptId = $firstStudent->program?->department_id;
            $deptFaculty = $facultyUsers->where('employee.department_id', $deptId)->values();
            if ($deptFaculty->count() < 3) {
                $deptFaculty = $facultyUsers->values();
            }

            $subjCodes = $getSubjectListForSection($secName);

            foreach ($subjCodes as $subjIdx => $code) {
                $subj = $subjectsByCode[$code] ?? $subjects->get($subjIdx % $subjects->count());
                if (! $subj) {
                    continue;
                }

                // Rotate faculty assignments across semesters using offset
                $fUser = $deptFaculty[($secIndex * 7 + $subjIdx + 13) % $deptFaculty->count()];
                $fEmp = $fUser->employee;

                $cId = $classIdCounter++;
                $sched = $times[($secIndex * 7 + $subjIdx) % count($times)];
                $room = $rooms[($secIndex * 7 + $subjIdx) % count($rooms)];

                $classInserts[] = [
                    'id' => $cId,
                    'subject_id' => $subj->id,
                    'semester_id' => $sem->id,
                    'teacher_id' => $fEmp->id,
                    'section' => $secName,
                    'schedule' => $sched,
                    'room' => $room,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Enroll ALL students in this section into this subject class
                foreach ($secStudents as $st) {
                    $enrollmentInserts[] = [
                        'class_id' => $cId,
                        'student_id' => $st->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $createdClasses[] = [
                    'class_id' => $cId,
                    'teacher_user_id' => $fUser->id,
                    'department_id' => $deptId,
                    'enrolled_students' => $secStudents,
                ];
            }

            $secIndex++;
        }

        foreach (array_chunk($classInserts, 200) as $chunk) {
            DB::table('classes')->insert($chunk);
        }
        foreach (array_chunk($enrollmentInserts, 500) as $chunk) {
            DB::table('class_student')->insert($chunk);
        }
        $this->command->info('Created '.count($classInserts).' classes and enrolled all '.count($enrollmentInserts).' students across 2nd Semester curriculum schedules.');

        // 7. Seed Multi-Role Evaluations
        $evalIdCounter = (int) DB::table('evaluations')->max('id') + 1;
        $evalInserts = [];
        $answerInserts = [];
        $sentimentInserts = [];

        $flushAll = function () use (&$evalInserts, &$answerInserts, &$sentimentInserts) {
            if (! empty($evalInserts)) {
                foreach (array_chunk($evalInserts, 300) as $chunk) {
                    DB::table('evaluations')->insert($chunk);
                }
                foreach (array_chunk($answerInserts, 500) as $chunk) {
                    DB::table('evaluation_answers')->insert($chunk);
                }
                foreach (array_chunk($sentimentInserts, 500) as $chunk) {
                    DB::table('evaluation_sentiments')->insert($chunk);
                }
                $evalInserts = [];
                $answerInserts = [];
                $sentimentInserts = [];
            }
        };

        // Distinct department completion benchmarks (e.g. CCS: 73.8%, COA: 72.4%, COE: 70.1%, CBAE: 71.6%)
        $deptTurnoutRates = [
            1 => 0.738, // CCS
            2 => 0.724, // COA
            3 => 0.701, // COE
            4 => 0.716, // CBAE
        ];

        // A. Student -> Teacher Evaluations (~71.5% historical completion turnout)
        $this->command->info('Seeding Student Evaluations for 2025-2026 2nd Semester...');
        foreach ($createdClasses as $cData) {
            $enrolled = $cData['enrolled_students'];
            $turnoutRate = $deptTurnoutRates[$cData['department_id']] ?? 0.715;
            $studentsToEval = (int) round($enrolled->count() * $turnoutRate);

            foreach ($enrolled->take($studentsToEval) as $st) {
                if (! $st->user) {
                    continue;
                }

                $rand = mt_rand(1, 100);
                // Sentiment distribution: 64% positive, 24% neutral, 12% negative
                $sentiment = $rand <= 64 ? 'positive' : ($rand <= 88 ? 'neutral' : 'negative');
                $calc = $generateAnswersAndScore('upward_student', $sentiment);
                $commentPool = $studentComments[$sentiment];
                $comment = $commentPool[array_rand($commentPool)];
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $st->user->id,
                    'evaluatee_id' => $cData['teacher_user_id'],
                    'semester_id' => $sem->id,
                    'class_id' => $cData['class_id'],
                    'evaluation_type' => 'upward_student',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $comment,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = [
                        'evaluation_id' => $currentEvalId,
                        'question_id' => $qId,
                        'rating' => $rating,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $sentimentInserts[] = [
                    'evaluation_id' => $currentEvalId,
                    'vader_score' => $sentimentMeta['vader_score'],
                    'vader_label' => $sentimentMeta['vader_label'],
                    'dt_label' => $sentimentMeta['dt_label'],
                    'manual_label' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($evalInserts) >= 300) {
                    $flushAll();
                }
            }
        }
        $flushAll();

        // B. Program Head Evaluations (3 of 4 completed 100%)
        $this->command->info('Seeding Program Head Evaluations...');
        foreach ($programHeadUsers as $idx => $phUser) {
            $phDeptId = $phUser->employee?->department_id;
            $deptFaculty = $facultyUsers->where('employee.department_id', $phDeptId);
            $isPhCompleted = ($idx < 3);
            $facultyToEval = $isPhCompleted ? $deptFaculty->count() : (int) ceil($deptFaculty->count() * 0.6);

            foreach ($deptFaculty->take($facultyToEval) as $fUser) {
                $calc = $generateAnswersAndScore('program_head', 'positive');
                $sentimentMeta = $determineSentimentData('positive', $calc['rating_average']);
                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $phUser->id,
                    'evaluatee_id' => $fUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'program_head',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => 'Demonstrates instructional quality and curriculum alignment.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => $sentimentMeta['vader_score'], 'vader_label' => $sentimentMeta['vader_label'], 'dt_label' => $sentimentMeta['dt_label'], 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($isPhCompleted) {
                // PH Self
                $calc = $generateAnswersAndScore('self', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $phUser->id,
                    'evaluatee_id' => $phUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'self',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $selfComments['positive'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.65, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];

                // PH Upward to Dean
                if ($deanUser) {
                    $calc = $generateAnswersAndScore('upward_employee', 'positive');
                    $currentEvalId = $evalIdCounter++;
                    $evalInserts[] = [
                        'id' => $currentEvalId,
                        'evaluator_id' => $phUser->id,
                        'evaluatee_id' => $deanUser->id,
                        'semester_id' => $sem->id,
                        'class_id' => null,
                        'evaluation_type' => 'upward_employee',
                        'rating_average' => $calc['rating_average'],
                        'raw_score' => $calc['raw_score'],
                        'max_score' => $calc['max_score'],
                        'weighted_score' => $calc['weighted_score'],
                        'comments' => $supervisorComments['positive'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    foreach ($calc['answers'] as $qId => $rating) {
                        $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                    }
                    $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.70, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        $flushAll();

        // C. Faculty Self, Peer & Upward (36 of 50 completed 100%)
        $this->command->info('Seeding Faculty Self, Peer & Upward Evaluations...');
        foreach ($facultyUsers as $fIdx => $fUser) {
            $fDeptId = $fUser->employee?->department_id;
            $isFacCompleted = ($fIdx < 36);

            if ($isFacCompleted) {
                // Self
                $calc = $generateAnswersAndScore('self', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $fUser->id,
                    'evaluatee_id' => $fUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'self',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $selfComments['positive'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.60, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            // Peers
            $peers = $facultyUsers->where('employee.department_id', $fDeptId)->where('id', '!==', $fUser->id);
            $peerCount = $isFacCompleted ? $peers->count() : (int) ceil($peers->count() * 0.6);

            foreach ($peers->take($peerCount) as $peerUser) {
                $calc = $generateAnswersAndScore('peer', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $fUser->id,
                    'evaluatee_id' => $peerUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'peer',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $peerComments['positive'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.55, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            // Upward to PH
            if ($isFacCompleted || ($fIdx % 2 === 0)) {
                $targetPH = $programHeadUsers->where('employee.department_id', $fDeptId)->first();
                if ($targetPH) {
                    $calc = $generateAnswersAndScore('upward_employee', 'positive');
                    $currentEvalId = $evalIdCounter++;
                    $evalInserts[] = [
                        'id' => $currentEvalId,
                        'evaluator_id' => $fUser->id,
                        'evaluatee_id' => $targetPH->id,
                        'semester_id' => $sem->id,
                        'class_id' => null,
                        'evaluation_type' => 'upward_employee',
                        'rating_average' => $calc['rating_average'],
                        'raw_score' => $calc['raw_score'],
                        'max_score' => $calc['max_score'],
                        'weighted_score' => $calc['weighted_score'],
                        'comments' => $supervisorComments['positive'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    foreach ($calc['answers'] as $qId => $rating) {
                        $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                    }
                    $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.65, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
                }
            }

            if (count($evalInserts) >= 300) {
                $flushAll();
            }
        }
        $flushAll();

        // D. Department Heads & Staff Evaluations
        $this->command->info('Seeding Department Head and Staff Evaluations...');
        foreach ($deptHeadUsers as $dhUser) {
            $dhDeptId = $dhUser->employee?->department_id;
            $deptStaff = $staffUsers->where('employee.department_id', $dhDeptId);

            foreach ($deptStaff as $sUser) {
                $calc = $generateAnswersAndScore('department_head', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $dhUser->id,
                    'evaluatee_id' => $sUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'department_head',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => 'Produces organized, dependable administrative outputs.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.62, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            // DH Self
            $calc = $generateAnswersAndScore('self', 'positive');
            $currentEvalId = $evalIdCounter++;
            $evalInserts[] = [
                'id' => $currentEvalId,
                'evaluator_id' => $dhUser->id,
                'evaluatee_id' => $dhUser->id,
                'semester_id' => $sem->id,
                'class_id' => null,
                'evaluation_type' => 'self',
                'rating_average' => $calc['rating_average'],
                'raw_score' => $calc['raw_score'],
                'max_score' => $calc['max_score'],
                'weighted_score' => $calc['weighted_score'],
                'comments' => $selfComments['positive'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach ($calc['answers'] as $qId => $rating) {
                $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
            }
            $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.65, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];

            // DH Upward to Dean
            if ($deanUser) {
                $calc = $generateAnswersAndScore('upward_employee', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $dhUser->id,
                    'evaluatee_id' => $deanUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'upward_employee',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $supervisorComments['positive'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.70, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $flushAll();

        // Staff
        foreach ($staffUsers as $sIdx => $sUser) {
            $sDeptId = $sUser->employee?->department_id;
            $deptStaffUsers = $staffUsers->where('employee.department_id', $sDeptId)->values();
            $localIdx = $deptStaffUsers->search(fn ($u) => $u->id === $sUser->id);
            $isStaffCompleted = ($localIdx !== false && $localIdx < (int) ceil($deptStaffUsers->count() * 0.72));

            if ($isStaffCompleted) {
                $calc = $generateAnswersAndScore('self', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $sUser->id,
                    'evaluatee_id' => $sUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'self',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $selfComments['positive'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.60, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            $peers = $staffUsers->where('employee.department_id', $sDeptId)->where('id', '!==', $sUser->id);
            $peerCount = $isStaffCompleted ? $peers->count() : (int) ceil($peers->count() * 0.6);

            foreach ($peers->take($peerCount) as $peerUser) {
                $calc = $generateAnswersAndScore('peer', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $sUser->id,
                    'evaluatee_id' => $peerUser->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'peer',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $peerComments['positive'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.55, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            $targetDH = $deptHeadUsers->where('employee.department_id', $sDeptId)->first();
            if ($targetDH && ($isStaffCompleted || ($sIdx % 2 === 0))) {
                $calc = $generateAnswersAndScore('upward_employee', 'positive');
                $currentEvalId = $evalIdCounter++;
                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $sUser->id,
                    'evaluatee_id' => $targetDH->id,
                    'semester_id' => $sem->id,
                    'class_id' => null,
                    'evaluation_type' => 'upward_employee',
                    'rating_average' => $calc['rating_average'],
                    'raw_score' => $calc['raw_score'],
                    'max_score' => $calc['max_score'],
                    'weighted_score' => $calc['weighted_score'],
                    'comments' => $supervisorComments['positive'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                foreach ($calc['answers'] as $qId => $rating) {
                    $answerInserts[] = ['evaluation_id' => $currentEvalId, 'question_id' => $qId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now];
                }
                $sentimentInserts[] = ['evaluation_id' => $currentEvalId, 'vader_score' => 0.65, 'vader_label' => 'positive', 'dt_label' => 'positive', 'manual_label' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            if (count($evalInserts) >= 300) {
                $flushAll();
            }
        }
        $flushAll();

        $totalEvaluations = DB::table('evaluations')->where('semester_id', $sem->id)->count();
        $avgScore = DB::table('evaluations')->where('semester_id', $sem->id)->avg('rating_average');
        $this->command->info("Seeding complete for {$ay->name} - {$sem->name}! Total evaluations: {$totalEvaluations} (Average Rating: ".round($avgScore, 2).')');
    }
}
