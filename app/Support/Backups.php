<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;

/**
 * Reads the encrypted backups made by spatie/laravel-backup (first configured disk).
 */
final class Backups
{
    /**
     * A backup older than this is flagged on the director dashboard.
     */
    public const STALE_AFTER_DAYS = 2;

    public static function destination(): BackupDestination
    {
        $disk = (array) config('backup.backup.destination.disks');

        return BackupDestination::create((string) (reset($disk) ?: 'backups'), (string) config('backup.backup.name'));
    }

    /**
     * Newest first.
     *
     * @return Collection<int, Backup>
     */
    public static function all(): Collection
    {
        return self::destination()->backups()->values();
    }

    /**
     * Find a backup by its file name only (never by a path from the request).
     */
    public static function find(string $filename): ?Backup
    {
        return self::all()->first(fn (Backup $backup) => basename($backup->path()) === $filename);
    }

    /**
     * Date of the newest backup, or null when there is none or the disk cannot be read.
     */
    public static function latestDate(): ?CarbonInterface
    {
        try {
            return self::destination()->newestBackup()?->date();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public static function isEncrypted(): bool
    {
        return filled(config('backup.backup.password')) && config('backup.backup.encryption') !== 'none';
    }
}
