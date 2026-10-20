<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * `role`, `is_active` and `must_change_password` are intentionally NOT fillable: they are only
 * ever set explicitly in code (registration hardcodes Student; the director's screens set staff accounts).
 */
#[Fillable(['name', 'last_name', 'first_name', 'middle_name', 'email', 'password', 'student_number', 'program_id', 'year_level'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'year_level' => 'integer',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Keep the display `name` in sync with the separate name parts.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->first_name || $user->last_name) {
                $user->name = collect([$user->first_name, $user->middle_name, $user->last_name])
                    ->filter()
                    ->implode(' ');
            }
        });
    }

    /**
     * The program a student is enrolled in.
     *
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Colleges a counselor is assigned to.
     *
     * @return BelongsToMany<College, $this>
     */
    public function colleges(): BelongsToMany
    {
        return $this->belongsToMany(College::class, 'college_counselor')->withTimestamps();
    }

    /**
     * Form 5 records filled out by this student (one per school year + semester).
     *
     * @return HasMany<CumulativeRecord, $this>
     */
    public function cumulativeRecords(): HasMany
    {
        return $this->hasMany(CumulativeRecord::class);
    }

    /**
     * The student's most recent Form 5 record.
     *
     * @return HasOne<CumulativeRecord, $this>
     */
    public function latestCumulativeRecord(): HasOne
    {
        return $this->hasOne(CumulativeRecord::class)->latestOfMany();
    }

    /**
     * SATA entrance test results for this student (retakes included).
     *
     * @return HasMany<EntranceTestResult, $this>
     */
    public function entranceTestResults(): HasMany
    {
        return $this->hasMany(EntranceTestResult::class, 'student_id');
    }

    /**
     * Current SATA result: the latest by exam date (newest entry wins on the same date).
     *
     * @return HasOne<EntranceTestResult, $this>
     */
    public function latestEntranceTestResult(): HasOne
    {
        return $this->hasOne(EntranceTestResult::class, 'student_id')->ofMany(['exam_date' => 'max', 'id' => 'max']);
    }

    /**
     * HDC Form 8 intake interviews for this student (staff only; never shown to the student).
     *
     * @return HasMany<IntakeInterview, $this>
     */
    public function intakeInterviews(): HasMany
    {
        return $this->hasMany(IntakeInterview::class, 'student_id');
    }

    /**
     * The student's most recent intake interview (by creation).
     *
     * @return HasOne<IntakeInterview, $this>
     */
    public function latestIntakeInterview(): HasOne
    {
        return $this->hasOne(IntakeInterview::class, 'student_id')->latestOfMany();
    }

    /**
     * Load what staff lists show about intake interviews: the latest one's status and a
     * `needs_follow_up` flag. Never loads concern answers or notes into a list.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithIntakeSummary(Builder $query): void
    {
        $query->with('latestIntakeInterview:intake_interviews.id,intake_interviews.student_id,intake_interviews.status')
            ->withExists(['intakeInterviews as needs_follow_up' => fn (Builder $interviews) => $interviews->needingFollowUp()]);
    }

    /**
     * Whether a staff user may see this student's records (student is in one of their colleges).
     */
    public function isVisibleTo(User $viewer): bool
    {
        $collegeId = $this->program?->college_id;

        return $this->isStudent()
            && ! $viewer->isStudent()
            && $collegeId !== null
            && $viewer->accessibleCollegeIds()->contains($collegeId);
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }

    public function isCounselor(): bool
    {
        return $this->role === Role::Counselor;
    }

    public function isDirector(): bool
    {
        return $this->role === Role::Director;
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === ($role instanceof Role ? $role : Role::tryFrom($role))) {
                return true;
            }
        }

        return false;
    }

    /**
     * IDs of the colleges whose student records this user may access.
     * Director: every college. Counselor: assigned colleges only. Student: none.
     *
     * @return Collection<int, int>
     */
    public function accessibleCollegeIds(): Collection
    {
        return match ($this->role) {
            Role::Director => College::query()->pluck('id'),
            Role::Counselor => $this->colleges()->pluck('colleges.id'),
            default => collect(),
        };
    }

    /**
     * Name of the route for this user's role dashboard.
     */
    public function dashboardRoute(): string
    {
        return $this->role->dashboardRoute();
    }

    /**
     * Staff who should hear about a student in the given college: every director,
     * plus counselors assigned to that college.
     *
     * @param  Builder<User>  $query
     */
    public function scopeStaffResponsibleFor(Builder $query, ?int $collegeId): void
    {
        $query->where('is_active', true)->where(function (Builder $query) use ($collegeId): void {
            $query->where('role', Role::Director);

            if ($collegeId !== null) {
                $query->orWhere(fn (Builder $counselors) => $counselors
                    ->where('role', Role::Counselor)
                    ->whereHas('colleges', fn (Builder $colleges) => $colleges->where('colleges.id', $collegeId)));
            }
        });
    }

    /**
     * Limit a query to students whose program belongs to a college the viewer may access.
     * Use this on every staff-facing student query.
     *
     * @param  Builder<User>  $query
     */
    public function scopeStudentsVisibleTo(Builder $query, User $viewer): void
    {
        $query->where('users.role', Role::Student)
            ->whereHas('program', fn (Builder $program) => $program->whereIn('college_id', $viewer->accessibleCollegeIds()));
    }
}
