<?php

namespace App\Support;

use App\Enums\RecordStatus;
use App\Models\College;
use App\Models\CumulativeRecord;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Staff-facing student/record queries. Every query starts from User::studentsVisibleTo(),
 * so counselors only ever see students in their assigned colleges.
 */
final class StudentRecordQuery
{
    public const NOT_STARTED = 'not_started';

    /**
     * Status filter keys => labels, in workflow order.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        self::NOT_STARTED => 'Not started',
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'reviewed' => 'Reviewed',
    ];

    /**
     * SATA filter keys => labels.
     *
     * @var array<string, string>
     */
    public const SATA_FILTERS = [
        'encoded' => 'Encoded',
        'not_encoded' => 'Not encoded',
    ];

    public const INTAKE_FOLLOW_UP = 'follow_up';

    /**
     * Intake interview filter keys => labels (status of the student's latest intake interview).
     *
     * @var array<string, string>
     */
    public const INTAKE_FILTERS = [
        'none' => 'None',
        'draft' => 'Draft',
        'finalized' => 'Finalized',
        self::INTAKE_FOLLOW_UP => 'Needs follow-up',
    ];

    /**
     * @var Collection<int, int>
     */
    private Collection $collegeIds;

    /**
     * @param  array{college_id?: int|null, program_id?: int|null, year_level?: int|null, status?: string|null, sata?: string|null, intake?: string|null, q?: string|null}  $filters
     */
    public function __construct(private User $viewer, private array $filters = [])
    {
        $this->collegeIds = $viewer->accessibleCollegeIds();
    }

    /**
     * Build from query-string filters, silently dropping anything outside the viewer's access.
     *
     * @param  list<string>  $allowedStatuses
     */
    public static function fromRequest(Request $request, ?array $allowedStatuses = null): self
    {
        $viewer = $request->user();
        $query = new self($viewer);

        $collegeId = $request->integer('college_id') ?: null;
        $programId = $request->integer('program_id') ?: null;
        $yearLevel = $request->integer('year_level') ?: null;
        $status = $request->string('status')->toString() ?: null;
        $sata = $request->string('sata')->toString() ?: null;
        $intake = $request->string('intake')->toString() ?: null;

        $query->filters = [
            'college_id' => $query->collegeIds->contains($collegeId) ? $collegeId : null,
            'program_id' => $programId && $query->programs()->contains('id', $programId) ? $programId : null,
            'year_level' => array_key_exists((int) $yearLevel, Form5::YEAR_LEVELS) ? $yearLevel : null,
            'status' => in_array($status, $allowedStatuses ?? array_keys(self::STATUSES), true) ? $status : null,
            'sata' => array_key_exists((string) $sata, self::SATA_FILTERS) ? $sata : null,
            'intake' => array_key_exists((string) $intake, self::INTAKE_FILTERS) ? $intake : null,
            'q' => mb_substr(trim($request->string('q')->toString()), 0, 100) ?: null,
        ];

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Colleges the viewer may filter by.
     *
     * @return Collection<int, College>
     */
    public function colleges(): Collection
    {
        return College::whereIn('id', $this->collegeIds)->orderBy('name')->get();
    }

    /**
     * Programs in the viewer's colleges (narrowed to the selected college, if any).
     *
     * @return Collection<int, Program>
     */
    public function programs(): Collection
    {
        return Program::whereIn('college_id', $this->collegeIds)
            ->when($this->filters['college_id'] ?? null, fn ($query, $collegeId) => $query->where('college_id', $collegeId))
            ->orderBy('code')
            ->get();
    }

    /**
     * Visible students with all filters applied (status uses each student's latest record).
     *
     * @return Builder<User>
     */
    public function students(): Builder
    {
        return $this->baseStudents()
            ->when($this->filters['status'] ?? null, fn (Builder $query, string $status) => $this->applyStatus($query, $status))
            ->when($this->filters['sata'] ?? null, fn (Builder $query, string $sata) => $sata === 'encoded'
                ? $query->whereHas('entranceTestResults')
                : $query->whereDoesntHave('entranceTestResults'))
            ->when($this->filters['intake'] ?? null, fn (Builder $query, string $intake) => $this->applyIntake($query, $intake));
    }

    /**
     * Number of visible students (status and SATA filters ignored) with no SATA result yet.
     */
    public function withoutSataCount(): int
    {
        return $this->baseStudents()->whereDoesntHave('entranceTestResults')->count();
    }

    /**
     * Students who have not submitted yet: no record, or only a draft.
     *
     * @return Builder<User>
     */
    public function notSubmitted(): Builder
    {
        return $this->students()->where(function (Builder $query): void {
            $query->whereDoesntHave('cumulativeRecords')
                ->orWhereHas('latestCumulativeRecord', fn (Builder $record) => $record->where('status', RecordStatus::Draft));
        });
    }

    /**
     * Counts of students per college, year level and record status (status filter ignored).
     *
     * @return Collection<int, object{college_id: int, year_level: int|null, record_status: string, total: int}>
     */
    public function statusCounts(): Collection
    {
        $latestRecordIds = CumulativeRecord::query()->selectRaw('max(id)')->groupBy('user_id');
        $statusExpression = "coalesce(latest_records.status, '".self::NOT_STARTED."')";

        return $this->baseStudents()
            ->toBase()
            ->join('programs as student_programs', 'student_programs.id', '=', 'users.program_id')
            ->leftJoin('cumulative_records as latest_records', function ($join) use ($latestRecordIds): void {
                $join->on('latest_records.user_id', '=', 'users.id')->whereIn('latest_records.id', $latestRecordIds);
            })
            ->selectRaw("student_programs.college_id, users.year_level, {$statusExpression} as record_status, count(*) as total")
            ->groupBy('student_programs.college_id', 'users.year_level')
            ->groupByRaw($statusExpression)
            ->get()
            ->map(function (object $row): object {
                $row->college_id = (int) $row->college_id;
                $row->year_level = $row->year_level === null ? null : (int) $row->year_level;
                $row->total = (int) $row->total;

                return $row;
            });
    }

    /**
     * Headline numbers for staff dashboards.
     *
     * @return array{total: int, submitted: int, not_submitted: int, waiting_for_review: int}
     */
    public function summary(): array
    {
        $byStatus = $this->statusCounts()->groupBy('record_status')->map->sum('total');

        return [
            'total' => (int) $byStatus->sum(),
            'submitted' => (int) (($byStatus['submitted'] ?? 0) + ($byStatus['reviewed'] ?? 0)),
            'not_submitted' => (int) (($byStatus[self::NOT_STARTED] ?? 0) + ($byStatus['draft'] ?? 0)),
            'waiting_for_review' => (int) ($byStatus['submitted'] ?? 0),
        ];
    }

    /**
     * @return Builder<User>
     */
    private function baseStudents(): Builder
    {
        $filters = $this->filters;

        return User::query()
            ->studentsVisibleTo($this->viewer)
            ->when($filters['college_id'] ?? null, fn (Builder $query, int $collegeId) => $query->whereHas('program', fn (Builder $program) => $program->where('college_id', $collegeId)))
            ->when($filters['program_id'] ?? null, fn (Builder $query, int $programId) => $query->where('users.program_id', $programId))
            ->when($filters['year_level'] ?? null, fn (Builder $query, int $yearLevel) => $query->where('users.year_level', $yearLevel))
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('users.name', 'like', $like)
                        ->orWhere('users.last_name', 'like', $like)
                        ->orWhere('users.first_name', 'like', $like)
                        ->orWhere('users.student_number', 'like', $like);
                });
            });
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyIntake(Builder $query, string $intake): void
    {
        match ($intake) {
            'none' => $query->whereDoesntHave('intakeInterviews'),
            self::INTAKE_FOLLOW_UP => $query->whereHas('intakeInterviews', fn (Builder $interviews) => $interviews->needingFollowUp()),
            default => $query->whereHas('latestIntakeInterview', fn (Builder $interview) => $interview->where('status', $intake)),
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyStatus(Builder $query, string $status): void
    {
        if ($status === self::NOT_STARTED) {
            $query->whereDoesntHave('cumulativeRecords');

            return;
        }

        $query->whereHas('latestCumulativeRecord', fn (Builder $record) => $record->where('status', $status));
    }
}
