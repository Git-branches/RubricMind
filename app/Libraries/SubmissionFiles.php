<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Stores the original PDF or DOCX files students upload (FR-05, §5).
 *
 * Files live in writable/uploads/essays, outside the public web folder, under
 * random names; the student's file name is only kept in the database. They
 * are served only through controllers that check who may see them.
 *
 * An upload is "pending" until the student submits: the text is read first so
 * the student can check it, and the file is attached when they press Submit.
 */
final class SubmissionFiles
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    // Pending files the student never submitted are removed after this long.
    private const PENDING_LIFETIME = DAY;

    private const SESSION_KEY = 'pending_uploads';

    public static function directory(): string
    {
        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'essays' . DIRECTORY_SEPARATOR;
    }

    public static function path(string $storedName): string
    {
        // Stored names are generated here, but never trust a value from the database with a path.
        return self::directory() . basename($storedName);
    }

    /**
     * Keeps an uploaded file until the student submits, and remembers it in the session.
     *
     * @return array{name: string, path: string, size: int}
     */
    public static function storePending(UploadedFile $file, string $extension, int $assignmentId): array
    {
        self::removeOldPending();

        $storedName = 'pending-' . bin2hex(random_bytes(16)) . '.' . $extension;
        $size       = (int) $file->getSize();

        $file->move(self::directory(), $storedName);

        $pending = [
            'name' => self::safeName($file->getClientName(), $extension),
            'path' => $storedName,
            'size' => $size,
        ];

        // Replace (and delete) an earlier pending file for the same assignment.
        $all = session(self::SESSION_KEY) ?? [];

        if (isset($all[$assignmentId])) {
            self::delete($all[$assignmentId]['path']);
        }

        $all[$assignmentId] = $pending;
        session()->set(self::SESSION_KEY, $all);

        return $pending;
    }

    /**
     * @return array{name: string, path: string, size: int}|null
     */
    public static function pending(int $assignmentId): ?array
    {
        $pending = (session(self::SESSION_KEY) ?? [])[$assignmentId] ?? null;

        return $pending !== null && is_file(self::path($pending['path'])) ? $pending : null;
    }

    /**
     * Turns the pending file into the submission's file and forgets it in the session.
     *
     * @return array{name: string, path: string, size: int}|null The attached file.
     */
    public static function takePending(int $assignmentId): ?array
    {
        $pending = self::pending($assignmentId);

        if ($pending === null) {
            return null;
        }

        $storedName = 'essay-' . bin2hex(random_bytes(16)) . '.' . pathinfo($pending['path'], PATHINFO_EXTENSION);
        rename(self::path($pending['path']), self::path($storedName));

        self::forgetPending($assignmentId);

        return ['name' => $pending['name'], 'path' => $storedName, 'size' => $pending['size']];
    }

    public static function forgetPending(int $assignmentId): void
    {
        $all = session(self::SESSION_KEY) ?? [];
        unset($all[$assignmentId]);
        session()->set(self::SESSION_KEY, $all);
    }

    public static function delete(?string $storedName): void
    {
        if ($storedName !== null && $storedName !== '' && is_file(self::path($storedName))) {
            @unlink(self::path($storedName));
        }
    }

    /**
     * A file name that is safe to show and to send in a download header.
     */
    private static function safeName(string $clientName, string $extension): string
    {
        $base = pathinfo($clientName, PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '', $base) ?? '');
        $base = mb_substr($base === '' ? 'essay' : $base, 0, 120);

        return $base . '.' . $extension;
    }

    private static function removeOldPending(): void
    {
        foreach (glob(self::directory() . 'pending-*') ?: [] as $path) {
            if (filemtime($path) < time() - self::PENDING_LIFETIME) {
                @unlink($path);
            }
        }
    }
}
