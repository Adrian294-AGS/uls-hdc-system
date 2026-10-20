<?php

namespace App\Support\Imports;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Keeps an uploaded file's checked rows between "preview" and "import" as JSON in
 * storage/app/imports (not public, not part of backups). Each file belongs to the user who
 * uploaded it and is removed after a day by the scheduler.
 */
final class ImportStore
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(User $user, array $data): string
    {
        $token = (string) Str::uuid();
        self::put($token, ['user_id' => $user->id, 'created_at' => now()->toIso8601String()] + $data);

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $token, User $user): ?array
    {
        if (! Str::isUuid($token) || ! is_file($path = self::path($token))) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && ($data['user_id'] ?? null) === $user->id ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function put(string $token, array $data): void
    {
        $directory = self::directory();
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents(self::path($token), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public static function prune(int $hours = 24): void
    {
        foreach (glob(self::directory().'/*.json') ?: [] as $file) {
            if (filemtime($file) < now()->subHours($hours)->getTimestamp()) {
                @unlink($file);
            }
        }
    }

    public static function directory(): string
    {
        return storage_path('app/imports');
    }

    private static function path(string $token): string
    {
        return self::directory().'/'.$token.'.json';
    }
}
