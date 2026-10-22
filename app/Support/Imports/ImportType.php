<?php

namespace App\Support\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * One kind of Excel/CSV import. Each row is validated with the same rules as the matching form,
 * then classified as "valid", "skipped" (already exists / duplicate / example row) or "error".
 */
abstract class ImportType
{
    public const TYPES = [
        'roster' => RosterImport::class,
        'sata' => SataImport::class,
    ];

    public const MAX_ROWS = 2000;

    public const MAX_KILOBYTES = 5120;

    /**
     * Student numbers starting with this mark the template's example row, which is never imported.
     */
    public const EXAMPLE_PREFIX = 'EXAMPLE';

    public function __construct(protected User $user) {}

    public static function make(string $key, User $user): ?self
    {
        $class = self::TYPES[$key] ?? null;

        return $class ? new $class($user) : null;
    }

    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * Columns in file order: key => [label, required, description, example].
     *
     * @return array<string, array{label: string, required: bool, description: string, example: string}>
     */
    abstract public function columns(): array;

    /**
     * Dropdown choices for template data validation: column key => allowed values.
     *
     * @return array<string, list<string>>
     */
    abstract public function choices(): array;

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>
     */
    abstract protected function rules(array $values): array;

    /**
     * Why an otherwise valid row is skipped (already in the system), or null.
     *
     * @param  array<string, mixed>  $values
     */
    abstract protected function skipReason(array $values): ?string;

    /**
     * Key used to find duplicates inside the same file.
     *
     * @param  array<string, mixed>  $values
     */
    abstract protected function duplicateKey(array $values): string;

    /**
     * Save one valid row.
     *
     * @param  array<string, mixed>  $values
     */
    abstract public function import(array $values): void;

    /**
     * Normalize raw cell values (trim, empty to null, case) before validation.
     *
     * @param  array<string, string|null>  $values
     * @return array<string, string|null>
     */
    protected function normalize(array $values): array
    {
        return array_map(fn ($value) => $value === null || trim((string) $value) === '' ? null : trim((string) $value), $values);
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Validate every row and classify it.
     *
     * @param  list<array{row: int, values: array<string, string|null>}>  $rows
     * @return list<array{row: int, values: array<string, string|null>, status: string, errors: array<string, string>, reason: string|null}>
     */
    public function check(array $rows): array
    {
        $seen = [];
        $results = [];
        $attributes = collect($this->columns())->map(fn (array $column) => mb_strtolower($column['label']))->all();

        foreach ($rows as $row) {
            $values = $this->normalize($row['values']);
            $result = ['row' => $row['row'], 'values' => $values, 'status' => 'valid', 'errors' => [], 'reason' => null];

            if (str_starts_with(mb_strtoupper((string) ($values['student_number'] ?? '')), self::EXAMPLE_PREFIX)) {
                $results[] = ['status' => 'skipped', 'reason' => 'Example row from the template (not imported).'] + $result;

                continue;
            }

            $validator = Validator::make($values, $this->rules($values), $this->messages(), $attributes);
            if ($validator->fails()) {
                $result['status'] = 'error';
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $result['errors'][$field] = $messages[0];
                }
                $results[] = $result;

                continue;
            }

            $key = $this->duplicateKey($values);
            if (isset($seen[$key])) {
                $results[] = ['status' => 'skipped', 'reason' => "Duplicate of row {$seen[$key]} in this file."] + $result;

                continue;
            }
            $seen[$key] = $row['row'];

            if ($reason = $this->skipReason($values)) {
                $result['status'] = 'skipped';
                $result['reason'] = $reason;
            }

            $results[] = $result;
        }

        return $results;
    }
}
