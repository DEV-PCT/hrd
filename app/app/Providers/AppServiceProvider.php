<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Folder public diganti nama menjadi public_html (sesuai hosting)
        $this->app->bind('path.public', fn () => base_path('public_html'));
    }

    public function boot()
    {
        Paginator::useBootstrapFive();
        config(['dompdf.public_path' => public_path()]); // dompdf default ke folder public, di sini public_html
        \Illuminate\Support\Carbon::setLocale('id'); // nama bulan & "x hari yang lalu" dalam Bahasa Indonesia
        // Audit: login & logout (semua jalur: password eUniversity maupun OTP)
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn () => \App\Models\AuditLog::catat('login'));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, fn () => \App\Models\AuditLog::catat('logout'));

        // Disk "google": berkas unggahan di Google Shared Drive lewat service account (adapter buatan sendiri)
        \Illuminate\Support\Facades\Storage::extend('google', function ($app, array $config) {
            $client = new \App\Services\GoogleDrive\DriveClient(
                $config['credentials'],$config['shared_drive_id'] ?: null, $config['shared_drive_name'] ?: null
            );
            $adapter = new \App\Services\GoogleDrive\GoogleDriveAdapter($client, $config['folder_id'] ?: null);

            return new \Illuminate\Filesystem\FilesystemAdapter(new \League\Flysystem\Filesystem($adapter, $config), $adapter, $config);
        });
    }
}
