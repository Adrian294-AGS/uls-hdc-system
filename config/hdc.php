<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Director Account
    |--------------------------------------------------------------------------
    |
    | Used by DirectorSeeder to create the single HDC Director account.
    | Set these in .env; they are never committed.
    |
    */

    'director' => [
        'email' => env('DIRECTOR_EMAIL'),
        'password' => env('DIRECTOR_PASSWORD'),
        'first_name' => env('DIRECTOR_FIRST_NAME', 'HDC'),
        'last_name' => env('DIRECTOR_LAST_NAME', 'Director'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Intake Interview: Urgent Follow-up Concerns
    |--------------------------------------------------------------------------
    |
    | Form 8 psychological concerns (keys from App\Support\Form8::CONCERNS)
    | that mark an intake interview "Needs follow-up" and notify the director.
    | The notification never says which concern was answered Yes.
    |
    */

    'follow_up_concerns' => [
        'attempted_suicide',
        'sexual_assault',
        'substance_abuse',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports: Smallest Group Shown
    |--------------------------------------------------------------------------
    |
    | Report counts from 1 to (min_group_size - 1) are shown as "<min_group_size"
    | (e.g. "<5") so small groups of students cannot be identified. Averages
    | based on fewer students than this are hidden. Zero is shown as 0.
    |
    */

    'min_group_size' => (int) env('HDC_MIN_GROUP_SIZE', 5),

    /*
    |--------------------------------------------------------------------------
    | Reports: Form 5 Self-description Items That Signal Risk
    |--------------------------------------------------------------------------
    |
    | Keys from App\Support\Form5::SELF_DESCRIPTIONS counted per college in the
    | Form 5 summary report.
    |
    */

    'risk_self_descriptions' => [
        'feels_depressed_often',
        'feeling_of_emptiness',
        'frequent_mood_changes',
        'lacks_self_confidence',
        'isolate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports: Semester Dates
    |--------------------------------------------------------------------------
    |
    | Intake interviews and SATA results only have dates, so the report filters
    | turn "school year + semester" into a date range with these month-day
    | bounds. The school year starts in June (see Form5::schoolYearFor()).
    | Months before June belong to the second calendar year of the school year.
    | HDC should confirm these against the university calendar.
    |
    */

    'semesters' => [
        '1st' => ['start' => '06-01', 'end' => '10-31'],
        '2nd' => ['start' => '11-01', 'end' => '03-31'],
        'summer' => ['start' => '04-01', 'end' => '05-31'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler and Queue
    |--------------------------------------------------------------------------
    |
    | schedule_timezone: the time zone for scheduled tasks (backups at 01:30).
    | queue_worker_via_scheduler: on shared hosting without Supervisor, set
    | QUEUE_WORKER_VIA_SCHEDULER=true so the every-minute cron also runs the
    | queue worker. Leave it false when Supervisor runs `queue:work`.
    |
    */

    'schedule_timezone' => env('SCHEDULE_TIMEZONE', 'Asia/Manila'),

    'queue_worker_via_scheduler' => (bool) env('QUEUE_WORKER_VIA_SCHEDULER', false),

    /*
    |--------------------------------------------------------------------------
    | Local Time Zone
    |--------------------------------------------------------------------------
    |
    | Used for "not in the future" date checks (birth date, exam date), so that
    | today's date in the Philippines is always accepted.
    |
    */

    'local_timezone' => env('LOCAL_TIMEZONE', 'Asia/Manila'),

];
