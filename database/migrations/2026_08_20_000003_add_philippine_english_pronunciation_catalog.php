<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->rules() as $rule) {
            $normalized = mb_strtolower($rule['match'], 'UTF-8');
            $existing = DB::table('pronunciation_rules')
                ->whereNull('ceremony_id')
                ->where('section', $rule['section'])
                ->where('normalized_match', $normalized)
                ->first();

            // A matching rule may be a deliberate local customization. Never replace it.
            if ($existing !== null) {
                continue;
            }

            $ruleId = DB::table('pronunciation_rules')->insertGetId([
                'ceremony_id' => null,
                'section' => $rule['section'],
                'match_text' => $rule['match'],
                'normalized_match' => $normalized,
                'is_enabled' => true,
                'is_builtin' => true,
                'revision' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['azure', 'kokoro'] as $provider) {
                DB::table('pronunciation_rule_variants')->insert([
                    'pronunciation_rule_id' => $ruleId,
                    'provider' => $provider,
                    'locale' => '',
                    'voice' => '',
                    'replacement_text' => $rule['replacement'],
                    'is_enabled' => true,
                    'revision' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->rules() as $rule) {
            DB::table('pronunciation_rules')
                ->whereNull('ceremony_id')
                ->where('section', $rule['section'])
                ->where('normalized_match', mb_strtolower($rule['match'], 'UTF-8'))
                ->where('is_builtin', true)
                ->delete();
        }
    }

    /** @return list<array{section: string, match: string, replacement: string}> */
    private function rules(): array
    {
        return [
            ...$this->entries('name', [
                'Prof.' => 'Professor', 'Atty.' => 'Attorney', 'Engr.' => 'Engineer',
                'Arch.' => 'Architect', 'Rev.' => 'Reverend', 'Fr.' => 'Father',
                'Hon.' => 'Honorable', 'Mr.' => 'Mister', 'Mrs.' => 'Missus', 'Ms.' => 'Miz',
                'Jr.' => 'Junior', 'Sr.' => 'Senior', 'II' => 'the Second',
                'III' => 'the Third', 'IV' => 'the Fourth',
            ]),
            ...$this->entries('honors', [
                'Summa Cum Laude' => 'soo-mah koom low-day',
                'Magna Cum Laude' => 'mag-nah koom low-day',
                'Cum Laude' => 'koom low-day',
                'Honoris Causa' => 'hon-or-is cow-sah',
            ]),
            ...$this->entries('any', [
                'GWA' => 'G W A', 'CGPA' => 'C G P A', 'CHED' => 'ched',
                'DepEd' => 'dep ed', 'TESDA' => 'tes dah', 'CPA' => 'C P A',
                'LPT' => 'L P T', 'RN' => 'R N', 'JD' => 'Juris Doctor',
                'J.D.' => 'Juris Doctor', 'LLB' => 'Bachelor of Laws',
                'LL.B.' => 'Bachelor of Laws', 'PhD' => 'Doctor of Philosophy',
                'EdD' => 'Doctor of Education', 'Ed.D.' => 'Doctor of Education',
                'DBA' => 'Doctor of Business Administration',
                'D.B.A.' => 'Doctor of Business Administration',
                'MBA' => 'Master of Business Administration',
                'M.B.A.' => 'Master of Business Administration',
                'BSc' => 'Bachelor of Science', 'B.B.A.' => 'Bachelor of Business Administration',
                'BBA' => 'Bachelor of Business Administration',
                'BSBA' => 'Bachelor of Science in Business Administration',
                'BSCS' => 'Bachelor of Science in Computer Science',
                'BSIT' => 'Bachelor of Science in Information Technology',
                'BSN' => 'Bachelor of Science in Nursing',
                'BSEd' => 'Bachelor of Secondary Education',
                'BEEd' => 'Bachelor of Elementary Education',
            ]),
            ...$this->entries('program', [
                'MA' => 'Master of Arts', 'M.A.' => 'Master of Arts',
                'MS' => 'Master of Science', 'M.S.' => 'Master of Science',
                'BA' => 'Bachelor of Arts', 'B.A.' => 'Bachelor of Arts',
                'BS' => 'Bachelor of Science', 'B.S.' => 'Bachelor of Science',
            ]),
        ];
    }

    /** @param array<string, string> $entries
     * @return list<array{section: string, match: string, replacement: string}>
     */
    private function entries(string $section, array $entries): array
    {
        return array_map(
            static fn (string $replacement, string $match): array => [
                'section' => $section,
                'match' => $match,
                'replacement' => $replacement,
            ],
            $entries,
            array_keys($entries),
        );
    }
};
