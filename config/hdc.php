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

];
