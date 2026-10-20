<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\CumulativeRecord;
use App\Models\User;
use App\Support\Exports\DocumentWriter;
use App\Support\Exports\RecordDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * One student's record as PDF, Excel or Word (staff). SATA results and Form 8 intake interviews
 * are included only when asked for and only for staff assigned to the student's college.
 * Every export is logged as "export_record".
 */
class RecordExportController extends Controller
{
    public const FORMATS = ['pdf', 'xlsx', 'docx'];

    public const SECTIONS = ['sata' => 'SATA result', 'intake' => 'Form 8 intake interviews'];

    public function __invoke(Request $request, CumulativeRecord $record): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('export', $record);

        $validated = $request->validate([
            'format' => ['required', Rule::in(self::FORMATS)],
            'include' => ['nullable', 'array'],
            'include.*' => [Rule::in(array_keys(self::SECTIONS))],
        ]);
        $include = array_values(array_unique($validated['include'] ?? []));

        if ($include) {
            Gate::authorize('exportConfidential', $record);
        }

        return self::download($request, $record, $validated['format'], $include);
    }

    /**
     * Build, log and send the file (also used for a student's own Form 5 PDF, with no extra sections).
     *
     * @param  list<string>  $include
     */
    public static function download(Request $request, CumulativeRecord $record, string $format, array $include = []): BinaryFileResponse|RedirectResponse
    {
        $student = $record->student;
        $sata = in_array('sata', $include, true)
            ? $student->entranceTestResults()->with('encoder')->orderByDesc('exam_date')->orderByDesc('id')->get()
            : null;
        $intakes = in_array('intake', $include, true)
            ? $student->intakeInterviews()->with('counselor')->latest('id')->get()
            : null;

        try {
            $document = RecordDocument::build($record, $request->user(), $sata, $intakes);
            $path = match ($format) {
                'pdf' => DocumentWriter::recordPdf($document),
                'xlsx' => DocumentWriter::recordXlsx($document),
                'docx' => DocumentWriter::recordDocx($document),
            };
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('The file could not be created. Please try again, or choose another format.'));
        }

        self::log($request->user(), $request, $record, $format, $include, $intakes?->pluck('id')->all() ?? []);

        return response()
            ->download($path, $document->filename($format), ['Content-Type' => DocumentWriter::FORMATS[$format]['mime']])
            ->deleteFileAfterSend();
    }

    /**
     * @param  list<string>  $include
     * @param  list<int>  $intakeIds
     */
    private static function log(User $user, Request $request, CumulativeRecord $record, string $format, array $include, array $intakeIds): void
    {
        $sections = ['form5', ...$include];
        $details = "format: {$format}; sections: ".implode(', ', $sections);
        if ($intakeIds) {
            $details .= '; intake ids: '.implode(', ', $intakeIds);
        }

        AccessLog::create([
            'user_id' => $user->id,
            'cumulative_record_id' => $record->id,
            'action' => AccessLog::ACTION_EXPORT_RECORD,
            'details' => mb_substr($details, 0, 500),
            'ip' => $request->ip(),
        ]);
    }
}
