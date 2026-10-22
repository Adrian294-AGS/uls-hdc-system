<?php

namespace App\Notifications;

use App\Models\IntakeInterview;
use Illuminate\Notifications\Notification;

/**
 * Tells the director an intake interview needs follow-up. Deliberately contains ONLY the
 * student's name and a link: never which concern was answered, never any notes.
 */
class IntakeNeedsFollowUp extends Notification
{
    public function __construct(public IntakeInterview $interview) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{intake_interview_id: int, student_name: string, url: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        $studentName = $this->interview->student->name;

        return [
            'intake_interview_id' => $this->interview->id,
            'student_name' => $studentName,
            'url' => route('staff.intake.show', $this->interview, absolute: false),
            'message' => "{$studentName}: intake interview needs follow-up.",
        ];
    }
}
