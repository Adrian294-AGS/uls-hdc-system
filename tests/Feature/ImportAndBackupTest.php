<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\College;
use App\Models\EntranceTestResult;
use App\Models\Program;
use App\Models\User;
use App\Support\Imports\ImportStore;
use App\Support\Imports\SataImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Phase 3: Excel/CSV import with templates, and the director's backups page.
 */
class ImportAndBackupTest extends TestCase
{
    use RefreshDatabase;

    private const ROSTER_HEADER = ['last_name', 'first_name', 'middle_name', 'student_number', 'email', 'program_code', 'year_level'];

    private User $counselorA;

    private User $director;

    private Program $programA;

    private Program $programB;

    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $collegeA = College::factory()->create(['code' => 'CIT']);
        $collegeB = College::factory()->create(['code' => 'CON']);
        $this->programA = Program::factory()->for($collegeA)->create(['code' => 'BSIT']);
        $this->programB = Program::factory()->for($collegeB)->create(['code' => 'BSN']);
        $this->counselorA = User::factory()->counselor([$collegeA])->create();
        $this->director = User::factory()->director()->create();

        $this->backupRoot = sys_get_temp_dir().'/hdc-backup-test-'.uniqid();
        config(['filesystems.disks.backups.root' => $this->backupRoot]);
        Storage::forgetDisk('backups');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupRoot)) {
            exec('rm -rf '.escapeshellarg($this->backupRoot));
        }
        foreach (glob(ImportStore::directory().'/*.json') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows, array $header = self::ROSTER_HEADER, string $name = 'roster.csv'): UploadedFile
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header, escape: '');
        foreach ($rows as $row) {
            fputcsv($out, $row, escape: '');
        }
        rewind($out);

        return UploadedFile::fake()->createWithContent($name, stream_get_contents($out));
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows, array $header = self::ROSTER_HEADER): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Data')->fromArray([$header, ...$rows]);
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return UploadedFile::fake()->createWithContent('roster.xlsx', file_get_contents($path));
    }

    private function upload(User $user, UploadedFile $file, string $type = 'roster'): string
    {
        $response = $this->actingAs($user)->post(route('staff.imports.preview'), ['type' => $type, 'file' => $file]);
        $response->assertSessionHasNoErrors()->assertRedirect();

        return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
    }

    private function backupFile(string $name, string $contents = 'encrypted-zip'): void
    {
        @mkdir($this->backupRoot.'/uls-hdc', 0777, true);
        file_put_contents($this->backupRoot.'/uls-hdc/'.$name, $contents);
    }

    // ---- Templates --------------------------------------------------------------------------

    public function test_templates_download_with_the_right_headers_instructions_and_dropdowns(): void
    {
        $csv = $this->actingAs($this->counselorA)->get(route('staff.imports.template', ['roster', 'csv']))->assertOk();
        $lines = explode("\n", trim(ltrim($csv->getContent(), "\xEF\xBB\xBF")));
        $this->assertSame(implode(',', self::ROSTER_HEADER), $lines[0]);
        $this->assertStringStartsWith('"Dela Cruz",Juan,Santos,EXAMPLE-', $lines[1]);

        $xlsx = $this->actingAs($this->counselorA)->get(route('staff.imports.template', ['roster', 'xlsx']))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $xlsx->getContent());
        $book = IOFactory::load($path);
        unlink($path);

        $data = $book->getSheetByName('Data');
        $this->assertSame(self::ROSTER_HEADER, array_slice($data->rangeToArray('A1:G1')[0], 0, 7));
        $this->assertNotNull($book->getSheetByName('Instructions'));
        $this->assertStringContainsString('temporary password', implode(' ', array_merge(...$book->getSheetByName('Instructions')->toArray())));
        $validation = $data->getCell('F2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $validation->getType());
        // The dropdown lists only the counselor's own programs.
        $this->assertSame(['program_code', 'BSIT'], array_filter(array_column($book->getSheetByName('Lists')->toArray(), 0)));

        $sata = $this->actingAs($this->counselorA)->get(route('staff.imports.template', ['sata', 'csv']))->getContent();
        $this->assertStringContainsString('student_number,exam_date,vr_score,vr_descriptor,nvr_score', $sata);
        $this->assertStringContainsString(',quotient,quotient_descriptor', $sata);
    }

    // ---- Roster import ----------------------------------------------------------------------

    public function test_preview_flags_invalid_rows_and_only_valid_rows_are_imported(): void
    {
        User::factory()->student($this->programA)->create(['student_number' => '26-00009']);

        $token = $this->upload($this->counselorA, $this->xlsx([
            ['Dela Cruz', 'Juan', 'Santos', '26-00001', 'juan@example.com', 'bsit', '1'],               // valid (code case-insensitive)
            ['Peña', 'María', '', '26-00002', 'maria@example.com', 'BSIT', '2nd Year'],                  // valid
            ['Cruz2', 'Ana', '', '26-00003', 'not-an-email', 'BSIT', '9'],                               // 3 errors
            ['Reyes', 'Luis', '', '26-00009', 'luis@example.com', 'BSIT', '1'],                          // already exists
            ['Dela Cruz', 'Juan', 'Santos', '26-00001', 'juan@example.com', 'BSIT', '1'],                // duplicate in file
            ['Example', 'Row', '', 'EXAMPLE-26-00001', 'example@example.com', 'BSIT', '1'],              // template example
            ['', '', '', '', '', '', ''],                                                                // blank: ignored
        ]));

        $page = $this->actingAs($this->counselorA)->get(route('staff.imports.show', $token))->assertOk();
        $page->assertSee('Check before importing')
            ->assertSee('may only contain letters')
            ->assertSee('The email field must be a valid email address.')
            ->assertSee('A student with this student number already exists.')
            ->assertSee('Duplicate of row 2 in this file.')
            ->assertSee('Example row from the template')
            ->assertSee('Import 2 rows')
            ->assertSee('data-confirm="Import 2 rows?', false);
        $this->assertSame(3, substr_count($page->getContent(), 'data-cell-error'));
        $this->assertSame(1, User::where('role', 'student')->count(), 'Nothing is saved at preview.');

        $this->actingAs($this->counselorA)->post(route('staff.imports.store', $token))
            ->assertRedirect(route('staff.imports.show', $token))
            ->assertSessionHas('status', '2 rows imported.');

        $juan = User::where('student_number', '26-00001')->sole();
        $this->assertTrue($juan->isStudent());
        $this->assertSame('Juan Santos Dela Cruz', $juan->name);
        $this->assertSame($this->programA->id, $juan->program_id);
        $this->assertSame(1, $juan->year_level);
        $this->assertTrue($juan->must_change_password, 'They must change the temporary password on first login.');
        $this->assertTrue(Hash::check('26-00001', $juan->password), 'The temporary password is the student number.');
        $this->assertSame(2, User::where('student_number', '26-00002')->value('year_level'));
        $this->assertNull(User::where('student_number', '26-00003')->first());
        $this->assertNull(User::where('student_number', 'EXAMPLE-26-00001')->first());

        $this->actingAs($this->counselorA)->get(route('staff.imports.show', $token))
            ->assertSee('Import finished')
            ->assertSee('Download problem report (CSV)');

        $report = $this->actingAs($this->counselorA)->get(route('staff.imports.errors', $token))->assertOk()->getContent();
        $this->assertStringContainsString('Error', $report);
        $this->assertStringContainsString('Email: The email field must be a valid email address.', $report);
        $this->assertStringContainsString('Skipped,"A student with this student number already exists."', $report);
        $this->assertStringNotContainsString('26-00002', $report);

        // Importing the same file again creates nothing new.
        $again = $this->upload($this->counselorA, $this->xlsx([['Dela Cruz', 'Juan', 'Santos', '26-00001', 'juan@example.com', 'BSIT', '1']]));
        $this->actingAs($this->counselorA)->get(route('staff.imports.show', $again))->assertSee('already exists');
    }

    public function test_counselor_cannot_import_into_other_colleges(): void
    {
        $token = $this->upload($this->counselorA, $this->csv([
            ['Santos', 'Nina', '', '26-00010', 'nina@example.com', 'BSN', '1'],
        ]));
        $this->actingAs($this->counselorA)->get(route('staff.imports.show', $token))
            ->assertSee('Unknown program code, or the program is not in your colleges.');
        $this->actingAs($this->counselorA)->post(route('staff.imports.store', $token));
        $this->assertNull(User::where('student_number', '26-00010')->first());

        // SATA for a student outside the counselor's colleges is refused too.
        $outsider = User::factory()->student($this->programB)->create(['student_number' => '26-00020']);
        $header = array_keys((new SataImport($this->counselorA))->columns());
        $row = ['26-00020', '2026-06-15'];
        foreach (array_keys(EntranceTestResult::subtests()) as $key) {
            array_push($row, '10', 'Average');
        }
        array_push($row, '104', 'Average');
        $sataToken = $this->upload($this->counselorA, $this->csv([$row], $header, 'sata.csv'), 'sata');
        $this->actingAs($this->counselorA)->get(route('staff.imports.show', $sataToken))->assertSee('No student with this student number in your colleges.');

        // The director can import both.
        $directorToken = $this->upload($this->director, $this->csv([$row], $header, 'sata.csv'), 'sata');
        $this->actingAs($this->director)->post(route('staff.imports.store', $directorToken));
        $result = $outsider->entranceTestResults()->sole();
        $this->assertSame(104, $result->quotient);
        $this->assertSame($this->director->id, $result->encoded_by);
    }

    public function test_sata_import_validates_scores_dates_and_skips_existing_results(): void
    {
        $student = User::factory()->student($this->programA)->create(['student_number' => '26-00030']);
        EntranceTestResult::factory()->for($student, 'student')->create(['exam_date' => '2026-01-10']);
        $header = array_keys((new SataImport($this->counselorA))->columns());
        $row = function (string $date, string $score = '10', string $descriptor = 'average') {
            $row = ['26-00030', $date];
            foreach (array_keys(EntranceTestResult::subtests()) as $key) {
                array_push($row, $score, $descriptor);
            }

            return [...$row, '104', 'Average'];
        };

        $token = $this->upload($this->counselorA, $this->csv([
            $row('2026-06-15'),
            $row('2026-01-10'),
            $row('2099-01-01'),
            $row('15/06/2026'),
            $row('2026-06-16', '99', 'Great'),
        ], $header, 'sata.csv'), 'sata');

        $this->actingAs($this->counselorA)->get(route('staff.imports.show', $token))
            ->assertSee('already has a SATA result for this exam date')
            ->assertSee('cannot be in the future')
            ->assertSee('Use the date format YYYY-MM-DD')
            ->assertSee('Import 1 row');

        $this->actingAs($this->counselorA)->post(route('staff.imports.store', $token));
        $this->assertSame(2, $student->entranceTestResults()->count());
        $this->assertSame('Average', $student->entranceTestResults()->whereDate('exam_date', '2026-06-15')->sole()->vr_descriptor);
    }

    public function test_upload_limits_and_file_types(): void
    {
        $this->actingAs($this->counselorA)
            ->post(route('staff.imports.preview'), ['type' => 'roster', 'file' => UploadedFile::fake()->createWithContent('roster.txt', "a,b\n1,2")])
            ->assertSessionHasErrors('file');

        $this->actingAs($this->counselorA)
            ->post(route('staff.imports.preview'), ['type' => 'roster', 'file' => UploadedFile::fake()->create('roster.xlsx', 6000)])
            ->assertSessionHasErrors('file');

        $tooMany = array_map(fn ($i) => ['Cruz', 'Ana', '', sprintf('26-%05d', $i), "ana{$i}@example.com", 'BSIT', '1'], range(1, 2001));
        $this->actingAs($this->counselorA)
            ->post(route('staff.imports.preview'), ['type' => 'roster', 'file' => $this->csv($tooMany)])
            ->assertSessionHasErrors(['file' => 'The file has 2001 rows; the limit is 2000. Please split it into smaller files.']);

        $this->actingAs($this->counselorA)
            ->post(route('staff.imports.preview'), ['type' => 'roster', 'file' => $this->csv([['x']], ['something_else'])])
            ->assertSessionHasErrors('file');
    }

    public function test_previews_are_private_to_the_uploader_and_students_are_refused(): void
    {
        $token = $this->upload($this->counselorA, $this->csv([['Cruz', 'Ana', '', '26-00040', 'ana@example.com', 'BSIT', '1']]));
        $otherCounselor = User::factory()->counselor([$this->programA->college])->create();

        $this->actingAs($otherCounselor)->get(route('staff.imports.show', $token))->assertNotFound();
        $this->actingAs($otherCounselor)->post(route('staff.imports.store', $token))->assertNotFound();
        $this->actingAs($this->director)->get(route('staff.imports.errors', $token))->assertNotFound();

        $student = User::factory()->student($this->programA)->create();
        $this->actingAs($student)->get(route('staff.imports.index'))->assertForbidden();
        $this->actingAs($student)->get(route('staff.imports.template', ['roster', 'xlsx']))->assertForbidden();
        $this->actingAs($student)->post(route('staff.imports.preview'), ['type' => 'roster'])->assertForbidden();
    }

    public function test_imports_are_logged(): void
    {
        $token = $this->upload($this->counselorA, $this->csv([
            ['Cruz', 'Ana', '', '26-00050', 'ana@example.com', 'BSIT', '1'],
            ['Bad1', 'Ana', '', '26-00051', 'ana2@example.com', 'BSIT', '1'],
        ]));
        $this->actingAs($this->counselorA)->post(route('staff.imports.store', $token));
        $this->actingAs($this->counselorA)->post(route('staff.imports.store', $token)); // second click: nothing new

        $log = AccessLog::where('action', AccessLog::ACTION_IMPORT)->sole();
        $this->assertSame($this->counselorA->id, $log->user_id);
        $this->assertSame('type: roster; imported: 1; skipped: 0; errors: 1; file: roster.csv', $log->details);
        $this->assertSame(1, User::where('student_number', '26-00050')->count());
    }

    // ---- Backups --------------------------------------------------------------------------------

    public function test_only_the_director_can_see_and_run_backups(): void
    {
        $this->actingAs($this->counselorA)->get(route('director.backups.index'))->assertForbidden();
        $this->actingAs($this->counselorA)->post(route('director.backups.store'))->assertForbidden();
        $this->actingAs($this->counselorA)->get(route('counselor.dashboard'))->assertDontSee(route('director.backups.index'));

        $this->actingAs($this->director)->get(route('director.dashboard'))->assertSee(route('director.backups.index'));
        $this->actingAs($this->director)->get(route('director.backups.index'))->assertOk()
            ->assertSee('Back up now')
            ->assertSee('data-confirm="'.e('Make a backup now?'), false);
    }

    public function test_back_up_now_runs_the_backup_and_is_logged(): void
    {
        Artisan::shouldReceive('call')->once()->with('backup:run', ['--no-interaction' => true])->andReturn(0);

        $this->actingAs($this->director)->post(route('director.backups.store'))
            ->assertRedirect(route('director.backups.index'))
            ->assertSessionHas('status', 'Backup completed.');

        $this->assertSame('success', AccessLog::where('action', AccessLog::ACTION_RUN_BACKUP)->sole()->details);
    }

    public function test_a_failed_backup_shows_a_friendly_message(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new \RuntimeException('Set BACKUP_ARCHIVE_PASSWORD in .env: production backups must be encrypted.'));

        $this->actingAs($this->director)->post(route('director.backups.store'))
            ->assertSessionHas('error', 'Backups are not set up yet: BACKUP_ARCHIVE_PASSWORD must be set in the server\'s .env file.');
        $this->assertSame('failed', AccessLog::where('action', AccessLog::ACTION_RUN_BACKUP)->sole()->details);
    }

    public function test_backups_are_listed_downloaded_and_deleted(): void
    {
        $this->backupFile('2026-10-18-02-00-00.zip', str_repeat('x', 2048));
        $this->backupFile('2026-10-19-02-00-00.zip', 'newest');
        $this->backupFile('notes.txt', 'not a backup');

        $page = $this->actingAs($this->director)->get(route('director.backups.index'))->assertOk()->getContent();
        $this->assertLessThan(strpos($page, '2026-10-18-02-00-00.zip'), strpos($page, '2026-10-19-02-00-00.zip'), 'Newest first.');
        $this->assertStringContainsString('2.0 KB', $page);
        $this->assertStringNotContainsString('notes.txt', $page);

        $download = $this->actingAs($this->director)->get(route('director.backups.download', '2026-10-19-02-00-00.zip'))->assertOk();
        $this->assertSame('newest', $download->streamedContent());
        $this->assertSame('2026-10-19-02-00-00.zip', AccessLog::where('action', AccessLog::ACTION_DOWNLOAD_BACKUP)->sole()->details);

        $this->actingAs($this->director)->get(route('director.backups.download', '2026-01-01-00-00-00.zip'))->assertNotFound();
        $this->actingAs($this->director)->get('/director/backups/..%2F..%2F.env')->assertNotFound();

        $this->actingAs($this->director)->delete(route('director.backups.destroy', '2026-10-18-02-00-00.zip'))->assertRedirect(route('director.backups.index'));
        $this->assertFileDoesNotExist($this->backupRoot.'/uls-hdc/2026-10-18-02-00-00.zip');
        $this->assertSame(1, AccessLog::where('action', AccessLog::ACTION_DELETE_BACKUP)->count());
    }

    public function test_dashboard_shows_the_last_backup_and_warns_when_it_is_older_than_two_days(): void
    {
        $this->actingAs($this->director)->get(route('director.dashboard'))->assertSee('No backups yet')->assertSee('Make the first backup');

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(9, 0));
        $this->backupFile('2026-10-17-02-00-00.zip');
        $this->actingAs($this->director)->get(route('director.dashboard'))
            ->assertSee('October 17, 2026')
            ->assertSee('Older than 2 days: check the server cron job');

        $this->backupFile('2026-10-20-02-00-00.zip');
        $this->actingAs($this->director)->get(route('director.dashboard'))
            ->assertSee('October 20, 2026')
            ->assertSee('Up to date')
            ->assertDontSee('Older than 2 days');
    }
}
