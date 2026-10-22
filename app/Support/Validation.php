<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Shared input rules, so the server-side FormRequests and the HTML attributes in the views
 * (pattern, maxlength, type) always agree. Use `kind="name|phone|student-number|email"` on
 * <x-form.text> / <x-text-input> to get the matching HTML attributes.
 */
final class Validation
{
    /**
     * Person names: letters (including ñ and accents), spaces, periods, hyphens and apostrophes.
     */
    public const NAME_REGEX = "/^[\\pL\\pM][\\pL\\pM\\s.'’-]*$/u";

    /**
     * Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX (spaces and dashes are removed first).
     */
    public const PH_MOBILE_REGEX = '/^(09\d{9}|\+639\d{9})$/';

    public const STUDENT_NUMBER_REGEX = '/^[A-Za-z0-9-]+$/';

    /**
     * Messages for the rules above (merge into a FormRequest's messages()).
     *
     * @var array<string, string>
     */
    public const MESSAGES = [
        'name' => 'The :attribute may only contain letters, spaces, periods, hyphens and apostrophes.',
        'phone' => 'Enter a Philippine mobile number like 09171234567 or +639171234567.',
        'student_number' => 'The student number may only contain letters, numbers and dashes.',
    ];

    /**
     * Error messages for fields that use name/phone/student_number rules.
     *
     * @param  array<string, 'name'|'phone'|'student_number'>  $fields  field (wildcards allowed) => kind
     * @return array<string, string>
     */
    public static function messages(array $fields): array
    {
        $messages = [];
        foreach ($fields as $field => $kind) {
            $messages["{$field}.regex"] = self::MESSAGES[$kind];
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    public static function name(bool $required = true, int $max = 100): array
    {
        return [$required ? 'required' : 'nullable', 'string', "max:{$max}", 'regex:'.self::NAME_REGEX];
    }

    /**
     * @return list<string>
     */
    public static function phone(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:'.self::PH_MOBILE_REGEX];
    }

    /**
     * @return list<string>
     */
    public static function studentNumber(): array
    {
        return ['required', 'string', 'max:30', 'regex:'.self::STUDENT_NUMBER_REGEX];
    }

    /**
     * "Today" in the Philippines, for "not in the future" date rules. The app stores times in UTC,
     * so plain `before_or_equal:today` would reject today's date before 8 AM Manila time.
     */
    public static function today(): string
    {
        return CarbonImmutable::now(config('hdc.local_timezone', 'Asia/Manila'))->toDateString();
    }

    /**
     * Remove spaces, dashes, dots and brackets from phone numbers before validating them.
     */
    public static function normalizePhone(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/[\s\-().]/', '', $value) : $value;
    }

    /**
     * HTML attributes for an input kind (pattern uses the browser's Unicode-aware `v` regex mode).
     *
     * @return array<string, string|int>
     */
    public static function htmlAttributes(?string $kind): array
    {
        return match ($kind) {
            'name' => [
                'maxlength' => 100,
                'pattern' => "[\\p{L}\\p{M}][\\p{L}\\p{M}\\s.'’\\-]*",
                'title' => 'Letters, spaces, periods, hyphens and apostrophes only.',
                'autocapitalize' => 'words',
            ],
            'phone' => [
                'type' => 'tel',
                'maxlength' => 16,
                'pattern' => '(09\d{9}|\+639\d{9})',
                'title' => 'Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX',
                'inputmode' => 'tel',
                'placeholder' => '09XXXXXXXXX',
            ],
            'student-number' => [
                'maxlength' => 30,
                'pattern' => '[A-Za-z0-9\-]+',
                'title' => 'Letters, numbers and dashes only.',
            ],
            'email' => ['type' => 'email', 'maxlength' => 255],
            default => [],
        };
    }
}
