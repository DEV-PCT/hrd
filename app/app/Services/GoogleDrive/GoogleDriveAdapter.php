<?php

namespace App\Services\GoogleDrive;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Throwable;

/**
 * Adapter Flysystem ke Google Drive (Shared Drive) berbasis path.
 * Path "riwayat/abc.pdf" dipetakan ke folder "riwayat" lalu berkas "abc.pdf" di bawah folder akar.
 */
class GoogleDriveAdapter implements FilesystemAdapter
{
    /** cache path folder => id selama satu request */
    private array $folders = [];

    public function __construct(private DriveClient $drive, private ?string $rootFolderId = null)
    {
    }

    private function root(): string
    {
        return $this->rootFolderId ?: $this->drive->driveId();
    }

    private function split(string $path): array
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $pos = strrpos($path, '/');

        return $pos === false ? ['', $path] : [substr($path, 0, $pos), substr($path, $pos + 1)];
    }

    /** ID folder untuk path direktori; null bila tidak ada (atau dibuat bila $create). */
    private function folderId(string $dir, bool $create = false): ?string
    {
        $dir = trim($dir, '/');
        if ($dir === '') {
            return $this->root();
        }
        if (isset($this->folders[$dir])) {
            return $this->folders[$dir];
        }
        [$parentPath, $name] = $this->split($dir);
        $parentId = $this->folderId($parentPath, $create);
        if (! $parentId) {
            return null;
        }
        $found = $this->drive->findChild($parentId, $name, true);
        if (! $found && $create) {
            $found = $this->drive->createFolder($parentId, $name);
        }

        return $found ? ($this->folders[$dir] = $found['id']) : null;
    }

    /** Metadata berkas (bukan folder) untuk path, atau null. */
    private function file(string $path): ?array
    {
        [$dir, $name] = $this->split($path);
        $parentId = $this->folderId($dir);

        return $parentId && $name !== '' ? $this->drive->findChild($parentId, $name, false) : null;
    }

    private function fileOrFail(string $path, string $exception): array
    {
        $file = $this->file($path);
        if (! $file) {
            throw $exception === UnableToReadFile::class
                ? UnableToReadFile::fromLocation($path, 'Berkas tidak ditemukan')
                : UnableToRetrieveMetadata::create($path, 'metadata', 'Berkas tidak ditemukan');
        }

        return $file;
    }

    private function attributes(string $path, array $f): FileAttributes
    {
        return new FileAttributes(
            $path,
            isset($f['size']) ? (int) $f['size'] : null,
            null,
            isset($f['modifiedTime']) ? strtotime($f['modifiedTime']) : null,
            $f['mimeType'] ?? null,
        );
    }

    /** Tautan Google Drive untuk berkas (dipakai Storage::url()). Hanya bisa dibuka akun yang punya akses ke Shared Drive. */
    public function getUrl(string $path): string
    {
        $file = $this->fileOrFail($path, UnableToRetrieveMetadata::class);

        return "https://drive.google.com/file/d/{$file['id']}/view";
    }

    // ---- FilesystemAdapter -------------------------------------------------

    public function fileExists(string $path): bool
    {
        return $this->file($path) !== null;
    }

    public function directoryExists(string $path): bool
    {
        return $this->folderId($path) !== null;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            [$dir, $name] = $this->split($path);
            $parentId = $this->folderId($dir, true);
            $existing = $this->drive->findChild($parentId, $name, false);
            $mime = $config->get('mimetype') ?: (new FinfoMimeTypeDetector())->detectMimeType($name, $contents);
            $this->drive->upload($parentId, $name, $contents, $mime, $existing['id'] ?? null);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $stream = $this->readStream($path);
        $data = stream_get_contents($stream);
        fclose($stream);

        return $data;
    }

    public function readStream(string $path)
    {
        try {
            return $this->drive->download($this->fileOrFail($path, UnableToReadFile::class)['id']);
        } catch (UnableToReadFile $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            if ($file = $this->file($path)) {
                $this->drive->delete($file['id']);
            }
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            if (trim($path, '/') !== '' && ($id = $this->folderId($path))) {
                $this->drive->delete($id);
                $this->folders = [];
            }
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->folderId($path, true);
        } catch (Throwable $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Visibilitas diatur lewat izin Shared Drive.');
    }

    public function visibility(string $path): FileAttributes
    {
        $this->fileOrFail($path, UnableToRetrieveMetadata::class);

        return new FileAttributes($path, null, 'private');
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->attributes($path, $this->fileOrFail($path, UnableToRetrieveMetadata::class));
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->attributes($path, $this->fileOrFail($path, UnableToRetrieveMetadata::class));
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->attributes($path, $this->fileOrFail($path, UnableToRetrieveMetadata::class));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $base = trim($path, '/');
        $id = $this->folderId($base);
        if (! $id) {
            return;
        }
        foreach ($this->drive->children($id) as $f) {
            $child = ltrim($base.'/'.$f['name'], '/');
            if ($f['mimeType'] === DriveClient::FOLDER_MIME) {
                $this->folders[$child] = $f['id'];
                yield new DirectoryAttributes($child, null, isset($f['modifiedTime']) ? strtotime($f['modifiedTime']) : null);
                if ($deep) {
                    yield from $this->listContents($child, true);
                }
            } else {
                yield $this->attributes($child, $f);
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $file = $this->fileOrFail($source, UnableToReadFile::class);
            [$fromDir] = $this->split($source);
            [$toDir, $toName] = $this->split($destination);
            $this->delete($destination);
            $this->drive->move($file['id'], $this->folderId($fromDir), $this->folderId($toDir, true), $toName);
        } catch (Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $file = $this->fileOrFail($source, UnableToReadFile::class);
            [$toDir, $toName] = $this->split($destination);
            $this->delete($destination);
            $this->drive->copy($file['id'], $this->folderId($toDir, true), $toName);
        } catch (Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }
}
