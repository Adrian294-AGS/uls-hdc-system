<?php

namespace App\Policies;

use App\Enums\RecordStatus;
use App\Models\CumulativeRecord;
use App\Models\User;

class CumulativeRecordPolicy
{
    /**
     * Students see only their own record. Staff see records of students in
     * colleges they may access (director: all; counselor: assigned colleges).
     */
    public function view(User $user, CumulativeRecord $record): bool
    {
        if ($user->isStudent()) {
            return $record->user_id === $user->id;
        }

        $collegeId = $record->student->program?->college_id;

        return $collegeId !== null && $user->accessibleCollegeIds()->contains($collegeId);
    }

    /**
     * Download the record (students: their own Form 5 PDF; staff: records they may view).
     */
    public function export(User $user, CumulativeRecord $record): bool
    {
        return $this->view($user, $record);
    }

    /**
     * Include SATA results or Form 8 intake interviews in an export: never students; only the
     * director or a counselor assigned to the student's college.
     */
    public function exportConfidential(User $user, CumulativeRecord $record): bool
    {
        return ! $user->isStudent() && $record->student->isVisibleTo($user);
    }

    /**
     * Only students start their own Form 5.
     */
    public function create(User $user): bool
    {
        return $user->isStudent();
    }

    /**
     * Only the owning student edits, and only until staff mark it reviewed.
     */
    public function update(User $user, CumulativeRecord $record): bool
    {
        return $user->isStudent()
            && $record->user_id === $user->id
            && $record->isEditableByStudent();
    }

    public function submit(User $user, CumulativeRecord $record): bool
    {
        return $this->update($user, $record);
    }

    /**
     * Staff who may see the record can mark it reviewed, once it is submitted.
     */
    public function review(User $user, CumulativeRecord $record): bool
    {
        return ! $user->isStudent()
            && $record->status === RecordStatus::Submitted
            && $this->view($user, $record);
    }
}
