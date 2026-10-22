<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Support\Backups;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * FR-11 backups (director only): list, back up now, download, delete.
 * Archives are AES-256 encrypted with BACKUP_ARCHIVE_PASSWORD; every action is logged.
 */
class BackupController extends Controller
{
    public function index(): View
    {
        try {
            $backups = Backups::all();
            $error = null;
        } catch (Throwable $exception) {
            report($exception);
            $backups = collect();
            $error = __('The backup folder could not be read. Check the server\'s storage permissions.');
        }

        return view('director.backups.index', [
            'backups' => $backups,
            'error' => $error,
            'encrypted' => Backups::isEncrypted(),
            'keepDays' => (int) config('backup.cleanup.default_strategy.keep_all_backups_for_days'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        @set_time_limit(600);

        try {
            $exitCode = Artisan::call('backup:run', ['--no-interaction' => true]);
        } catch (Throwable $exception) {
            report($exception);
            $exitCode = str_contains($exception->getMessage(), 'BACKUP_ARCHIVE_PASSWORD') ? 'password' : 1;
        }

        $this->log($request, AccessLog::ACTION_RUN_BACKUP, $exitCode === 0 ? 'success' : 'failed');

        return redirect()->route('director.backups.index')->with(...match ($exitCode) {
            0 => ['status', __('Backup completed.')],
            'password' => ['error', __('Backups are not set up yet: BACKUP_ARCHIVE_PASSWORD must be set in the server\'s .env file.')],
            default => ['error', __('The backup could not be completed. The problem has been logged; please try again or contact the system administrator.')],
        });
    }

    public function download(Request $request, string $filename): StreamedResponse
    {
        $backup = Backups::find($filename);
        abort_unless($backup, 404);

        $this->log($request, AccessLog::ACTION_DOWNLOAD_BACKUP, $filename);

        /** @var FilesystemAdapter $disk */
        $disk = $backup->disk();

        return $disk->download($backup->path(), $filename);
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        $backup = Backups::find($filename);
        abort_unless($backup, 404);

        try {
            $backup->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('The backup could not be deleted. Please try again.'));
        }

        $this->log($request, AccessLog::ACTION_DELETE_BACKUP, $filename);

        return redirect()->route('director.backups.index')->with('status', __('Backup :name deleted.', ['name' => $filename]));
    }

    private function log(Request $request, string $action, string $details): void
    {
        AccessLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'details' => $details,
            'ip' => $request->ip(),
        ]);
    }
}
