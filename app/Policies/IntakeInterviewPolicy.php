<?php

namespace App\Policies;

use App\Models\IntakeInterview;
use App\Models\User;

/**
 * Form 8 intake interviews: staff only, and only for students in colleges they may access.
 * Students can never view or create one. Once finalized, only the director can edit.
 */
class IntakeInterviewPolicy
{
    /**
     * Start a new intake interview for a student.
     */
    public function create(User $user, User $student): bool
    {
        return $student->isVisibleTo($user);
    }

    public function view(User $user, IntakeInterview $interview): bool
    {
        return $interview->student->isVisibleTo($user);
    }

    public function update(User $user, IntakeInterview $interview): bool
    {
        return $this->view($user, $interview)
            && (! $interview->isFinalized() || $user->isDirector());
    }
}
