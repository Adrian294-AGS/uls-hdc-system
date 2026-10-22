<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per staff view/action on a full Form 5 record, SATA result, intake interview or report.
 */
#[Fillable(['user_id', 'cumulative_record_id', 'entrance_test_result_id', 'intake_interview_id', 'action', 'details', 'ip'])]
class AccessLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_VIEW = 'view';

    public const ACTION_REVIEW = 'review';

    public const ACTION_VIEW_SATA = 'view_sata';

    public const ACTION_VIEW_INTAKE = 'view_intake';

    public const ACTION_EDIT_INTAKE = 'edit_intake';

    public const ACTION_VIEW_REPORT = 'view_report';

    public const ACTION_EXPORT_REPORT = 'export_report';

    public const ACTION_EXPORT_RECORD = 'export_record';

    public const ACTION_EXPORT_LIST = 'export_list';

    public const ACTION_IMPORT = 'import';

    public const ACTION_RUN_BACKUP = 'run_backup';

    public const ACTION_DOWNLOAD_BACKUP = 'download_backup';

    public const ACTION_DELETE_BACKUP = 'delete_backup';

    /**
     * @return BelongsTo<IntakeInterview, $this>
     */
    public function intakeInterview(): BelongsTo
    {
        return $this->belongsTo(IntakeInterview::class);
    }

    /**
     * @return BelongsTo<EntranceTestResult, $this>
     */
    public function entranceTestResult(): BelongsTo
    {
        return $this->belongsTo(EntranceTestResult::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CumulativeRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(CumulativeRecord::class, 'cumulative_record_id');
    }
}
