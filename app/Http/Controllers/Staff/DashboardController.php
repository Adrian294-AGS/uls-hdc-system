<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\Backups;
use App\Support\StudentRecordQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Counselor and director dashboards: headline counts for the colleges they may access.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view($user->isDirector() ? 'director.dashboard' : 'counselor.dashboard', [
            'stats' => (new StudentRecordQuery($user))->summary(),
            'lastBackup' => $user->isDirector() ? Backups::latestDate() : null,
        ]);
    }
}
