<?php

namespace Database\Seeders;

use App\Models\AcademicClass;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationSummary;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EvaluationPhase2Seeder extends Seeder
{
    /**
     * Run the database seeds for Phase 2.
     */
    public function run(): void
    {
        $activeSemester = Semester::where('is_active', true)->first();
        if (! $activeSemester) {
            $this->command->error('No active semester found! Aborting Phase 2.');

            return;
        }

        // Cache all evaluation criteria and questions by type
        $criteriaByType = EvaluationCriterion::with('questions')->get()->groupBy('evaluation_type');

        // Pre-compile question point definitions for fast mathematical computation
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

        // Multilingual Comments Bank
        $studentComments = [
            'positive' => [
                'en' => [
                    'Explains the subject matter clearly and always provides great real-world examples. Highly recommended!',
                    'One of the most dedicated and approachable professors this semester. I learned so much in this course.',
                    'The lectures are well-structured, engaging, and easy to follow. Always comes to class prepared.',
                    'Very considerate with deadlines while maintaining high standards. Encourages active class discussions.',
                    'Returns graded quizzes promptly with constructive feedback. Excellent classroom management.',
                    'Always on time, patient with questions, and makes complex topics enjoyable and easy to grasp.',
                ],
                'taglish' => [
                    'Sobrang galing magturo ni Sir/Ma\'am! Malinaw mag-explain lalo na kapag mahirap yung topic.',
                    'Super hands-on at approachable sa students. Hindi nakakatakot magtanong kapag may hindi naintindihan.',
                    'Organized ang mga lecture slides and nagbibigay ng extra review materials bago mag-exam.',
                    'Ganda ng teaching method, madaling masundan at engaging lagi ang class discussions.',
                    'Very considerate sa students pero marami talaga kaming natututunan sa bawat lesson.',
                    'Laging prepared pumasok sa klase at mabilis magbalik ng graded seatworks with helpful comments.',
                ],
                'fil' => [
                    'Napakahusay magpaliwanag ng bawat aralin at laging maayos at handa sa klase.',
                    'Matiyagang sumasagot sa mga katanungan ng mga mag-aaral nang may paggalang at linaw.',
                    'Makatwiran magbigay ng marka at maayos ang pamamalakad sa loob ng silid-aralan.',
                    'Nagbibigay ng mga totoong halimbawa na madaling maiugnay sa aming pang-araw-araw na karanasan.',
                    'Laging maagap sa pagpasok at nagpapakita ng mataas na dedikasyon sa pagtuturo.',
                ],
            ],
            'neutral' => [
                'en' => [
                    'The lectures are informative, though the pacing is sometimes a bit too fast for complex lessons.',
                    'Good discussions in class, but more time for hands-on activities would be greatly appreciated.',
                    'Clear instructions on projects, though sharing quiz results earlier would help us prepare better.',
                    'Knowledgeable professor, but it would help if lecture slides are uploaded ahead of class.',
                ],
                'taglish' => [
                    'Okay naman po magturo, medyo mabilis lang minsan ang pacing kaya kailangan mag-self study afterwards.',
                    'Magaling si Sir/Ma\'am sa subject, sana lang po maibalik agad yung mga previous seatworks bago mag-midterm.',
                    'Maayos ang class management, pero sana mas madaming practical exercises bago magbigay ng complex activity.',
                    'Clear naman ang expectations, though minsan medyo sabay-sabay ang deadline ng requirements.',
                ],
                'fil' => [
                    'Mabuti ang pagtuturo subalit sana ay medyo dahan-dahan sa mga mahihirap na paksa.',
                    'Maliwanag ang mga patakaran, ngunit makabubuti kung mas maagang maipapamahagi ang mga marka sa pagsusulit.',
                    'Maayos ang talakayan, nawa ay mabigyan pa ng mas maraming panahon ang paggawa ng mga pagsasanay.',
                ],
            ],
            'negative' => [
                'en' => [
                    'Often reads directly from slides without explaining underlying concepts. Needs more interactive examples.',
                    'Late feedback on assignments makes it difficult to know how to improve before midterm exams.',
                    'Needs to improve class pacing and ensure all student questions are properly answered.',
                    'Inconsistent adherence to class syllabus and delayed return of corrected test papers.',
                ],
                'taglish' => [
                    'Medyo binabasa lang po minsan ang PPT slides, sana po mas ipaliwanag gamit ang actual practical examples.',
                    'Madalas po matagal bago maibalik ang quizzes kaya hindi namin alam kung saan kami nagkamali.',
                    'Minsan po medyo mabilis mag-discuss at hindi masyadong na-e-entertain ang clarifications ng klase.',
                ],
                'fil' => [
                    'Kailangan ng mas malinaw na paliwanag sa mga kumplikadong paksa sa halip na pagbasa lamang ng mga tala.',
                    'Nawa ay mas mapabilis ang pagbabalik ng mga naiwastong pagsusulit upang malaman ang mga dapat iwasto.',
                ],
            ],
        ];

        $peerComments = [
            'positive' => [
                'en' => [
                    'A dedicated colleague who actively shares curriculum resources and collaborates willingly in departmental initiatives.',
                    'Consistently demonstrates professional competence and upholds ethical conduct in all faculty activities.',
                    'Always supportive of departmental goals, dependable in committee assignments, and respectful to peers.',
                ],
                'taglish' => [
                    'Sobrang cooperative kasama sa department committees, laging ready tumulong at mag-share ng learning materials.',
                    'Maayos makisama sa kapwa faculty at laging maaasahan sa mga departmental tasks and activities.',
                ],
                'fil' => [
                    'Maaasahang kasamahan sa kagawaran na laging nagpapamalas ng propesyonalismo at pagkakaisa.',
                    'Tapat sa tungkulin at laging handang tumulong sa mga gawaing pang-institusyon.',
                ],
            ],
            'neutral' => [
                'en' => [
                    'Competent and reliable in subject delivery, though could participate more actively in committee meetings.',
                    'Maintains good collegial relations. Encouraged to collaborate more on interdisciplinary projects.',
                ],
                'taglish' => [
                    'Maayos makisama at dependable sa klase, sana lang mas makadalo sa mga departmental gatherings.',
                ],
                'fil' => [
                    'Maayos ang pagtupad sa tungkulin, inaasahan ang mas aktibong pakikilahok sa mga pagpupulong.',
                ],
            ],
            'negative' => [
                'en' => [
                    'Needs to improve attendance in departmental coordination meetings and submit course materials on schedule.',
                ],
                'taglish' => [
                    'Kailangan pa ng mas maagap na pakikipag-ugnayan sa kapwa faculty sa mga departmental requirements.',
                ],
                'fil' => [
                    'Nangangailangan ng higit na pakikipagtulungan sa mga kapwa guro at mas maagap na pagsumite ng ulat.',
                ],
            ],
        ];

        $supervisorComments = [
            'positive' => [
                'en' => [
                    'Consistently submits syllabi, TOS, and grades on time. Exhibits commendable classroom management and mastery.',
                    'Exemplifies GRC core values, takes initiative in academic programs, and maintains high student satisfaction.',
                    'Displays strong leadership within the classroom, punctual attendance, and sound pedagogical methods.',
                ],
                'taglish' => [
                    'Maagap magpasa ng departmental requirements at maayos ang pamamahala sa klase batay sa feedback ng students.',
                    'Magandang ehemplo sa department, masipag at laging handang tumanggap ng karagdagang responsibilidad.',
                ],
                'fil' => [
                    'Nagpapakita ng huwarang dedikasyon sa pagtuturo at tapat na sumusunod sa mga patakaran ng institusyon.',
                    'Matiyaga, maagap sa pagpasa ng mga ulat, at mahusay mamahala sa mga mag-aaral.',
                ],
            ],
            'neutral' => [
                'en' => [
                    'Strong instructional capabilities. Encouraged to maintain strictly punctual submission of grade sheets.',
                    'Performs duties competently. Recommended to incorporate more active learning tools in the syllabus.',
                ],
                'taglish' => [
                    'Magaling sa subject matter, kailangan lang panatilihin ang maagap na pagpasa ng grade sheets at records.',
                ],
                'fil' => [
                    'Mahusay sa larangan ng pagtuturo, paalala lamang sa maagap na pagbibigay ng mga talaan ng marka.',
                ],
            ],
            'negative' => [
                'en' => [
                    'Requires closer monitoring on syllabus completion, classroom punctuality, and timely grade submission.',
                ],
                'taglish' => [
                    'Kailangang paalalahanan sa pagiging maagap sa klase at sa pagsumite ng mga departmental records.',
                ],
                'fil' => [
                    'Kinakailangan ng mas mahigpit na pagsunod sa takdang oras ng klase at pagsumite ng mga kailangang dokumento.',
                ],
            ],
        ];

        $selfComments = [
            'positive' => [
                'I have continuously updated my lecture materials, engaged students in interactive discussions, and adhered faithfully to GRC policies.',
                'Napanatili ko ang maayos na pamamahala sa klase, maagap na pagpasa ng marka, at aktibong pakikilahok sa mga gawaing pang-kagawaran.',
                'Strived to provide a supportive and challenging learning environment for my students throughout the academic term.',
            ],
            'neutral' => [
                'Accomplished the syllabus objectives successfully. I plan to incorporate more digital learning tools in the upcoming term.',
                'Maayos na naisagawa ang mga aralin, subalit nais ko pang paunlarin ang paggamit ng makabagong pamamaraan sa pagtuturo.',
            ],
        ];

        $staffComments = [
            'positive' => [
                'en' => [
                    'Demonstrates excellent customer service, prompt response to student queries, and meticulous record keeping.',
                    'A highly dependable team member who takes initiative and collaborates effectively with all departments.',
                ],
                'taglish' => [
                    'Laging magalang at mabilis mag-asikaso sa mga estudyante at faculty na may kailangan sa opisina.',
                    'Maaasahan sa lahat ng office assignments at laging maayos ang pag-organize ng files.',
                ],
                'fil' => [
                    'Nagpapakita ng tapat at maagap na paglilingkod sa mga mag-aaral at kawani ng paaralan.',
                ],
            ],
            'neutral' => [
                'en' => [
                    'Accomplishes daily office responsibilities reliably. Encouraged to optimize turnaround time for record requests.',
                ],
                'taglish' => [
                    'Maayos ang trabaho sa opisina, sana lang mas mapabilis pa ang processing ng mga documents kapag peak season.',
                ],
                'fil' => [
                    'Maayos ang pagtupad sa tungkulin, paalala lamang sa mas maagap na pag-aasikaso ng mga kahilingan.',
                ],
            ],
            'negative' => [
                'en' => [
                    'Needs to improve punctuality and observe better communication when handling student complaints.',
                ],
                'taglish' => [
                    'Kailangan pang ayusin ang customer service at panatilihin ang maayos na pakikitungo sa mga bisita.',
                ],
                'fil' => [
                    'Nangangailangan ng higit na pagsisikap sa maayos na pakikitungo at pagiging maagap sa pagpasok.',
                ],
            ],
        ];

        // Helper closures
        $pickComment = function (array $bank, string $sentiment) {
            $byLang = $bank[$sentiment] ?? $bank['positive'];
            $langKeys = array_keys($byLang);
            $chosenLang = $langKeys[array_rand($langKeys)];
            $options = $byLang[$chosenLang];

            return $options[array_rand($options)];
        };

        $generateAnswersAndScore = function (string $evalType, string $sentiment) use ($questionsData, $activeSemester) {
            $questions = $questionsData[$evalType] ?? [];
            if (empty($questions)) {
                return [
                    'answers' => [],
                    'rating_average' => 4.0,
                    'raw_score' => 40.0,
                    'max_score' => 40.0,
                    'weighted_score' => 20.0,
                ];
            }

            $answers = [];
            $totalRating = 0;
            $rawScore = 0.0;

            foreach ($questions as $q) {
                if ($sentiment === 'positive') {
                    $rating = mt_rand(1, 100) <= 75 ? 5 : 4;
                } elseif ($sentiment === 'neutral') {
                    $rand = mt_rand(1, 100);
                    $rating = $rand <= 40 ? 3 : ($rand <= 80 ? 4 : 5);
                } else {
                    $rand = mt_rand(1, 100);
                    $rating = $rand <= 45 ? 2 : ($rand <= 80 ? 1 : 3);
                }

                $answers[$q['id']] = $rating;
                $totalRating += $rating;

                $qPoints = ($rating / 5.0) * ($q['max_points'] / max(1, $q['q_count']));
                $rawScore += $qPoints;
            }

            $count = count($questions);
            $avgRating = round($totalRating / max(1, $count), 2);
            $rawScore = round($rawScore, 2);

            $maxScore = (float) $activeSemester->getCategoryMaxPoints($evalType);
            if ($maxScore <= 0) {
                $maxScore = (float) array_sum(array_column($questions, 'max_points'));
            }
            $weight = (float) $activeSemester->getCategoryWeight($evalType);
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
                $vaderScore = round(0.45 + (mt_rand(0, 50) / 100), 4);
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

        // Cache Users and Employees
        $facultyUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'faculty'))->with('employee.department')->get();
        $programHeadUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'program head'))->with('employee.department')->get();
        $deanUser = User::whereHas('employee', fn ($q) => $q->where('role', 'dean'))->with('employee.department')->first();
        $deptHeadUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'department head'))->with('employee.department')->get();
        $staffUsers = User::whereHas('employee', fn ($q) => $q->where('role', 'staff'))->with('employee.department')->get();

        $this->command->info('1. Seeding Student Evaluations (~75% completed, ~25% pending for demo)...');
        $classes = AcademicClass::with(['teacher.user', 'students.user'])->get();

        $evalIdCounter = 1;
        $evalInserts = [];
        $answerInserts = [];
        $sentimentInserts = [];
        $now = now()->toDateTimeString();

        foreach ($classes as $class) {
            if (! $class->teacher || ! $class->teacher->user) {
                continue;
            }

            $teacherUser = $class->teacher->user;
            $students = $class->students;
            $totalEnrolled = $students->count();

            // Evaluate approximately 75% of enrolled students, leaving ~25% pending
            $evaluateCount = (int) round($totalEnrolled * 0.75);

            foreach ($students->take($evaluateCount) as $student) {
                if (! $student->user) {
                    continue;
                }

                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 70 ? 'positive' : ($rand <= 90 ? 'neutral' : 'negative');

                $calc = $generateAnswersAndScore('upward_student', $sentiment);
                $comment = $pickComment($studentComments, $sentiment);
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $student->user->id,
                    'evaluatee_id' => $teacherUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => $class->id,
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
            }
        }

        $this->command->info('2. Seeding Dean Evaluations (40/50 Faculty & 3/4 Program Heads completed)...');
        if ($deanUser) {
            // Dean -> Faculty (40 completed, 10 pending)
            foreach ($facultyUsers->take(40) as $fUser) {
                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 75 ? 'positive' : ($rand <= 92 ? 'neutral' : 'negative');
                $calc = $generateAnswersAndScore('dean', $sentiment);
                $comment = $pickComment($supervisorComments, $sentiment);
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $deanUser->id,
                    'evaluatee_id' => $fUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'dean',
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
            }

            // Dean -> Program Heads (3 completed, 1 pending)
            foreach ($programHeadUsers->take(3) as $phUser) {
                $calc = $generateAnswersAndScore('dean', 'positive');
                $comment = $pickComment($supervisorComments, 'positive');
                $sentimentMeta = $determineSentimentData('positive', $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $deanUser->id,
                    'evaluatee_id' => $phUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'dean',
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
            }
        }

        $this->command->info('3. Seeding Program Head Evaluations (~75% completed per college)...');
        foreach ($programHeadUsers as $idx => $phUser) {
            $phDeptId = $phUser->employee?->department_id;
            $deptFaculty = $facultyUsers->where('employee.department_id', $phDeptId);
            $facultyToEval = (int) ceil($deptFaculty->count() * 0.75);

            // Program Head -> Subordinate Faculty
            foreach ($deptFaculty->take($facultyToEval) as $fUser) {
                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 75 ? 'positive' : ($rand <= 90 ? 'neutral' : 'negative');
                $calc = $generateAnswersAndScore('program_head', $sentiment);
                $comment = $pickComment($supervisorComments, $sentiment);
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $phUser->id,
                    'evaluatee_id' => $fUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'program_head',
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
            }

            // Program Head -> Self (3 completed, 1 pending)
            if ($idx < 3) {
                $calc = $generateAnswersAndScore('self', 'positive');
                $comment = $selfComments['positive'][array_rand($selfComments['positive'])];
                $sentimentMeta = $determineSentimentData('positive', $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $phUser->id,
                    'evaluatee_id' => $phUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'self',
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
            }

            // Program Head -> Dean (Upward evaluation, 3 completed, 1 pending)
            if ($idx < 3 && $deanUser) {
                $calc = $generateAnswersAndScore('upward_employee', 'positive');
                $comment = $pickComment($supervisorComments, 'positive');
                $sentimentMeta = $determineSentimentData('positive', $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $phUser->id,
                    'evaluatee_id' => $deanUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'upward_employee',
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
            }
        }

        $this->command->info('4. Seeding Faculty Peer, Self & Upward Evaluations (~75% completed)...');
        foreach ($facultyUsers as $fIdx => $fUser) {
            $fDeptId = $fUser->employee?->department_id;

            // Faculty -> Self (35 completed, 15 pending)
            if ($fIdx < 35) {
                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 80 ? 'positive' : 'neutral';
                $calc = $generateAnswersAndScore('self', $sentiment);
                $comment = $selfComments[$sentiment][array_rand($selfComments[$sentiment])];
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $fUser->id,
                    'evaluatee_id' => $fUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'self',
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
            }

            // Faculty -> Peers in same department (~75% completed)
            $peers = $facultyUsers->where('employee.department_id', $fDeptId)->where('id', '!==', $fUser->id);
            $peerEvalCount = (int) ceil($peers->count() * 0.75);

            foreach ($peers->take($peerEvalCount) as $peerUser) {
                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 75 ? 'positive' : ($rand <= 90 ? 'neutral' : 'negative');
                $calc = $generateAnswersAndScore('peer', $sentiment);
                $comment = $pickComment($peerComments, $sentiment);
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $fUser->id,
                    'evaluatee_id' => $peerUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'peer',
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
            }

            // Faculty -> Program Head (Upward Evaluation, ~75% of faculty)
            if ($fIdx % 4 !== 0) {
                $targetPH = $programHeadUsers->where('employee.department_id', $fDeptId)->first();
                if ($targetPH) {
                    $rand = mt_rand(1, 100);
                    $sentiment = $rand <= 80 ? 'positive' : 'neutral';
                    $calc = $generateAnswersAndScore('upward_employee', $sentiment);
                    $comment = $pickComment($supervisorComments, $sentiment);
                    $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                    $currentEvalId = $evalIdCounter++;

                    $evalInserts[] = [
                        'id' => $currentEvalId,
                        'evaluator_id' => $fUser->id,
                        'evaluatee_id' => $targetPH->id,
                        'semester_id' => $activeSemester->id,
                        'class_id' => null,
                        'evaluation_type' => 'upward_employee',
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
                }
            }
        }

        $this->command->info('5. Seeding Department Heads & Staff Evaluations (~75% completed)...');
        // Department Head -> Staff (Downward)
        foreach ($deptHeadUsers as $dhIdx => $dhUser) {
            $dhDeptId = $dhUser->employee?->department_id;
            $deptStaff = $staffUsers->where('employee.department_id', $dhDeptId);
            $staffToEval = (int) ceil($deptStaff->count() * 0.75);

            foreach ($deptStaff->take($staffToEval) as $sUser) {
                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 75 ? 'positive' : ($rand <= 90 ? 'neutral' : 'negative');
                $calc = $generateAnswersAndScore('department_head', $sentiment);
                $comment = $pickComment($staffComments, $sentiment);
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $dhUser->id,
                    'evaluatee_id' => $sUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'department_head',
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
            }

            // Department Head -> Self (8 completed, 3 pending)
            if ($dhIdx < 8) {
                $calc = $generateAnswersAndScore('self', 'positive');
                $comment = $selfComments['positive'][array_rand($selfComments['positive'])];
                $sentimentMeta = $determineSentimentData('positive', $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $dhUser->id,
                    'evaluatee_id' => $dhUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'self',
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
            }
        }

        // Staff -> Self, Peers & Supervisor (DH)
        foreach ($staffUsers as $sIdx => $sUser) {
            $sDeptId = $sUser->employee?->department_id;

            // Staff -> Self (42 completed, 15 pending)
            if ($sIdx < 42) {
                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 80 ? 'positive' : 'neutral';
                $calc = $generateAnswersAndScore('self', $sentiment);
                $comment = $selfComments[$sentiment][array_rand($selfComments[$sentiment])];
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $sUser->id,
                    'evaluatee_id' => $sUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'self',
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
            }

            // Staff -> Peers in same admin dept
            $peers = $staffUsers->where('employee.department_id', $sDeptId)->where('id', '!==', $sUser->id);
            $peerEvalCount = (int) ceil($peers->count() * 0.75);

            foreach ($peers->take($peerEvalCount) as $peerUser) {
                $rand = mt_rand(1, 100);
                $sentiment = $rand <= 75 ? 'positive' : ($rand <= 90 ? 'neutral' : 'negative');
                $calc = $generateAnswersAndScore('peer', $sentiment);
                $comment = $pickComment($peerComments, $sentiment);
                $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                $currentEvalId = $evalIdCounter++;

                $evalInserts[] = [
                    'id' => $currentEvalId,
                    'evaluator_id' => $sUser->id,
                    'evaluatee_id' => $peerUser->id,
                    'semester_id' => $activeSemester->id,
                    'class_id' => null,
                    'evaluation_type' => 'peer',
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
            }

            // Staff -> Department Head (Upward Evaluation, ~75% of staff)
            if ($sIdx % 4 !== 0) {
                $targetDH = $deptHeadUsers->where('employee.department_id', $sDeptId)->first();
                if ($targetDH) {
                    $rand = mt_rand(1, 100);
                    $sentiment = $rand <= 80 ? 'positive' : 'neutral';
                    $calc = $generateAnswersAndScore('upward_employee', $sentiment);
                    $comment = $pickComment($supervisorComments, $sentiment);
                    $sentimentMeta = $determineSentimentData($sentiment, $calc['rating_average']);

                    $currentEvalId = $evalIdCounter++;

                    $evalInserts[] = [
                        'id' => $currentEvalId,
                        'evaluator_id' => $sUser->id,
                        'evaluatee_id' => $targetDH->id,
                        'semester_id' => $activeSemester->id,
                        'class_id' => null,
                        'evaluation_type' => 'upward_employee',
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
                }
            }
        }

        $this->command->info('6. Executing batch database insertions...');
        // Insert evaluations in chunks
        foreach (array_chunk($evalInserts, 500) as $chunk) {
            DB::table('evaluations')->insert($chunk);
        }
        $this->command->info('   -> Inserted '.count($evalInserts).' evaluation records.');

        // Insert answers in chunks
        foreach (array_chunk($answerInserts, 2000) as $chunk) {
            DB::table('evaluation_answers')->insert($chunk);
        }
        $this->command->info('   -> Inserted '.count($answerInserts).' answer records.');

        // Insert sentiments in chunks
        foreach (array_chunk($sentimentInserts, 500) as $chunk) {
            DB::table('evaluation_sentiments')->insert($chunk);
        }
        $this->command->info('   -> Inserted '.count($sentimentInserts).' sentiment records.');

        $this->command->info('7. Aggregating evaluation summaries for all employees...');
        $allEmployees = Employee::with('user')->get();
        foreach ($allEmployees as $emp) {
            if (! $emp->user) {
                continue;
            }

            $evaluations = Evaluation::where('evaluatee_id', $emp->user->id)
                ->where('semester_id', $activeSemester->id)
                ->get();

            if ($evaluations->isEmpty()) {
                continue;
            }

            $byType = $evaluations->groupBy('evaluation_type');

            $studentAvg = isset($byType['student']) || isset($byType['upward_student'])
                ? ($byType['student'] ?? $byType['upward_student'])->avg('weighted_score')
                : null;

            $deanAvg = isset($byType['dean'])
                ? $byType['dean']->avg('weighted_score')
                : null;

            $phDhAvg = isset($byType['program_head']) || isset($byType['ph_dh']) || isset($byType['department_head']) || isset($byType['downward'])
                ? ($byType['program_head'] ?? ($byType['ph_dh'] ?? ($byType['department_head'] ?? $byType['downward'])))->avg('weighted_score')
                : null;

            $peerAvg = isset($byType['peer'])
                ? $byType['peer']->avg('weighted_score')
                : null;

            $selfAvg = isset($byType['self'])
                ? $byType['self']->avg('weighted_score')
                : null;

            $superiorAvg = isset($byType['superior']) || isset($byType['upward_employee'])
                ? ($byType['superior'] ?? $byType['upward_employee'])->avg('weighted_score')
                : null;

            $scores = array_filter([$studentAvg, $deanAvg, $phDhAvg, $peerAvg, $selfAvg, $superiorAvg], fn ($v) => ! is_null($v));
            $overallRating = ! empty($scores) ? array_sum($scores) : 0.0;

            EvaluationSummary::updateOrCreate(
                [
                    'evaluatee_id' => $emp->id,
                    'semester_id' => $activeSemester->id,
                ],
                [
                    'student_score' => $studentAvg !== null ? round($studentAvg, 2) : null,
                    'dean_score' => $deanAvg !== null ? round($deanAvg, 2) : null,
                    'ph_dh_score' => $phDhAvg !== null ? round($phDhAvg, 2) : null,
                    'peer_score' => $peerAvg !== null ? round($peerAvg, 2) : null,
                    'self_score' => $selfAvg !== null ? round($selfAvg, 2) : null,
                    'superior_score' => $superiorAvg !== null ? round($superiorAvg, 2) : null,
                    'overall_rating' => round($overallRating, 2),
                    'total_submissions' => $evaluations->count(),
                ]
            );
        }

        $this->command->info('Phase 2 Evaluation population complete!');
    }
}
