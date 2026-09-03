<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ThematicAnalysisService
{
    /**
     * Common English, Tagalog, and academic filler stopwords to exclude from TF-IDF extraction.
     */
    protected static array $stopwords = [
        'the', 'and', 'to', 'a', 'of', 'in', 'is', 'it', 'you', 'that', 'was', 'for', 'on', 'are', 'as', 'with',
        'they', 'be', 'at', 'one', 'have', 'this', 'from', 'or', 'had', 'by', 'not', 'but', 'some', 'what', 'there',
        'we', 'can', 'out', 'other', 'were', 'all', 'your', 'when', 'up', 'use', 'how', 'an', 'each', 'which', 'she',
        'do', 'their', 'time', 'if', 'will', 'about', 'many', 'then', 'them', 'would', 'like', 'so', 'these', 'her',
        'has', 'more', 'could', 'no', 'who', 'than', 'been', 'now', 'find', 'get', 'made', 'may', 'part', 'make',
        'very', 'just', 'much', 'also', 'ang', 'ng', 'mga', 'sa', 'si', 'ni', 'kay', 'po', 'opo', 'kasi', 'naman',
        'ba', 'ay', 'yung', 'ung', 'na', 'pa', 'mas', 'sobrang', 'medyo', 'hindi', 'di', 'wala', 'huwag', 'nang',
        'para', 'at', 'dahil', 'kung', 'kapag', 'pag', 'nito', 'nila', 'kami', 'tayo', 'sila', 'ako', 'ikaw', 'siya',
        'ito', 'iyan', 'iyon', 'dito', 'diyan', 'doon', 'lahat', 'bawat', 'isa', 'mismo', 'lang', 'din', 'rin', 'daw',
        'raw', 'sana', 'nawa', 'maging', 'upang', 'habang', 'subalit', 'ngunit', 'pero', 'kaya', 'tapos', 'bago', 'lalo',
        'talaga', 'namin', 'naming', 'kanila', 'inyo', 'inyong', 'aming', 'ating', 'kanilang', 'sir', 'maam', 'prof',
        'teacher', 'instructor', 'professor', 'class', 'students', 'student', 'course', 'subject', 'semester', 'good', 'well',
    ];

    /**
     * Curated key academic themes and patterns (unigrams and phrases).
     */
    protected static array $positiveThematicPatterns = [
        'Approachable & Helpful' => ['approachable', 'hands-on', 'madaling lapitan'],
        'Clear Explanations' => ['clear', 'malinaw', 'paliwanag', 'explain'],
        'Well-Prepared for Class' => ['prepared', 'handa', 'organisado', 'structured'],
        'Engaging Discussions' => ['engaging', 'discussions', 'talakayan', 'active'],
        'Patient with Questions' => ['patient', 'matiyaga', 'pasensya'],
        'Real-World Examples' => ['real-world', 'totoong halimbawa', 'halimbawa', 'practical'],
        'High Dedication' => ['dedicated', 'dedikasyon', 'sipag', 'tiyaga'],
    ];

    protected static array $constructiveThematicPatterns = [
        'Delayed Return of Quizzes' => ['delayed', 'matagal', 'ibabalik', 'pagsusulit', 'quizzes'],
        'Lecture Pacing Too Fast' => ['mabilis', 'pacing', 'pabilisin', 'dahan-dahan'],
        'Reading Directly Off Slides' => ['slides', 'binabasa', 'ppt', 'powerpoint'],
        'Complex Topic Clarifications' => ['kumplikado', 'complex', 'mahirap'],
        'Inconsistent Class Schedule' => ['inconsistent', 'syllabus', 'schedule', 'patakaran'],
    ];

    /**
     * Extract top thematic keywords and driver phrases for an active evaluation semester.
     */
    public static function getThematicDrivers(?int $semesterId, int $limit = 5): array
    {
        if (! $semesterId) {
            return [
                'has_data' => false,
                'total_analyzed' => 0,
                'positive_drivers' => [],
                'constructive_drivers' => [],
            ];
        }

        return Cache::remember("dashboard_thematic_drivers_{$semesterId}_{$limit}", 1800, function () use ($semesterId, $limit) {
            $evaluations = DB::table('evaluations')
                ->leftJoin('evaluation_sentiments', 'evaluations.id', '=', 'evaluation_sentiments.evaluation_id')
                ->where('evaluations.semester_id', $semesterId)
                ->whereNotNull('evaluations.comments')
                ->where('evaluations.comments', '!=', '')
                ->select('evaluations.comments', 'evaluation_sentiments.vader_label', 'evaluation_sentiments.vader_score')
                ->get();

            $totalAnalyzed = $evaluations->count();
            if ($totalAnalyzed < 5) {
                return [
                    'has_data' => false,
                    'total_analyzed' => $totalAnalyzed,
                    'positive_drivers' => [],
                    'constructive_drivers' => [],
                ];
            }

            // Separate positive and negative/constructive corpora
            $posTexts = [];
            $negTexts = [];

            foreach ($evaluations as $e) {
                $text = (string) $e->comments;
                $label = $e->vader_label;
                $score = (float) $e->vader_score;

                if ($label === 'positive' || $score >= 0.05) {
                    $posTexts[] = $text;
                } else {
                    $negTexts[] = $text;
                }
            }

            $positiveDrivers = self::analyzeCorpusThemes($posTexts, self::$positiveThematicPatterns, $limit);
            $constructiveDrivers = self::analyzeCorpusThemes($negTexts, self::$constructiveThematicPatterns, $limit);

            return [
                'has_data' => count($positiveDrivers) > 0 || count($constructiveDrivers) > 0,
                'total_analyzed' => $totalAnalyzed,
                'positive_drivers' => $positiveDrivers,
                'constructive_drivers' => $constructiveDrivers,
            ];
        });
    }

    /**
     * Compute TF-IDF weighted frequency for patterns and unigrams in a specific text collection.
     */
    protected static function analyzeCorpusThemes(array $texts, array $patterns, int $limit): array
    {
        $totalDocs = count($texts);
        if ($totalDocs === 0) {
            return [];
        }

        $stopMap = array_flip(self::$stopwords);
        $themeCounts = [];

        // 1. Match curated thematic drivers
        foreach ($texts as $doc) {
            $lower = strtolower($doc);
            foreach ($patterns as $theme => $keywords) {
                $matched = false;
                foreach ($keywords as $kw) {
                    if (str_contains($lower, $kw)) {
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    $themeCounts[$theme] = ($themeCounts[$theme] ?? 0) + 1;
                }
            }
        }

        // 2. Also extract dynamic unigrams using TF-IDF to discover unexpected terms
        $docFreq = [];
        $termFreq = [];

        foreach ($texts as $docId => $doc) {
            $words = preg_split('/[^a-zA-Z\-]+/', strtolower($doc), -1, PREG_SPLIT_NO_EMPTY);
            $docWords = [];
            foreach ($words as $w) {
                $w = trim($w, '-');
                if (strlen($w) < 4 || isset($stopMap[$w])) {
                    continue;
                }
                $docWords[] = $w;
            }

            if (empty($docWords)) {
                continue;
            }

            $wordCounts = array_count_values($docWords);
            $docLen = count($docWords);
            foreach ($wordCounts as $w => $c) {
                $termFreq[$w] = ($termFreq[$w] ?? 0) + ($c / $docLen);
                $docFreq[$w] = ($docFreq[$w] ?? 0) + 1;
            }
        }

        $tfidf = [];
        foreach ($termFreq as $w => $tf) {
            $df = $docFreq[$w] ?? 1;
            $idf = log(1 + ($totalDocs / (1 + $df)));
            $tfidf[$w] = $tf * $idf;
        }
        arsort($tfidf);

        // Normalize curated themes
        $results = [];
        $maxCount = max(1, count($themeCounts) > 0 ? max($themeCounts) : 1);

        foreach ($themeCounts as $theme => $count) {
            $weight = min(100, max(20, round(($count / $maxCount) * 100)));
            $results[] = [
                'term' => $theme,
                'count' => $count,
                'weight' => $weight,
            ];
        }

        // Sort descending by count
        usort($results, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($results, 0, $limit);
    }
}
