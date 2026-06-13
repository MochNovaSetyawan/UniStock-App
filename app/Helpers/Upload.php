<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Upload – file-upload utilities.
 */
final class Upload
{
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const MAX_BYTES    = 5 * 1024 * 1024; // 5 MB

    private function __construct() {}

    /**
     * Validates and moves an uploaded image.
     *
     * @param array  $file      Element from $_FILES
     * @param string $subfolder Subfolder inside UPLOAD_PATH (e.g. 'items', 'logo')
     * @return array{success: true, path: string}|array{error: string}
     */
    public static function image(array $file, string $subfolder = 'items'): array
    {
        $uploadDir = UPLOAD_PATH . $subfolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return ['error' => 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.'];
        }
        if ($file['size'] > self::MAX_BYTES) {
            return ['error' => 'Ukuran file maksimal 5 MB.'];
        }

        $filename = uniqid('', true) . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['error' => 'Gagal mengupload file. Periksa izin folder.'];
        }

        return ['success' => true, 'path' => $subfolder . '/' . $filename];
    }

    /**
     * Deletes an uploaded file by its relative path (relative to UPLOAD_PATH).
     */
    public static function delete(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }
        $full = UPLOAD_PATH . $relativePath;
        if (file_exists($full)) {
            unlink($full);
        }
    }
}
