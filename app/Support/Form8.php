<?php

namespace App\Support;

/**
 * Field definitions for HDC Form 8: Intake Interview (docs/form-fields.md, section C).
 * Keys are database column names; labels follow the paper form.
 */
final class Form8
{
    /**
     * II. Psychological concerns, in the paper form's two columns (left, then right).
     * These are the concerns counted in HDC reports.
     *
     * @var array<string, string>
     */
    public const CONCERNS = [
        'eating_disorder' => 'Eating disorder',
        'depression' => 'Depression',
        'discrimination' => 'Discrimination',
        'pregnancy' => 'Pregnancy',
        'abortion' => 'Abortion',
        'anxiety_stress' => 'Anxiety or stress',
        'financial' => 'Financial concerns',
        'alcohol_use' => 'Alcohol use',
        'attempted_suicide' => 'Attempted suicide',
        'substance_abuse' => 'Substance abuse',
        'body_dysmorphic' => 'Body dysmorphic disorder',
        'miscarriage' => 'Miscarriage',
        'self_esteem' => 'Self-esteem',
        'sexual_assault' => 'Rape or sexual assault',
        'acculturation' => 'Acculturation',
        'loss_of_significant_others' => 'Loss of significant others',
    ];

    /**
     * Relationship concerns (listed under section II on the paper form).
     *
     * @var array<string, string>
     */
    public const RELATIONSHIP_CONCERNS = [
        'family' => 'Family',
        'same_sex' => 'Same sex',
        'peers' => 'Peers',
        'significant_others' => 'Significant others',
    ];

    /**
     * III. Coping strategies.
     *
     * @var array<string, string>
     */
    public const COPING_STRATEGIES = [
        'praying' => 'Praying',
        'laughing' => 'Laughing',
        'drinking' => 'Drinking',
        'malling' => 'Malling',
        'listening_to_music' => 'Listening to music',
        'crying' => 'Crying',
        'going_out_with_peers' => 'Going out with peers',
        'partying' => 'Partying',
        'shopping' => 'Shopping',
    ];

    /**
     * VI. Relationship with the client during the intake.
     *
     * @var array<string, string>
     */
    public const RELATIONSHIP_DURING_INTAKE = [
        'cooperative' => 'Cooperative',
        'aggressive' => 'Aggressive',
        'shy' => 'Shy',
        'hyperactive' => 'Hyperactive',
        'short_attention_span' => 'Short attention span',
    ];

    /**
     * IV, V, VII, VIII. Counselor notes: long text, encrypted at rest.
     *
     * @var array<string, string>
     */
    public const NOTES = [
        'client_description' => 'IV. Description of the client',
        'presenting_concerns' => 'V. Presenting concern/s',
        'conceptualization' => 'VII. Conceptualization of the intake summary session (e.g., degree of disturbance)',
        'recommendations' => 'VIII. Recommendation/s',
    ];

    /**
     * Free-text "specify" fields that describe concerns; encrypted at rest like the notes.
     *
     * @var list<string>
     */
    public const SPECIFY_FIELDS = ['concerns_specify', 'relationship_other', 'coping_other'];

    /**
     * @var array<string, string>
     */
    public const CITIZENSHIPS = [
        'filipino' => 'Filipino',
        'other' => 'Other',
    ];

    /**
     * I. Personal data snapshot columns (pre-filled from Form 5, editable).
     *
     * @var list<string>
     */
    public const PERSONAL_FIELDS = [
        'name', 'nickname', 'course', 'home_address', 'age', 'sex', 'birth_date', 'place_of_birth',
        'civil_status', 'spouse_name', 'number_of_children', 'citizenship', 'citizenship_other', 'religion',
        'contact_number', 'boarding_address', 'emergency_contact_name', 'emergency_contact_relationship',
        'emergency_contact_address', 'emergency_contact_number',
    ];

    /**
     * Every Yes/No column on the form.
     *
     * @return list<string>
     */
    public static function yesNoFields(): array
    {
        return [
            ...array_keys(self::CONCERNS),
            ...array_keys(self::RELATIONSHIP_CONCERNS),
            ...array_keys(self::COPING_STRATEGIES),
        ];
    }

    /**
     * Concern keys that trigger the "Needs follow-up" flag (config/hdc.php), limited to real concern columns.
     *
     * @return list<string>
     */
    public static function followUpConcerns(): array
    {
        return array_values(array_intersect(
            (array) config('hdc.follow_up_concerns', []),
            array_keys(self::CONCERNS),
        ));
    }
}
