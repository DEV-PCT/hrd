<?php

namespace App\Services\GoogleDrive;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien REST Google Drive v3 minimal (tanpa SDK pihak ketiga).
 * Autentikasi: service account (JWT RS256 -> access token), semua panggilan mendukung Shared Drive.
 */
class DriveClient
{
    private const API = 'https://www.googleapis.com/drive/v3';
    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/drive';
    public const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private array $key;
    private ?string $driveId = null;

    /** @param array $credentials ['client_email' => ..., 'private_key' => ..., 'token_uri' => ...] (dari .env) */
    public function __construct(array $credentials, private ?string $sharedDriveId = null, private ?string $sharedDriveName = null)
    {
        // Private key di .env ditulis satu baris dengan "\n"; ubah kembali menjadi baris baru
        $credentials['private_key'] = str_replace('\n', "\n", (string) ($credentials['private_key'] ?? ''));
        if (empty($credentials['client_email']) || ! str_contains($credentials['private_key'], 'PRIVATE KEY')) {
            throw new RuntimeException('Kredensial service account Google belum diisi (GDRIVE_CLIENT_EMAIL / GDRIVE_PRIVATE_KEY di .env).');
        }
        $this->key = $credentials;
    }

    // ---- Autentikasi -------------------------------------------------------

    public function accessToken(): string
    {
        $cacheKey = 'gdrive_token_'.md5($this->key['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $now = time();
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $b64(json_encode([
                'iss' => $this->key['client_email'],
                'scope' => self::SCOPE,
                'aud' => $this->key['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (! openssl_sign("{$header}.{$claims}", $sig, $this->key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Gagal menandatangani JWT service account.');
            }
            $res = Http::asForm()->post($this->key['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$header}.{$claims}.".$b64($sig),
            ]);
            $this->ensureOk($res, 'ambil token');

            return $res->json('access_token');
        });
    }

    private function http(): PendingRequest
    {
        // Koneksi ke googleapis kadang lambat: perpanjang connect timeout & ulangi bila gagal tersambung
        return Http::withToken($this->accessToken())->connectTimeout(30)->timeout(120)
            ->retry(3, 1000, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, false);
    }

    private function ensureOk(Response $res, string $what): void
    {
        if ($res->failed()) {
            throw new RuntimeException("Google Drive gagal {$what}: HTTP {$res->status()} ".$res->body());
        }
    }

    // ---- Shared Drive ------------------------------------------------------

    /** ID Shared Drive: dari konfigurasi, atau dicari menurut nama di antara drive yang dapat diakses service account. */
    public function driveId(): string
    {
        if ($this->driveId) {
            return $this->driveId;
        }
        if ($this->sharedDriveId) {
            return $this->driveId = $this->sharedDriveId;
        }

        return $this->driveId = Cache::rememberForever('gdrive_drive_id_'.md5((string) $this->sharedDriveName), function () {
            $drives = $this->listDrives();
            foreach ($drives as $d) {
                if ($this->sharedDriveName === null || $d['name'] === $this->sharedDriveName) {
                    return $d['id'];
                }
            }
            $names = implode(', ', array_column($drives, 'name')) ?: '(tidak ada)';
            throw new RuntimeException("Shared Drive \"{$this->sharedDriveName}\" tidak terlihat oleh service account. Drive yang terlihat: {$names}");
        });
    }

    public function listDrives(): array
    {
        $res = $this->http()->get(self::API.'/drives', ['pageSize' => 100]);
        $this->ensureOk($res, 'daftar Shared Drive');

        return $res->json('drives') ?? [];
    }

    // ---- Operasi berkas ----------------------------------------------------

    private function common(): array
    {
        return ['supportsAllDrives' => 'true'];
    }

    /** Cari anak langsung bernama $name di folder $parentId. */
    public function findChild(string $parentId, string $name, ?bool $folder = null): ?array
    {
        $q = sprintf("name = '%s' and '%s' in parents and trashed = false", $this->escape($name), $this->escape($parentId));
        if ($folder === true) {
            $q .= " and mimeType = '".self::FOLDER_MIME."'";
        } elseif ($folder === false) {
            $q .= " and mimeType != '".self::FOLDER_MIME."'";
        }
        $files = $this->query($q, 1);

        return $files[0] ?? null;
    }

    public function children(string $parentId): array
    {
        return $this->query(sprintf("'%s' in parents and trashed = false", $this->escape($parentId)));
    }

    private function query(string $q, int $limit = 0): array
    {
        $out = [];
        $token = null;
        do {
            $res = $this->http()->get(self::API.'/files', $this->common() + array_filter([
                'q' => $q,
                'corpora' => 'drive',
                'driveId' => $this->driveId(),
                'includeItemsFromAllDrives' => 'true',
                'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime)',
                'pageSize' => $limit ?: 1000,
                'pageToken' => $token,
            ]));
            $this->ensureOk($res, 'cari berkas');
            $out = array_merge($out, $res->json('files') ?? []);
            $token = $res->json('nextPageToken');
        } while ($token && (! $limit || count($out) < $limit));

        return $out;
    }

    public function get(string $id): array
    {
        $res = $this->http()->get(self::API."/files/{$id}", $this->common() + ['fields' => 'id, name, mimeType, size, modifiedTime']);
        $this->ensureOk($res, 'ambil metadata');

        return $res->json();
    }

    public function createFolder(string $parentId, string $name): array
    {
        $res = $this->http()
            ->post($this->url(self::API.'/files', $this->common() + ['fields' => 'id, name, mimeType']), ['name' => $name, 'mimeType' => self::FOLDER_MIME, 'parents' => [$parentId]]);
        $this->ensureOk($res, 'buat folder');

        return $res->json();
    }

    /** Unggah berkas baru (multipart) atau timpa isi berkas yang ada bila $existingId diberikan. */
    public function upload(string $parentId, string $name, string $contents, ?string $mime = null, ?string $existingId = null): array
    {
        $mime = $mime ?: 'application/octet-stream';
        $params = $this->common() + ['fields' => 'id, name, mimeType, size, modifiedTime'];

        if ($existingId) {
            $res = $this->http()
                ->withBody($contents, $mime)
                ->patch($this->url(self::UPLOAD."/files/{$existingId}", $params + ['uploadType' => 'media']));
        } else {
            $boundary = 'hrd'.bin2hex(random_bytes(12));
            $meta = json_encode(['name' => $name, 'parents' => [$parentId]]);
            $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$meta}\r\n"
                ."--{$boundary}\r\nContent-Type: {$mime}\r\n\r\n{$contents}\r\n--{$boundary}--";
            $res = $this->http()
                ->withBody($body, "multipart/related; boundary={$boundary}")
                ->post($this->url(self::UPLOAD.'/files', $params + ['uploadType' => 'multipart']));
        }
        $this->ensureOk($res, 'unggah berkas');

        return $res->json();
    }

    /** Unduh isi berkas sebagai stream. */
    public function download(string $id)
    {
        $res = $this->http()->withOptions(['stream' => true])
            ->get(self::API."/files/{$id}", $this->common() + ['alt' => 'media']);
        $this->ensureOk($res, 'unduh berkas');

        return $res->toPsrResponse()->getBody()->detach();
    }

    /**
     * Pindahkan ke Sampah. Service account berperan Content manager di Shared Drive sehingga tidak boleh
     * menghapus permanen (API menjawab 404); Sampah Shared Drive dikosongkan otomatis setelah 30 hari.
     */
    public function delete(string $id): void
    {
        $res = $this->http()->patch($this->url(self::API."/files/{$id}", $this->common()), ['trashed' => true]);
        $this->ensureOk($res, 'hapus berkas');
    }

    public function move(string $id, string $fromParent, string $toParent, string $newName): void
    {
        $res = $this->http()->patch($this->url(self::API."/files/{$id}", $this->common() + array_filter([
            'addParents' => $fromParent !== $toParent ? $toParent : null,
            'removeParents' => $fromParent !== $toParent ? $fromParent : null,
        ])), ['name' => $newName]);
        $this->ensureOk($res, 'pindah berkas');
    }

    public function copy(string $id, string $toParent, string $newName): void
    {
        $res = $this->http()
            ->post($this->url(self::API."/files/{$id}/copy", $this->common()),['name' => $newName, 'parents' => [$toParent]]);
        $this->ensureOk($res, 'salin berkas');
    }

    private function url(string $base, array $params): string
    {
        return $base.'?'.http_build_query($params);
    }

    private function escape(string $s): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $s);
    }
}
