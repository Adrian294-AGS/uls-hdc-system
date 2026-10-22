<?php

namespace App\Support\Reports;

use App\Models\College;
use App\Models\Program;
use App\Models\User;
use App\Support\Form5;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filters shared by every report. Anything outside the viewer's accessible colleges is
 * silently dropped, so a counselor can never widen a report beyond their assigned colleges.
 */
final class ReportFilters
{
    /**
     * @param  Collection<int, int>  $collegeIds  Colleges included in the report (always within the viewer's access).
     */
    private function __construct(
        public readonly User $viewer,
        public readonly Collection $collegeIds,
        public readonly ?string $schoolYear = null,
        public readonly ?string $semester = null,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
        public readonly ?int $collegeId = null,
        public readonly ?int $programId = null,
        public readonly ?int $yearLevel = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $viewer = $request->user();
        $accessible = $viewer->accessibleCollegeIds()->map(fn ($id) => (int) $id)->values();

        $collegeId = $request->integer('college_id') ?: null;
        $collegeId = $accessible->contains($collegeId) ? $collegeId : null;

        $programId = $request->integer('program_id') ?: null;
        $programId = $programId && Program::whereKey($programId)
            ->whereIn('college_id', $collegeId ? [$collegeId] : $accessible)
            ->exists() ? $programId : null;

        $yearLevel = $request->integer('year_level') ?: null;
        $yearLevel = array_key_exists((int) $yearLevel, Form5::YEAR_LEVELS) ? $yearLevel : null;

        $schoolYear = $request->string('school_year')->toString();
        $schoolYear = in_array($schoolYear, self::schoolYearOptions(), true) ? $schoolYear : null;

        $semester = $request->string('semester')->toString();
        $semester = $schoolYear && array_key_exists($semester, Form5::SEMESTERS) ? $semester : null;

        $from = self::parseDate($request->string('from')->toString());
        $to = self::parseDate($request->string('to')->toString());
        if ($from && $to && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            viewer: $viewer,
            collegeIds: $collegeId ? collect([$collegeId]) : $accessible,
            schoolYear: $schoolYear,
            semester: $semester,
            from: $from?->startOfDay(),
            to: $to?->endOfDay(),
            collegeId: $collegeId,
            programId: $programId,
            yearLevel: $yearLevel,
        );
    }

    /**
     * School years offered in the filter: the current one and the five before it.
     *
     * @return list<string>
     */
    public static function schoolYearOptions(): array
    {
        $current = (int) explode('-', Form5::schoolYearFor(now()))[0];

        return array_map(fn (int $start) => $start.'-'.($start + 1), range($current, $current - 5));
    }

    /**
     * The date window for date-based data (intake interview date, SATA exam date): the
     * intersection of the school year/semester and the date range.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public function window(): array
    {
        [$start, $end] = [$this->from, $this->to];

        if ($this->schoolYear) {
            [$termStart, $termEnd] = $this->termBounds();
            $start = $start ? $start->max($termStart) : $termStart;
            $end = $end ? $end->min($termEnd) : $termEnd;
        }

        return [$start, $end];
    }

    /**
     * Limit a date column to the window. An empty window (start after end) matches nothing.
     */
    public function applyWindow(QueryBuilder $query, string $column): void
    {
        [$start, $end] = $this->window();

        if ($start && $end && $start->greaterThan($end)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->when($start, fn (QueryBuilder $query) => $query->where($column, '>=', $start))
            ->when($end, fn (QueryBuilder $query) => $query->where($column, '<=', $end));
    }

    /**
     * Limit a query that joins `users` (students) and `programs` (their program) to the
     * students this report covers.
     */
    public function applyToStudents(QueryBuilder $query, string $users = 'users', string $programs = 'programs'): void
    {
        $query->where("{$users}.role", 'student')
            ->whereIn("{$programs}.college_id", $this->collegeIds->all())
            ->when($this->programId, fn (QueryBuilder $query, int $programId) => $query->where("{$users}.program_id", $programId))
            ->when($this->yearLevel, fn (QueryBuilder $query, int $yearLevel) => $query->where("{$users}.year_level", $yearLevel));
    }

    /**
     * Colleges covered by this report, in name order.
     *
     * @return Collection<int, College>
     */
    public function colleges(): Collection
    {
        return College::whereIn('id', $this->collegeIds)->orderBy('code')->get();
    }

    /**
     * Colleges the viewer may pick in the filter.
     *
     * @return Collection<int, College>
     */
    public function collegeOptions(): Collection
    {
        return College::whereIn('id', $this->viewer->accessibleCollegeIds())->orderBy('code')->get();
    }

    /**
     * Programs the viewer may pick (narrowed to the chosen college).
     *
     * @return Collection<int, Program>
     */
    public function programOptions(): Collection
    {
        return Program::whereIn('college_id', $this->collegeIds)->orderBy('code')->get();
    }

    /**
     * Query-string values, for links that keep the same filters (CSV, print).
     *
     * @return array<string, string|int>
     */
    public function query(): array
    {
        return array_filter([
            'school_year' => $this->schoolYear,
            'semester' => $this->semester,
            'from' => $this->from?->format('Y-m-d'),
            'to' => $this->to?->format('Y-m-d'),
            'college_id' => $this->collegeId,
            'program_id' => $this->programId,
            'year_level' => $this->yearLevel,
        ]);
    }

    /**
     * Human-readable filters, for exports and the access log.
     *
     * @return array<string, string>
     */
    public function describe(): array
    {
        $college = $this->collegeId ? College::find($this->collegeId) : null;
        $program = $this->programId ? Program::find($this->programId) : null;

        return [
            'School year' => $this->schoolYear ?? 'All',
            'Semester' => $this->semester ? Form5::SEMESTERS[$this->semester] : 'All',
            'Date range' => match (true) {
                $this->from && $this->to => $this->from->format('M j, Y').' to '.$this->to->format('M j, Y'),
                (bool) $this->from => 'From '.$this->from->format('M j, Y'),
                (bool) $this->to => 'Until '.$this->to->format('M j, Y'),
                default => 'Any',
            },
            'College' => $college ? "{$college->code} – {$college->name}" : ($this->viewer->isDirector() ? 'All colleges' : 'All my assigned colleges'),
            'Program' => $program?->code ?? 'All',
            'Year level' => $this->yearLevel ? Form5::YEAR_LEVELS[$this->yearLevel] : 'All',
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function termBounds(): array
    {
        [$firstYear, $secondYear] = array_map('intval', explode('-', $this->schoolYear));
        $dateIn = function (string $monthDay) use ($firstYear, $secondYear): CarbonImmutable {
            $month = (int) substr($monthDay, 0, 2);

            return CarbonImmutable::parse(($month >= 6 ? $firstYear : $secondYear).'-'.$monthDay);
        };

        if (! $this->semester) {
            return [CarbonImmutable::create($firstYear, 6, 1)->startOfDay(), CarbonImmutable::create($secondYear, 5, 31)->endOfDay()];
        }

        $bounds = config("hdc.semesters.{$this->semester}");

        return [$dateIn($bounds['start'])->startOfDay(), $dateIn($bounds['end'])->endOfDay()];
    }

    private static function parseDate(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
