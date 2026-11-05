<?php

namespace App\Services;

use App\Models\DailyJournal;
use Illuminate\Support\Str;

/**
 * Transparent, deterministic fallback analysis.
 *
 * It deliberately does not call itself AI. It looks for completed actions,
 * concrete outcomes, evidence, and role-relevant signals, and lowers scores for
 * vague, exaggerated, or repeated entries.
 */
class RuleBasedAchievementAnalyzer
{
    private const COMPLETED_ACTIONS = [
        'completed', 'complete', 'finished', 'submitted', 'filed', 'prepared',
        'reconciled', 'reviewed', 'resolved', 'processed', 'closed', 'delivered',
        'updated', 'verified', 'coordinated', 'assisted', 'trained', 'implemented',
        'improved', 'automated', 'fixed', 'drafted', 'organized', 'handled',
        'managed', 'achieved', 'reduced', 'saved', 'led', 'created', 'checked',
        'audited', 'encoded', 'recorded', 'compiled', 'finalized', 'approved',
        'answered', 'responded', 'documented', 'reported', 'monitored', 'settled',
    ];

    private const OUTCOME_PHRASES = [
        'on time', 'ahead of schedule', 'before the deadline', 'within the deadline',
        'same day', 'zero errors', 'error-free', 'error free', 'client approved',
        'approved by', 'resolved the issue', 'completed ahead', 'under budget',
        'reduced by', 'saved time', 'met the deadline',
    ];

    private const SUPERLATIVES = [
        'everything', 'all tasks', 'always', 'never', '100%', 'perfect',
        'flawless', 'exceeded all', 'nothing left', 'single-handedly',
    ];

    private const CATEGORY_KEYWORDS = [
        'Productivity' => [
            'completed', 'finished', 'processed', 'prepared', 'filed', 'submitted',
            'task', 'tasks', 'accomplished', 'daily', 'volume', 'batch', 'entries',
            'reports', 'bookkeeping', 'payroll', 'clients',
        ],
        'Quality and Accuracy' => [
            'accurate', 'accuracy', 'verified', 'reviewed', 'checked', 'reconciled',
            'error', 'errors', 'compliance', 'audit', 'validated', 'corrected',
            'zero errors', 'error-free', 'quality',
        ],
        'Initiative' => [
            'proactively', 'proactive', 'initiated', 'proposed', 'volunteered',
            'independently', 'took initiative', 'recommended', 'suggested', 'launched',
        ],
        'Problem Solving' => [
            'resolved', 'fixed', 'troubleshoot', 'troubleshot', 'investigated',
            'root cause', 'workaround', 'addressed', 'debugged', 'solution', 'solved',
        ],
        'Teamwork' => [
            'team', 'coordinated', 'collaborated', 'assisted', 'supported', 'helped',
            'together', 'handoff', 'backup', 'mentored',
        ],
        'Client Service' => [
            'client', 'clients', 'customer', 'inquiry', 'request', 'follow-up',
            'followed up', 'satisfaction', 'served', 'accommodated', 'explained',
        ],
        'Timeliness' => [
            'on time', 'deadline', 'ahead of schedule', 'timely', 'within',
            'same day', 'before', 'early', 'schedule', 'due date',
        ],
        'Process Improvement' => [
            'improved', 'streamlined', 'automated', 'reduced', 'standardized',
            'template', 'workflow', 'efficiency', 'eliminated', 'simplified',
        ],
        'Learning and Development' => [
            'learned', 'training', 'studied', 'certification', 'webinar',
            'researched', 'upskilled', 'course', 'mentored', 'studied',
        ],
    ];

    public function analyze(DailyJournal $journal): array
    {
        $achievements = trim((string) $journal->achievements);
        $problems = trim((string) $journal->problems_encountered);
        $solutions = trim((string) $journal->suggested_solutions);
        $allText = trim(implode(' ', array_filter([$achievements, $problems, $solutions])));
        $achievementText = $achievements !== '' ? $achievements : $allText;
        $words = preg_split('/\s+/u', $allText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        $evidenceCount = $journal->evidenceCount();

        if ($achievements === '' || $wordCount < 4) {
            return [
                'score' => min(25, 15 + ($wordCount * 2)) + ($evidenceCount > 0 ? 3 : 0),
                'category' => 'Productivity',
                'explanation' => 'Rule-based assessment: The accomplishment description is empty or too brief to identify meaningful completed work. Add specific tasks, results, or outcomes for a fairer assessment.',
                'tags' => $evidenceCount > 0 ? ['Evidence-backed'] : [],
                'source' => 'rule_based',
            ];
        }

        $lower = Str::lower($allText);
        $achievementLower = Str::lower($achievementText);

        $completed = $this->matchedKeywords($lower, self::COMPLETED_ACTIONS);
        $outcomePhrases = $this->matchedKeywords($lower, self::OUTCOME_PHRASES);
        $superlatives = $this->matchedKeywords($lower, self::SUPERLATIVES);
        $quantified = $this->hasQuantifiedOutcome($allText);
        $categoryScores = $this->categoryScores($achievementLower, $lower);
        $category = $this->pickCategory($categoryScores);
        $categorySignals = $categoryScores[$category] ?? 0;
        $repetition = $this->isRepetitive($journal, $achievementText);

        $score = 35;
        $score += match (true) {
            $wordCount >= 60 => 18,
            $wordCount >= 30 => 14,
            $wordCount >= 15 => 9,
            $wordCount >= 8 => 4,
            default => 0,
        };
        $score += min(count($completed) * 3, 18);
        $score += $quantified ? 8 : 0;
        $score += min(count($outcomePhrases) * 3, 6);
        $score += $evidenceCount > 0 ? 4 : 0;
        $score += min($categorySignals * 3, 15);
        $score += $this->hasClarity($achievementText) ? 2 : 0;

        if (! empty($superlatives) && ! $quantified) {
            $score -= 6;
        }
        if ($this->hasLowVariety($words)) {
            $score -= 5;
        }
        if ($repetition) {
            $score -= 18;
        }

        $score = max(0, min(100, (int) round($score)));

        return [
            'score' => $score,
            'category' => $category,
            'explanation' => $this->explanation(
                $completed,
                $quantified,
                $evidenceCount,
                $category,
                $categorySignals,
                $repetition,
                $superlatives,
                $wordCount
            ),
            'tags' => $this->tags(
                $quantified,
                $evidenceCount,
                $categoryScores,
                $category,
                $repetition,
                $wordCount
            ),
            'source' => 'rule_based',
        ];
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function matchedKeywords(string $text, array $keywords): array
    {
        return array_values(array_filter(
            $keywords,
            fn (string $keyword): bool => $this->containsKeyword($text, $keyword)
        ));
    }

    private function containsKeyword(string $text, string $keyword): bool
    {
        $quoted = preg_quote(Str::lower($keyword), '/');

        return (bool) preg_match('/(?<![a-z0-9])'.$quoted.'(?![a-z0-9])/u', $text);
    }

    private function hasQuantifiedOutcome(string $text): bool
    {
        if (preg_match('/\b\d+(?:[.,]\d+)?\s*(?:%|percent|clients?|forms?|filings?|entries?|invoices?|tasks?|reports?|hours?|days?|items?)?\b/u', $text)) {
            return true;
        }

        return (bool) preg_match('/\b(?:zero|double|twice|triple|half)\b/u', Str::lower($text));
    }

    /**
     * @return array<string, int>
     */
    private function categoryScores(string $achievementText, string $allText): array
    {
        $scores = [];

        foreach (config('achievements.categories', []) as $category) {
            $scores[$category] = 0;
        }

        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            if (! array_key_exists($category, $scores)) {
                continue;
            }

            $achievementHits = $this->matchedKeywords($achievementText, $keywords);
            $allHits = $this->matchedKeywords($allText, $keywords);

            $scores[$category] = (count($achievementHits) * 2) + max(0, count($allHits) - count($achievementHits));
        }

        return $scores;
    }

    /**
     * @param  array<string, int>  $scores
     */
    private function pickCategory(array $scores): string
    {
        $best = 'Productivity';
        $bestScore = 0;

        foreach (config('achievements.categories', []) as $category) {
            $score = $scores[$category] ?? 0;

            if ($score > $bestScore) {
                $best = $category;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function hasClarity(string $text): bool
    {
        $text = trim($text);

        return mb_strlen($text) >= 15 && (bool) preg_match('/[.!?]$/u', $text);
    }

    /**
     * @param  array<int, string>  $words
     */
    private function hasLowVariety(array $words): bool
    {
        $normalized = array_filter(array_map(
            fn (string $word): string => preg_replace('/[^a-z0-9]/i', '', Str::lower($word)) ?? '',
            $words
        ));

        if (count($normalized) < 12) {
            return false;
        }

        return (count(array_unique($normalized)) / count($normalized)) < 0.35;
    }

    private function isRepetitive(DailyJournal $journal, string $currentText): bool
    {
        $current = $this->normalizeContent($currentText);

        if ($current === '' || ! $journal->user_id) {
            return false;
        }

        $date = $journal->required_date?->toDateString() ?? now()->toDateString();

        $recent = DailyJournal::query()
            ->where('user_id', $journal->user_id)
            ->when($journal->exists, fn ($query) => $query->whereKeyNot($journal->getKey()))
            ->whereDate('required_date', '<', $date)
            ->whereNotNull('achievements')
            ->orderByDesc('required_date')
            ->limit(10)
            ->pluck('achievements');

        foreach ($recent as $text) {
            $other = $this->normalizeContent((string) $text);

            if ($other === '') {
                continue;
            }

            if ($other === $current) {
                return true;
            }

            similar_text($current, $other, $percent);

            if ($percent >= 90) {
                return true;
            }
        }

        return false;
    }

    private function normalizeContent(string $text): string
    {
        $text = Str::lower(trim($text));
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array<int, string>  $completed
     * @param  array<int, string>  $superlatives
     */
    private function explanation(
        array $completed,
        bool $quantified,
        int $evidenceCount,
        string $category,
        int $categorySignals,
        bool $repetition,
        array $superlatives,
        int $wordCount
    ): string {
        $parts = [];

        if (! empty($completed)) {
            $parts[] = 'describes completed work ('.implode(', ', array_slice($completed, 0, 3)).')';
        }
        if ($quantified) {
            $parts[] = 'includes a concrete or measurable outcome';
        }
        if ($evidenceCount > 0) {
            $parts[] = 'is supported by attached evidence';
        }
        if ($categorySignals > 0) {
            $parts[] = 'shows '.Str::lower($category).' signals';
        }
        if ($repetition) {
            $parts[] = 'closely repeats recent entries, which lowers the score';
        }
        if (! empty($superlatives) && ! $quantified) {
            $parts[] = 'uses broad claims without concrete outcomes';
        }
        if (empty($parts)) {
            $parts[] = $wordCount < 8
                ? 'is brief and does not clearly describe a completed result'
                : 'reports activity without enough specific outcome detail';
        }

        return 'Rule-based assessment: The entry '.$this->joinParts($parts).'.';
    }

    /**
     * @param  array<string, int>  $categoryScores
     * @return array<int, string>
     */
    private function tags(
        bool $quantified,
        int $evidenceCount,
        array $categoryScores,
        string $category,
        bool $repetition,
        int $wordCount
    ): array {
        $tags = [];

        if ($quantified) {
            $tags[] = 'Results-oriented';
        }
        if (($categoryScores['Initiative'] ?? 0) > 0) {
            $tags[] = 'Initiative';
        }
        if (($categoryScores['Problem Solving'] ?? 0) > 0) {
            $tags[] = 'Problem Solving';
        }
        if (($categoryScores['Teamwork'] ?? 0) > 0) {
            $tags[] = 'Teamwork';
        }
        if (($categoryScores['Client Service'] ?? 0) > 0) {
            $tags[] = 'Client Service';
        }
        if (($categoryScores['Timeliness'] ?? 0) > 0) {
            $tags[] = 'Timeliness';
        }
        if (($categoryScores['Process Improvement'] ?? 0) > 0) {
            $tags[] = 'Process Improvement';
        }
        if (($categoryScores['Learning and Development'] ?? 0) > 0) {
            $tags[] = 'Learning';
        }
        if ($category === 'Quality and Accuracy') {
            $tags[] = 'Quality Focus';
        }
        if ($evidenceCount > 0) {
            $tags[] = 'Evidence-backed';
        }
        if (! $repetition && $wordCount >= 30) {
            $tags[] = 'Consistency';
        }

        return array_slice(array_values(array_unique($tags)), 0, 5);
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function joinParts(array $parts): string
    {
        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }
}
