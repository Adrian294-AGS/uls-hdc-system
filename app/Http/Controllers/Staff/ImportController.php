<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Support\Imports\ImportStore;
use App\Support\Imports\ImportType;
use App\Support\Imports\SpreadsheetReader;
use App\Support\Imports\TemplateBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Excel/CSV import (student roster, SATA results): upload -> preview with every problem per cell
 * -> confirm -> only valid rows are saved, in one transaction -> summary with an error report.
 * Counselors can only import into their own colleges (checked per row). Logged as "import".
 */
class ImportController extends Controller
{
    public function index(): View
    {
        return view('staff.imports.index', [
            'types' => collect(ImportType::TYPES)->map(fn ($class, $key) => ImportType::make($key, request()->user())),
        ]);
    }

    public function template(Request $request, string $type, string $format): Response
    {
        $import = ImportType::make($type, $request->user());
        abort_unless($import && in_array($format, ['xlsx', 'csv'], true), 404);

        $filename = 'HDC_import_template_'.$type.'.'.$format;

        return $format === 'xlsx'
            ? response(TemplateBuilder::xlsx($import), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ])
            : response(TemplateBuilder::csv($import), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ImportType::TYPES))],
            'file' => ['required', 'file', 'max:'.ImportType::MAX_KILOBYTES, 'extensions:xlsx,csv', 'mimes:xlsx,csv,txt'],
        ], [
            'file.max' => 'The file may not be larger than 5 MB.',
            'file.extensions' => 'Upload an Excel (.xlsx) or CSV (.csv) file.',
            'file.mimes' => 'Upload an Excel (.xlsx) or CSV (.csv) file.',
        ]);

        $import = ImportType::make($validated['type'], $request->user());
        $file = $request->file('file');

        try {
            $rows = SpreadsheetReader::read($file->getRealPath(), mb_strtolower($file->getClientOriginalExtension()), $import);
            $results = $import->check($rows);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['file' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['file' => 'The file could not be read. Please save it again as .xlsx or .csv from the template and try again.']);
        }

        $token = ImportStore::create($request->user(), [
            'type' => $import->key(),
            'filename' => mb_substr($file->getClientOriginalName(), 0, 150),
            'status' => 'preview',
            'results' => $results,
        ]);

        return redirect()->route('staff.imports.show', $token);
    }

    public function show(Request $request, string $token): View
    {
        $data = ImportStore::get($token, $request->user());
        abort_unless($data, 404);

        return view('staff.imports.preview', [
            'token' => $token,
            'data' => $data,
            'import' => ImportType::make($data['type'], $request->user()),
            'counts' => collect($data['results'])->countBy('status')->all() + ['valid' => 0, 'error' => 0, 'skipped' => 0, 'imported' => 0],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = ImportStore::get($token, $request->user());
        abort_unless($data, 404);

        if ($data['status'] === 'done') {
            return redirect()->route('staff.imports.show', $token);
        }

        $import = ImportType::make($data['type'], $request->user());
        // Check again: data may have changed since the preview (e.g. a student registered meanwhile).
        $results = $import->check(array_map(fn ($result) => ['row' => $result['row'], 'values' => $result['values']], $data['results']));

        try {
            DB::transaction(function () use ($import, &$results): void {
                foreach ($results as $index => $result) {
                    if ($result['status'] === 'valid') {
                        $import->import($result['values']);
                        $results[$index]['status'] = 'imported';
                    }
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Nothing was imported because of an unexpected problem. Please try again.'));
        }

        $counts = collect($results)->countBy('status');
        $summary = [
            'imported' => $counts['imported'] ?? 0,
            'skipped' => $counts['skipped'] ?? 0,
            'errors' => $counts['error'] ?? 0,
        ];

        ImportStore::put($token, ['status' => 'done', 'results' => $results, 'summary' => $summary, 'imported_at' => now()->toIso8601String()] + $data);

        AccessLog::create([
            'user_id' => $request->user()->id,
            'action' => AccessLog::ACTION_IMPORT,
            'details' => mb_substr("type: {$import->key()}; imported: {$summary['imported']}; skipped: {$summary['skipped']}; errors: {$summary['errors']}; file: {$data['filename']}", 0, 500),
            'ip' => $request->ip(),
        ]);

        return redirect()->route('staff.imports.show', $token)
            ->with('status', trans_choice(':count row imported.|:count rows imported.', $summary['imported']));
    }

    /**
     * Rows that were not imported, with the reason, as CSV.
     */
    public function errors(Request $request, string $token): Response
    {
        $data = ImportStore::get($token, $request->user());
        abort_unless($data, 404);
        $import = ImportType::make($data['type'], $request->user());
        $columns = $import->columns();

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Row', 'Status', 'Problems', ...array_keys($columns)], escape: '');
        foreach ($data['results'] as $result) {
            if (in_array($result['status'], ['valid', 'imported'], true)) {
                continue;
            }
            $problems = $result['reason'] ?? collect($result['errors'])->map(fn ($message, $field) => ($columns[$field]['label'] ?? $field).': '.$message)->implode(' | ');
            $values = array_map(fn ($key) => self::csvCell($result['values'][$key] ?? ''), array_keys($columns));
            fputcsv($out, [$result['row'], $result['status'] === 'error' ? 'Error' : 'Skipped', $problems, ...$values], escape: '');
        }
        rewind($out);

        return response((string) stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="HDC_import_problems_'.$data['type'].'.csv"',
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        abort_unless(ImportStore::get($token, $request->user()), 404);
        @unlink(ImportStore::directory().'/'.$token.'.json');

        return redirect()->route('staff.imports.index')->with('status', __('The upload was discarded. Nothing was imported.'));
    }

    private static function csvCell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
