<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\College;
use App\Models\CumulativeRecord;
use App\Models\EntranceTestResult;
use App\Models\IntakeInterview;
use App\Models\Program;
use App\Models\User;
use App\Support\Reports\ReportResult;
use App\Support\Reports\ReportTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $counselorA;

    private User $director;

    private College $collegeA;

    private College $collegeB;

    private Program $programA;

    private Program $programB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collegeA = College::factory()->create(['code' => 'CIT']);
        $this->collegeB = College::factory()->create(['code' => 'CON']);
        $this->programA = Program::factory()->for($this->collegeA)->create(['code' => 'BSIT']);
        $this->programB = Program::factory()->for($this->collegeB)->create(['code' => 'BSN']);

        $this->counselorA = User::factory()->counselor([$this->collegeA])->create();
        $this->director = User::factory()->director()->create();
    }

    /**
     * Students (in the given program) each with one intake interview.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<User>
     */
    private function intakes(int $count, Program $program, array $attributes = [], bool $finalized = true, int $yearLevel = 1): array
    {
        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $student = User::factory()->student($program)->create(['year_level' => $yearLevel]);
            $factory = IntakeInterview::factory()->for($student, 'student');
            ($finalized ? $factory->finalized() : $factory)->create(['interview_at' => '2026-08-15 10:00:00', ...$attributes]);
            $students[] = $student;
        }

        return $students;
    }

    private function reportResult(TestResponse $response): ReportResult
    {
        return $response->assertOk()->viewData('result');
    }

    private function table(ReportResult $result, string $title): ReportTable
    {
        foreach ($result->tables as $table) {
            if ($table->title === $title) {
                return $table;
            }
        }

        $this->fail("No table titled {$title}");
    }

    private function cell(ReportResult $result, string $title, string $rowLabel, string $column = 'Students'): string
    {
        $table = $this->table($result, $title);
        $columnIndex = array_search($column, $table->headers, true);
        $this->assertNotFalse($columnIndex, "No column {$column} in {$title}");

        foreach ($table->rows as $row) {
            if ($row[0] === $rowLabel) {
                return $row[$columnIndex];
            }
        }

        $this->fail("No row {$rowLabel} in {$title}");
    }

    private function depressionCount(User $viewer, array $query = []): string
    {
        return $this->cell($this->reportResult($this->actingAs($viewer)->get(route('reports.show', ['concerns', ...$query]))), 'Psychological concerns', 'Depression');
    }

    public function test_students_get_403_on_every_report_route_and_see_no_link(): void
    {
        $student = User::factory()->student($this->programA)->create();

        $this->actingAs($student)->get(route('reports.index'))->assertForbidden();
        foreach (['concerns', 'form5', 'sata'] as $report) {
            $this->actingAs($student)->get(route('reports.show', $report))->assertForbidden();
            $this->actingAs($student)->get(route('reports.csv', $report))->assertForbidden();
            $this->actingAs($student)->get(route('reports.print', $report))->assertForbidden();
        }

        $this->actingAs($student)->get(route('student.dashboard'))->assertDontSee(route('reports.index'));
        $this->actingAs($this->counselorA)->get(route('counselor.dashboard'))->assertSee(route('reports.index'));
        $this->assertSame(0, AccessLog::count());
    }

    public function test_counselor_sees_only_counts_from_assigned_colleges(): void
    {
        $this->intakes(6, $this->programA, ['depression' => true]);
        $this->intakes(7, $this->programB, ['depression' => true]);

        $result = $this->reportResult($this->actingAs($this->counselorA)->get(route('reports.show', 'concerns')));
        $this->assertSame('6', $this->cell($result, 'Psychological concerns', 'Depression'));
        $this->assertSame(['Concern', 'CIT'], $this->table($result, 'Psychological concerns by college')->headers);
        $this->assertSame('6', $this->cell($result, 'Psychological concerns by college', 'Depression', 'CIT'));

        // Asking for another college's data is ignored, not honored.
        $this->assertSame('6', $this->depressionCount($this->counselorA, ['college_id' => $this->collegeB->id]));
        $this->actingAs($this->counselorA)->get(route('reports.show', 'concerns'))->assertDontSee('CON –', false);
    }

    public function test_director_sees_all_colleges(): void
    {
        $this->intakes(6, $this->programA, ['depression' => true]);
        $this->intakes(7, $this->programB, ['depression' => true]);

        $result = $this->reportResult($this->actingAs($this->director)->get(route('reports.show', 'concerns')));
        $this->assertSame('13', $this->cell($result, 'Psychological concerns', 'Depression'));
        $this->assertSame('6', $this->cell($result, 'Psychological concerns by college', 'Depression', 'CIT'));
        $this->assertSame('7', $this->cell($result, 'Psychological concerns by college', 'Depression', 'CON'));
        $this->assertSame('7', $this->depressionCount($this->director, ['college_id' => $this->collegeB->id]));
    }

    public function test_counts_from_1_to_4_show_as_less_than_5_on_the_page_chart_and_csv(): void
    {
        $this->intakes(3, $this->programA, ['anxiety_stress' => true]);
        $this->intakes(5, $this->programA, ['depression' => true]);

        $response = $this->actingAs($this->counselorA)->get(route('reports.show', 'concerns'));
        $result = $this->reportResult($response);
        $this->assertSame('<5', $this->cell($result, 'Psychological concerns', 'Anxiety or stress'));
        $this->assertSame('5', $this->cell($result, 'Psychological concerns', 'Depression'));
        $this->assertSame('0', $this->cell($result, 'Psychological concerns', 'Eating disorder'));
        $response->assertSee('&lt;5', false);

        // Chart: suppressed counts have no bar and sort after visible counts, before zeros.
        $chart = $result->chart;
        $this->assertSame('Depression', $chart['labels'][0]);
        $this->assertSame(5, $chart['values'][0]);
        $this->assertSame('Anxiety or stress', $chart['labels'][1]);
        $this->assertNull($chart['values'][1]);
        $this->assertSame('<5', $chart['display'][1]);
        $this->assertNotContains(3, $chart['values']);

        $csv = $this->actingAs($this->counselorA)->get(route('reports.csv', 'concerns'))->assertOk()->streamedContent();
        $this->assertStringContainsString("\"Anxiety or stress\",<5\n", $csv);
        $this->assertStringContainsString("Depression,5\n", $csv);
        $this->assertStringNotContainsString('stress",3', $csv);
        $this->assertStringContainsString('"Generated by","'.$this->counselorA->name.' (Counselor)"', $csv);

        $this->actingAs($this->counselorA)->get(route('reports.print', 'concerns'))->assertOk()->assertSee('&lt;5', false)->assertSee($this->counselorA->name);
    }

    public function test_min_group_size_comes_from_config(): void
    {
        $this->intakes(6, $this->programA, ['depression' => true]);

        config(['hdc.min_group_size' => 10]);
        $this->assertSame('<10', $this->depressionCount($this->counselorA));

        config(['hdc.min_group_size' => 5]);
        $this->assertSame('6', $this->depressionCount($this->counselorA));
    }

    public function test_draft_intakes_are_not_counted(): void
    {
        $this->intakes(5, $this->programA, ['depression' => true]);
        $this->intakes(4, $this->programA, ['depression' => true], finalized: false);

        $result = $this->reportResult($this->actingAs($this->counselorA)->get(route('reports.show', 'concerns')));
        $this->assertSame('5', $this->cell($result, 'Psychological concerns', 'Depression'));
        $this->assertSame('5', $result->summary[0]['value']);
    }

    public function test_each_student_counts_once_using_their_latest_finalized_intake_in_the_period(): void
    {
        $students = $this->intakes(5, $this->programA, ['depression' => true, 'interview_at' => '2026-07-01 09:00:00']);
        foreach ($students as $student) {
            IntakeInterview::factory()->finalized()->for($student, 'student')->create(['interview_at' => '2026-09-01 09:00:00', 'depression' => true]);
        }
        $this->assertSame('5', $this->depressionCount($this->counselorA), 'Two interviews per student still count once.');

        // A newer interview answering "No" replaces the older "Yes"...
        foreach ($students as $student) {
            IntakeInterview::factory()->finalized()->for($student, 'student')->create(['interview_at' => '2026-10-01 09:00:00', 'depression' => false]);
        }
        $this->assertSame('0', $this->depressionCount($this->counselorA));

        // ...unless the period ends before it.
        $this->assertSame('5', $this->depressionCount($this->counselorA, ['to' => '2026-09-30']));
    }

    public function test_filters_change_the_counts(): void
    {
        $this->intakes(5, $this->programA, ['depression' => true, 'interview_at' => '2026-08-10 09:00:00'], yearLevel: 1);
        $this->intakes(6, $this->programA, ['depression' => true, 'interview_at' => '2027-01-20 09:00:00'], yearLevel: 2);
        $otherProgram = Program::factory()->for($this->collegeA)->create(['code' => 'BSCS']);
        $this->intakes(7, $otherProgram, ['depression' => true, 'interview_at' => '2025-09-01 09:00:00'], yearLevel: 1);

        $this->assertSame('18', $this->depressionCount($this->counselorA));
        $this->assertSame('12', $this->depressionCount($this->counselorA, ['year_level' => 1]));
        $this->assertSame('11', $this->depressionCount($this->counselorA, ['program_id' => $this->programA->id]));
        $this->assertSame('11', $this->depressionCount($this->counselorA, ['school_year' => '2026-2027']));
        $this->assertSame('5', $this->depressionCount($this->counselorA, ['school_year' => '2026-2027', 'semester' => '1st']));
        $this->assertSame('6', $this->depressionCount($this->counselorA, ['school_year' => '2026-2027', 'semester' => '2nd']));
        $this->assertSame('7', $this->depressionCount($this->counselorA, ['school_year' => '2025-2026']));
        $this->assertSame('6', $this->depressionCount($this->counselorA, ['from' => '2027-01-01', 'to' => '2027-01-31']));
        $this->assertSame('0', $this->depressionCount($this->counselorA, ['school_year' => '2026-2027', 'from' => '2025-01-01', 'to' => '2025-12-31']));

        $this->assertSame(
            ['Concern', '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
            $this->table($this->reportResult($this->actingAs($this->counselorA)->get(route('reports.show', 'concerns'))), 'Psychological concerns by year level')->headers,
        );
    }

    public function test_views_and_exports_are_logged(): void
    {
        $this->actingAs($this->counselorA)->get(route('reports.show', ['concerns', 'year_level' => 2]))->assertOk();
        $this->actingAs($this->counselorA)->get(route('reports.csv', 'form5'))->assertOk()->assertDownload();
        $this->actingAs($this->director)->get(route('reports.print', 'sata'))->assertOk();

        $this->assertSame(1, AccessLog::where('action', AccessLog::ACTION_VIEW_REPORT)->where('user_id', $this->counselorA->id)->count());
        $view = AccessLog::where('action', AccessLog::ACTION_VIEW_REPORT)->sole();
        $this->assertStringStartsWith('concerns |', $view->details);
        $this->assertStringContainsString('Year level: 2nd Year', $view->details);

        $exports = AccessLog::where('action', AccessLog::ACTION_EXPORT_REPORT)->orderBy('id')->get();
        $this->assertCount(2, $exports);
        $this->assertStringStartsWith('form5 (csv)', $exports[0]->details);
        $this->assertSame($this->counselorA->id, $exports[0]->user_id);
        $this->assertStringStartsWith('sata (print)', $exports[1]->details);
        $this->assertSame($this->director->id, $exports[1]->user_id);
    }

    public function test_reports_never_show_names(): void
    {
        $students = $this->intakes(6, $this->programA, ['depression' => true]);

        foreach (['concerns', 'form5', 'sata'] as $report) {
            $this->actingAs($this->director)->get(route('reports.show', $report))->assertOk()->assertDontSee($students[0]->name)->assertDontSee($students[0]->student_number);
            $csv = $this->actingAs($this->director)->get(route('reports.csv', $report))->streamedContent();
            $this->assertStringNotContainsString($students[0]->name, $csv);
        }
    }

    public function test_form5_summary_counts_status_risk_items_and_willingness(): void
    {
        config(['hdc.min_group_size' => 1]);

        CumulativeRecord::factory()->submitted()->for(User::factory()->student($this->programA)->create(['year_level' => 1]), 'student')
            ->create(['self_description' => ['isolate', 'calm', 'frank', 'quiet', 'serious'], 'willing_to_see_counselor' => true]);
        CumulativeRecord::factory()->reviewed()->for(User::factory()->student($this->programA)->create(['year_level' => 2]), 'student')
            ->create(['self_description' => ['isolate', 'feels_depressed_often', 'calm', 'frank', 'quiet'], 'willing_to_see_counselor' => false]);
        CumulativeRecord::factory()->completed()->for(User::factory()->student($this->programA)->create(['year_level' => 1]), 'student')
            ->create(['self_description' => ['isolate', 'calm', 'frank', 'quiet', 'serious'], 'willing_to_see_counselor' => true]);
        User::factory()->student($this->programA)->create(['year_level' => 1]);
        CumulativeRecord::factory()->submitted()->for(User::factory()->student($this->programB)->create(), 'student')->create();

        $result = $this->reportResult($this->actingAs($this->counselorA)->get(route('reports.show', 'form5')));

        $this->assertSame(['College', 'Not started', 'Draft', 'Submitted', 'Reviewed'], $this->table($result, 'Submission status by college')->headers);
        foreach (['Not started' => '1', 'Draft' => '1', 'Submitted' => '1', 'Reviewed' => '1'] as $status => $expected) {
            $this->assertSame($expected, $this->cell($result, 'Submission status by college', 'CIT', $status));
        }
        $this->assertSame('1', $this->cell($result, 'Submission status by year level', '1st Year', 'Not started'));
        $this->assertSame('1', $this->cell($result, 'Submission status by year level', '2nd Year', 'Reviewed'));

        // Drafts are left out of the self-description and willingness counts.
        $this->assertSame('2', $this->cell($result, 'Self-description items that signal risk, by college', 'Isolate', 'CIT'));
        $this->assertSame('1', $this->cell($result, 'Self-description items that signal risk, by college', 'Feels depressed often', 'CIT'));
        $this->assertSame('0', $this->cell($result, 'Self-description items that signal risk, by college', 'Frequent mood changes', 'CIT'));
        $this->assertSame('1', $this->cell($result, 'Willing to see a counselor, by college', 'CIT', 'Yes'));
        $this->assertSame('1', $this->cell($result, 'Willing to see a counselor, by college', 'CIT', 'No'));

        // Term filter: no record for that term means "not started".
        $other = $this->reportResult($this->actingAs($this->counselorA)->get(route('reports.show', ['form5', 'school_year' => '2025-2026'])));
        $this->assertSame('4', $this->cell($other, 'Submission status by college', 'CIT', 'Not started'));
    }

    public function test_sata_summary_counts_descriptors_and_hides_small_averages(): void
    {
        foreach ([100, 104, 110, 96, 90] as $quotient) {
            EntranceTestResult::factory()->for(User::factory()->student($this->programA)->create(), 'student')
                ->create(['quotient' => $quotient, 'quotient_descriptor' => 'Average', 'exam_date' => '2026-06-15']);
        }
        foreach ([120, 121] as $quotient) {
            EntranceTestResult::factory()->for(User::factory()->student($this->programB)->create(), 'student')
                ->create(['quotient' => $quotient, 'quotient_descriptor' => 'Superior', 'exam_date' => '2026-06-15']);
        }

        $result = $this->reportResult($this->actingAs($this->director)->get(route('reports.show', 'sata')));
        $this->assertSame('5', $this->cell($result, 'Students per quotient descriptor, by college', 'Average', 'CIT'));
        $this->assertSame('<5', $this->cell($result, 'Students per quotient descriptor, by college', 'Superior', 'CON'));
        $this->assertSame('0', $this->cell($result, 'Students per quotient descriptor, by college', 'Very Poor', 'CIT'));
        $this->assertSame('100.0', $this->cell($result, 'Average quotient by college', 'CIT', 'Average quotient'));
        $this->assertSame('Hidden', $this->cell($result, 'Average quotient by college', 'CON', 'Average quotient'));
        $this->assertSame('<5', $this->cell($result, 'Average quotient by college', 'CON', 'Students'));

        $counselorResult = $this->reportResult($this->actingAs($this->counselorA)->get(route('reports.show', 'sata')));
        $this->assertSame(['College', 'Students', 'Average quotient'], $this->table($counselorResult, 'Average quotient by college')->headers);
        $this->assertCount(1, $this->table($counselorResult, 'Average quotient by college')->rows);
    }
}
