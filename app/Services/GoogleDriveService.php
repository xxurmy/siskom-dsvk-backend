<?php
// app/Services/GoogleDriveService.php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;

class GoogleDriveService
{
    private GoogleDrive $drive;
    private GoogleClient $client;
    private string $rootFolderId;
    private string $tokenPath;

    public function __construct()
    {
        $this->tokenPath = storage_path('app/google-drive-token.json');

        $this->client = new GoogleClient();
        $this->client->setAuthConfig(storage_path('app/silvikultur-719cbf4f9484.json'));
        $this->client->addScope(GoogleDrive::DRIVE);
        $this->client->setAccessType('offline');

        if (! file_exists($this->tokenPath)) {
            throw new \RuntimeException(
                'Token Google Drive belum ada. Jalankan: php artisan google-drive:authorize'
            );
        }

        $accessToken = json_decode(file_get_contents($this->tokenPath), true);
        $this->client->setAccessToken($accessToken);

        // Access token cuma berlaku ~1 jam, refresh otomatis pakai refresh_token
        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $this->client->getRefreshToken() ?? $accessToken['refresh_token'] ?? null;

            if (! $refreshToken) {
                throw new \RuntimeException(
                    'Refresh token tidak ditemukan. Jalankan ulang: php artisan google-drive:authorize'
                );
            }

            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            // refresh_token lama tetap dipertahankan (Google kadang tidak kirim ulang)
            $newToken['refresh_token'] = $newToken['refresh_token'] ?? $refreshToken;

            file_put_contents($this->tokenPath, json_encode($newToken, JSON_PRETTY_PRINT));
            $this->client->setAccessToken($newToken);
        }

        $this->drive = new GoogleDrive($this->client);
        $this->rootFolderId = config('services.google_drive.root_folder_id');
    }

    public function findOrCreateUserFolder(string $nim, string $nama): string
    {
        $folderName = $this->sanitizeFolderName($nim . '_' . $nama);

        $query = sprintf(
            "'%s' in parents and name = '%s' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            $this->rootFolderId,
            addslashes($folderName)
        );

        $existing = $this->drive->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name)',
        ]);

        if (count($existing->getFiles()) > 0) {
            return $existing->getFiles()[0]->getId();
        }

        $folder = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$this->rootFolderId],
        ]);

        $created = $this->drive->files->create($folder, ['fields' => 'id']);

        return $created->getId();
    }

    public function uploadFile(string $folderId, UploadedFile $file, string $customFileName): array
    {
        $fileMetadata = new DriveFile([
            'name' => $customFileName,
            'parents' => [$folderId],
        ]);

        $uploaded = $this->drive->files->create(
            $fileMetadata,
            [
                'data' => file_get_contents($file->getRealPath()),
                'mimeType' => $file->getMimeType(),
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink',
            ]
        );

        $this->drive->permissions->create($uploaded->getId(), new Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]));

        return [
            'id'  => $uploaded->getId(),
            'url' => $uploaded->getWebViewLink() ?? "https://drive.google.com/file/d/{$uploaded->getId()}/view",
        ];
    }

    public function deleteFile(string $fileId): void
    {
        try {
            $this->drive->files->delete($fileId);
        } catch (\Exception $e) {
            // File mungkin sudah dihapus manual, abaikan
        }
    }

    private function sanitizeFolderName(string $name): string
    {
        return preg_replace('/[\/\\\?%*:|"<>]/', '-', $name);
    }

    /**
     * Replace isi file yang sudah ada (dipakai untuk re-upload/anti-duplikat).
     * File ID & link tetap sama, cuma kontennya yang diganti.
     * Return null kalau file lama sudah tidak ada di Drive (perlu fallback create baru).
     */
    public function replaceFile(string $fileId, UploadedFile $file, string $customFileName): ?array
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $customFileName,
            ]);

            $updated = $this->drive->files->update(
                $fileId,
                $fileMetadata,
                [
                    'data' => file_get_contents($file->getRealPath()),
                    'mimeType' => $file->getMimeType(),
                    'uploadType' => 'multipart',
                    'fields' => 'id, webViewLink',
                ]
            );

            return [
                'id'  => $updated->getId(),
                'url' => $updated->getWebViewLink() ?? "https://drive.google.com/file/d/{$updated->getId()}/view",
            ];
        } catch (\Google\Service\Exception $e) {
            // File lama sudah tidak ada / sudah dihapus manual di Drive -> null, controller fallback create baru
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}