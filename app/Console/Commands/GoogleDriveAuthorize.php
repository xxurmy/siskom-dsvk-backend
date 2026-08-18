<?php
// app/Console/Commands/GoogleDriveAuthorize.php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Console\Command;

class GoogleDriveAuthorize extends Command
{
    protected $signature = 'google-drive:authorize';
    protected $description = 'Ambil refresh token Google Drive dengan login manual sekali saja';

    public function handle(): int
    {
        $client = new GoogleClient();
        $client->setAuthConfig(storage_path('app/silvikultur-719cbf4f9484.json'));
        $client->addScope(GoogleDrive::DRIVE);
        $client->setAccessType('offline');   // wajib, supaya dapat refresh_token
        $client->setPrompt('consent');       // paksa munculkan consent screen lagi

        $authUrl = $client->createAuthUrl();

        $this->info('Buka URL ini di browser, login pakai akun Google Drive kamu:');
        $this->line($authUrl);

        $authCode = $this->ask('Paste kode otorisasi yang muncul di sini');

        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($accessToken['error'])) {
            $this->error('Gagal: ' . $accessToken['error_description']);
            return self::FAILURE;
        }

        file_put_contents(
            storage_path('app/google-drive-token.json'),
            json_encode($accessToken, JSON_PRETTY_PRINT)
        );

        $this->info('Berhasil! Token disimpan di storage/app/google-drive-token.json');
        $this->warn('Simpan file ini baik-baik, JANGAN di-commit ke git.');

        return self::SUCCESS;
    }
}