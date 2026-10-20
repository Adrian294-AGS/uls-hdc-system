<?php

namespace Tests\Feature;

use App\Enums\IntakeStatus;
use App\Models\AccessLog;
use App\Models\College;
use App\Models\CumulativeRecord;
use App\Models\IntakeInterview;
use App\Models\Program;
use App\Models\User;
use App\Notifications\IntakeNeedsFollowUp;
use App\Support\Form8;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntakeInterviewTest extends TestCase
{
    use RefreshDatabase;

    private User $counselorA;

    private User $director;

    private User $studentA;

    private User $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        $collegeA = College::factory()->create(['code' => 'CIT']);
        $collegeB = College::factory()->create(['code' => 'CON']);

        $this->counselorA = User::factory()->counselor([$collegeA])->create();
        $this->director = User::factory()->director()->create();
        $this->studentA = User::factory()->student(Program::factory()->for($collegeA)->create(['code' => 'BSIT']))->create(['last_name' => 'Aquino']);
        $this->studentB = User::factory()->student(Program::factory()->for($collegeB)->create())->create(['last_name' => 'Bautista']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $payload = [
            'action' => 'draft',
            'name' => 'Ana Aquino',
            'client_description' => 'Neatly dressed, soft-spoken.',
            'presenting_concerns' => 'Difficulty sleeping before exams.',
            'conceptualization' => 'Mild situational stress.',
            'recommendations' => 'Follow-up session in two weeks.',
            'relationship_during_intake' => ['cooperative', 'shy'],
        ];

        foreach (Form8::yesNoFields() as $field) {
            $payload[$field] = '0';
        }

        return array_merge($payload, $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function finalizePayload(array $overrides = []): array
    {
        return $this->payload(['action' => 'finalize', 'interview_at' => '2026-10-19T14:30', 'interviewee_acknowledged' => '1', ...$overrides]);
    }

    public function test_counselor_can_save_a_draft_with_personal_data_prefilled_from_form_5(): void
    {
        CumulativeRecord::factory()->completed()->for($this->studentA, 'student')->create([
            'first_name' => 'Ana', 'last_name' => 'Aquino', 'religion' => 'Roman Catholic', 'contact_number' => '09171234567',
        ]);

        $this->actingAs($this->counselorA)
            ->get(route('staff.intake.create', $this->studentA))
            ->assertOk()
            ->assertSee('INTAKE INTERVIEW FORM')
            ->assertSee('value="Roman Catholic"', false)
            ->assertSee('value="09171234567"', false)
            ->assertSee('value="BSIT"', false)
            ->assertSee($this->counselorA->name);

        $this->actingAs($this->counselorA)
            ->post(route('staff.intake.store', $this->studentA), $this->payload(['depression' => '1']))
            ->assertSessionHasNoErrors();

        $interview = IntakeInterview::sole();
        $this->assertSame(IntakeStatus::Draft, $interview->status);
        $this->assertSame($this->studentA->id, $interview->student_id);
        $this->assertSame($this->counselorA->id, $interview->counselor_id);
        $this->assertTrue($interview->depression);
        $this->assertFalse($interview->eating_disorder);
        $this->assertSame(['cooperative', 'shy'], $interview->relationship_during_intake);
        $this->assertNull($interview->finalized_at);
    }

    public function test_counselor_gets_403_for_students_outside_assigned_colleges(): void
    {
        $interview = IntakeInterview::factory()->for($this->studentB, 'student')->create();

        $this->actingAs($this->counselorA)->get(route('staff.intake.create', $this->studentB))->assertForbidden();
        $this->actingAs($this->counselorA)->post(route('staff.intake.store', $this->studentB), $this->payload())->assertForbidden();
        $this->actingAs($this->counselorA)->get(route('staff.intake.show', $interview))->assertForbidden();
        $this->actingAs($this->counselorA)->get(route('staff.intake.edit', $interview))->assertForbidden();
        $this->actingAs($this->counselorA)->put(route('staff.intake.update', $interview), $this->payload(['name' => 'Changed']))->assertForbidden();

        $this->assertSame(1, IntakeInterview::count());
        $this->assertNotSame('Changed', $interview->fresh()->name);
        $this->assertSame(0, AccessLog::count());
    }

    public function test_students_get_403_on_all_intake_routes_and_never_see_a_link(): void
    {
        $interview = IntakeInterview::factory()->for($this->studentA, 'student')->create();

        $this->actingAs($this->studentA)->get(route('staff.intake.create', $this->studentA))->assertForbidden();
        $this->actingAs($this->studentA)->post(route('staff.intake.store', $this->studentA), $this->payload())->assertForbidden();
        $this->actingAs($this->studentA)->get(route('staff.intake.show', $interview))->assertForbidden();
        $this->actingAs($this->studentA)->get(route('staff.intake.edit', $interview))->assertForbidden();
        $this->actingAs($this->studentA)->put(route('staff.intake.update', $interview), $this->payload())->assertForbidden();

        $this->actingAs($this->studentA)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('/staff/intake', false)
            ->assertDontSee('Intake', false);
        $this->assertSame(1, IntakeInterview::count());
    }

    public function test_finalize_requires_interview_date_and_acknowledgment(): void
    {
        $this->actingAs($this->counselorA)
            ->post(route('staff.intake.store', $this->studentA), $this->payload(['action' => 'finalize']))
            ->assertSessionHasErrors(['interview_at', 'interviewee_acknowledged']);
        $this->assertSame(0, IntakeInterview::count());

        // A draft needs neither.
        $this->actingAs($this->counselorA)
            ->post(route('staff.intake.store', $this->studentA), $this->payload())
            ->assertSessionHasNoErrors();
        $interview = IntakeInterview::sole();

        $this->actingAs($this->counselorA)
            ->put(route('staff.intake.update', $interview), $this->finalizePayload(['interviewee_acknowledged' => null]))
            ->assertSessionHasErrors('interviewee_acknowledged');
        $this->assertSame(IntakeStatus::Draft, $interview->fresh()->status);

        $this->actingAs($this->counselorA)
            ->put(route('staff.intake.update', $interview), $this->finalizePayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.intake.show', $interview));

        $interview->refresh();
        $this->assertSame(IntakeStatus::Finalized, $interview->status);
        $this->assertNotNull($interview->finalized_at);
        $this->assertSame('2026-10-19 14:30', $interview->interview_at->format('Y-m-d H:i'));
        $this->assertTrue($interview->interviewee_acknowledged);
    }

    public function test_after_finalizing_only_the_director_can_edit_and_each_edit_is_logged(): void
    {
        $interview = IntakeInterview::factory()->finalized()->for($this->studentA, 'student')->for($this->counselorA, 'counselor')->create();

        $this->actingAs($this->counselorA)->get(route('staff.intake.edit', $interview))->assertForbidden();
        $this->actingAs($this->counselorA)->put(route('staff.intake.update', $interview), $this->finalizePayload(['name' => 'Changed']))->assertForbidden();
        $this->actingAs($this->counselorA)->get(route('staff.intake.show', $interview))->assertOk()->assertDontSee(route('staff.intake.edit', $interview));
        $this->assertNotSame('Changed', $interview->fresh()->name);

        $this->actingAs($this->director)->get(route('staff.intake.edit', $interview))->assertOk()->assertSee('Save changes');
        $this->actingAs($this->director)
            ->put(route('staff.intake.update', $interview), $this->finalizePayload(['name' => 'Corrected Name', 'action' => 'draft']))
            ->assertSessionHasNoErrors();

        $interview->refresh();
        $this->assertSame('Corrected Name', $interview->name);
        $this->assertSame(IntakeStatus::Finalized, $interview->status, 'A finalized interview never goes back to draft.');
        $this->assertSame($this->counselorA->id, $interview->counselor_id, 'The interviewer does not change when the director edits.');

        $this->assertSame(1, AccessLog::where('action', AccessLog::ACTION_EDIT_INTAKE)
            ->where('user_id', $this->director->id)
            ->where('intake_interview_id', $interview->id)
            ->count());
    }

    public function test_draft_edits_are_not_logged_as_edit_intake(): void
    {
        $interview = IntakeInterview::factory()->for($this->studentA, 'student')->create();

        $this->actingAs($this->counselorA)->put(route('staff.intake.update', $interview), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($this->counselorA)->put(route('staff.intake.update', $interview), $this->finalizePayload())->assertSessionHasNoErrors();

        $this->assertSame(0, AccessLog::where('action', AccessLog::ACTION_EDIT_INTAKE)->count());
    }

    public function test_follow_up_concern_notifies_the_director_without_sensitive_text(): void
    {
        $otherDirector = User::factory()->director()->create();

        $this->actingAs($this->counselorA)
            ->post(route('staff.intake.store', $this->studentA), $this->payload(['attempted_suicide' => '1']))
            ->assertSessionHasNoErrors();

        $interview = IntakeInterview::sole();
        $this->assertTrue($interview->needsFollowUp());

        foreach ([$this->director, $otherDirector] as $director) {
            $notification = $director->notifications()->sole();
            $this->assertSame(IntakeNeedsFollowUp::class, $notification->type);
            $this->assertSame(route('staff.intake.show', $interview, absolute: false), $notification->data['url']);
            $this->assertStringContainsString($this->studentA->name, $notification->data['message']);

            $stored = mb_strtolower(json_encode($notification->data));
            foreach ([...array_keys(Form8::CONCERNS), ...array_map('mb_strtolower', Form8::CONCERNS), 'suicide', 'difficulty sleeping', 'situational stress'] as $sensitive) {
                $this->assertStringNotContainsString(mb_strtolower($sensitive), $stored);
            }
        }
        $this->assertSame(0, $this->counselorA->notifications()->count());

        // Saving again while still flagged does not notify again.
        $this->actingAs($this->counselorA)
            ->put(route('staff.intake.update', $interview), $this->payload(['attempted_suicide' => '1', 'sexual_assault' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->director->notifications()->count());
    }

    public function test_no_notification_when_no_follow_up_concern_is_yes(): void
    {
        $this->actingAs($this->counselorA)
            ->post(route('staff.intake.store', $this->studentA), $this->payload(['depression' => '1', 'anxiety_stress' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertFalse(IntakeInterview::sole()->needsFollowUp());
        $this->assertSame(0, $this->director->notifications()->count());
    }

    public function test_follow_up_concerns_come_from_config(): void
    {
        config(['hdc.follow_up_concerns' => ['depression', 'not_a_real_column']]);

        $this->assertSame(['depression'], Form8::followUpConcerns());
        $this->assertTrue(IntakeInterview::factory()->make(['depression' => true])->needsFollowUp());
        $this->assertFalse(IntakeInterview::factory()->make(['attempted_suicide' => true])->needsFollowUp());
    }

    public function test_counselor_notes_and_specify_fields_are_stored_encrypted(): void
    {
        $this->actingAs($this->counselorA)
            ->post(route('staff.intake.store', $this->studentA), $this->payload(['concerns_specify' => 'Grandmother passed away']))
            ->assertSessionHasNoErrors();

        $raw = DB::table('intake_interviews')->sole();
        $expected = [
            'client_description' => 'Neatly dressed, soft-spoken.',
            'presenting_concerns' => 'Difficulty sleeping before exams.',
            'conceptualization' => 'Mild situational stress.',
            'recommendations' => 'Follow-up session in two weeks.',
            'concerns_specify' => 'Grandmother passed away',
        ];

        foreach ($expected as $column => $plain) {
            $this->assertStringNotContainsString($plain, $raw->{$column});
            $this->assertSame($plain, Crypt::decryptString($raw->{$column}));
        }
        $this->assertSame($expected['presenting_concerns'], IntakeInterview::sole()->presenting_concerns);
    }

    public function test_concern_answers_and_notes_are_hidden_from_serialization(): void
    {
        $array = IntakeInterview::factory()->needingFollowUp()->create()->toArray();

        foreach ([...Form8::yesNoFields(), ...Form8::SPECIFY_FIELDS, ...array_keys(Form8::NOTES), 'relationship_during_intake'] as $field) {
            $this->assertArrayNotHasKey($field, $array);
        }
    }

    public function test_every_view_creates_an_access_log(): void
    {
        $interview = IntakeInterview::factory()->for($this->studentA, 'student')->create();

        $this->actingAs($this->counselorA)->get(route('staff.intake.show', $interview))->assertOk()->assertSee($interview->presenting_concerns);
        $this->actingAs($this->director)->get(route('staff.intake.show', $interview))->assertOk();
        $this->actingAs($this->counselorA)->get(route('staff.intake.edit', $interview))->assertOk();

        $this->assertSame(3, AccessLog::where('action', AccessLog::ACTION_VIEW_INTAKE)->where('intake_interview_id', $interview->id)->count());
        $this->assertSame(2, AccessLog::where('action', AccessLog::ACTION_VIEW_INTAKE)->where('user_id', $this->counselorA->id)->count());
    }

    public function test_record_page_shows_intake_history_without_answers(): void
    {
        $record = CumulativeRecord::factory()->submitted()->for($this->studentA, 'student')->create();
        $older = IntakeInterview::factory()->finalized()->for($this->studentA, 'student')->for($this->counselorA, 'counselor')->create(['interview_at' => '2026-09-01 09:00:00']);
        $newer = IntakeInterview::factory()->needingFollowUp()->for($this->studentA, 'student')->for($this->director, 'counselor')->create([
            'presenting_concerns' => 'Secret presenting concern text',
        ]);

        $html = $this->actingAs($this->counselorA)->get(route('staff.records.show', $record))
            ->assertOk()
            ->assertSee('New intake interview')
            ->assertSee(route('staff.intake.create', $this->studentA))
            ->assertSee('Sep 1, 2026 9:00 AM')
            ->assertSee($this->counselorA->name)
            ->assertSee($this->director->name)
            ->assertSee('Finalized')
            ->assertSee('Draft')
            ->assertSee('Needs follow-up')
            ->assertDontSee('Secret presenting concern text')
            ->assertDontSee('Attempted suicide')
            ->getContent();

        $this->assertLessThan(
            strpos($html, route('staff.intake.show', $older)),
            strpos($html, route('staff.intake.show', $newer)),
            'History is listed newest first.',
        );
        $this->assertSame(0, AccessLog::where('action', AccessLog::ACTION_VIEW_INTAKE)->count(), 'Listing is not a view of the interview.');
    }

    public function test_records_list_shows_intake_column_and_filters_by_it(): void
    {
        User::factory()->student($this->studentA->program)->create(['last_name' => 'Cruz']);
        $studentDraft = User::factory()->student($this->studentA->program)->create(['last_name' => 'Dizon']);
        IntakeInterview::factory()->for($studentDraft, 'student')->create();
        IntakeInterview::factory()->finalized()->needingFollowUp('substance_abuse')->for($this->studentA, 'student')->create();
        IntakeInterview::factory()->for($this->studentB, 'student')->create();

        $this->actingAs($this->counselorA)->get(route('staff.records.index'))
            ->assertOk()
            ->assertSee('Intake')
            ->assertSee('Needs follow-up')
            ->assertDontSee('Substance abuse');

        $names = fn (string $filter) => $this->actingAs($this->counselorA)
            ->get(route('staff.records.index', ['intake' => $filter]))
            ->assertOk()
            ->viewData('students')
            ->pluck('last_name')
            ->all();

        $this->assertSame(['Cruz'], $names('none'));
        $this->assertSame(['Dizon'], $names('draft'), 'Filters never reach outside the counselor\'s colleges.');
        $this->assertSame(['Aquino'], $names('finalized'));
        $this->assertSame(['Aquino'], $names('follow_up'));
        $this->assertSame(['Aquino', 'Cruz', 'Dizon'], $names('nonsense'));
    }

    public function test_tracker_still_renders_with_the_intake_column(): void
    {
        IntakeInterview::factory()->needingFollowUp()->for($this->studentA, 'student')->create();

        $this->actingAs($this->counselorA)->get(route('staff.tracker'))->assertOk()->assertSee('Needs follow-up');
    }
}
