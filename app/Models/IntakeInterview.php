<?php

namespace App\Models;

use App\Enums\IntakeStatus;
use App\Support\Form8;
use Database\Factories\IntakeInterviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HDC Form 8: Intake Interview (highly sensitive). `student_id`, `counselor_id`, `status` and
 * `finalized_at` are set explicitly in code. Concern answers and notes are hidden from
 * serialization so they can never leak into JSON, lists or exports by accident.
 */
#[Fillable([
    'interview_at', 'date_of_entry', 'academic_status',
    'name', 'nickname', 'course', 'home_address', 'age', 'sex', 'birth_date', 'place_of_birth',
    'civil_status', 'spouse_name', 'number_of_children', 'citizenship', 'citizenship_other', 'religion',
    'contact_number', 'boarding_address', 'emergency_contact_name', 'emergency_contact_relationship',
    'emergency_contact_address', 'emergency_contact_number',
    'eating_disorder', 'depression', 'discrimination', 'pregnancy', 'abortion', 'anxiety_stress', 'financial',
    'alcohol_use', 'attempted_suicide', 'substance_abuse', 'body_dysmorphic', 'miscarriage', 'self_esteem',
    'sexual_assault', 'acculturation', 'loss_of_significant_others', 'concerns_specify',
    'family', 'same_sex', 'peers', 'significant_others', 'relationship_other',
    'praying', 'laughing', 'drinking', 'malling', 'listening_to_music', 'crying', 'going_out_with_peers',
    'partying', 'shopping', 'coping_other',
    'client_description', 'presenting_concerns', 'conceptualization', 'recommendations', 'relationship_during_intake',
    'interviewee_acknowledged',
])]
#[Hidden([
    'eating_disorder', 'depression', 'discrimination', 'pregnancy', 'abortion', 'anxiety_stress', 'financial',
    'alcohol_use', 'attempted_suicide', 'substance_abuse', 'body_dysmorphic', 'miscarriage', 'self_esteem',
    'sexual_assault', 'acculturation', 'loss_of_significant_others', 'concerns_specify',
    'family', 'same_sex', 'peers', 'significant_others', 'relationship_other',
    'praying', 'laughing', 'drinking', 'malling', 'listening_to_music', 'crying', 'going_out_with_peers',
    'partying', 'shopping', 'coping_other',
    'client_description', 'presenting_concerns', 'conceptualization', 'recommendations', 'relationship_during_intake',
])]
class IntakeInterview extends Model
{
    /** @use HasFactory<IntakeInterviewFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'interviewee_acknowledged' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'status' => IntakeStatus::class,
            'interview_at' => 'datetime',
            'date_of_entry' => 'date',
            'birth_date' => 'date',
            'age' => 'integer',
            'number_of_children' => 'integer',
            'relationship_during_intake' => 'array',
            'interviewee_acknowledged' => 'boolean',
            'finalized_at' => 'datetime',
        ];

        foreach (Form8::yesNoFields() as $field) {
            $casts[$field] = 'boolean';
        }

        foreach ([...Form8::SPECIFY_FIELDS, ...array_keys(Form8::NOTES)] as $field) {
            $casts[$field] = 'encrypted';
        }

        return $casts;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The interviewer: the staff member who created the interview.
     *
     * @return BelongsTo<User, $this>
     */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function isFinalized(): bool
    {
        return $this->status === IntakeStatus::Finalized;
    }

    /**
     * Whether any concern listed in config('hdc.follow_up_concerns') was answered Yes.
     */
    public function needsFollowUp(): bool
    {
        foreach (Form8::followUpConcerns() as $concern) {
            if ($this->{$concern} === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Interviews with at least one follow-up concern answered Yes.
     *
     * @param  Builder<IntakeInterview>  $query
     */
    public function scopeNeedingFollowUp(Builder $query): void
    {
        $concerns = Form8::followUpConcerns();

        if ($concerns === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($concerns): void {
            foreach ($concerns as $concern) {
                $query->orWhere($concern, true);
            }
        });
    }
}
