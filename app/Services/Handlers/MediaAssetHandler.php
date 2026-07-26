<?php

namespace App\Services\Handlers;

use App\Models\Admin;
use App\Models\MediaAsset;
use App\Models\MediaStorageProvider;
use App\Traits\FileManageTrait;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaAssetHandler
{
    use FileManageTrait;

    public function activeProvider(): MediaStorageProvider
    {
        $provider = MediaStorageProvider::query()->active()->latest('id')->first();

        if ($provider instanceof MediaStorageProvider) {
            return $provider;
        }

        $provider = $this->localProvider();

        if (! $provider->is_active) {
            $provider->forceFill(['is_active' => true])->save();
        }

        return $provider;
    }

    public function syncLocalPublicUploads(?Admin $admin = null, int $limit = 250): int
    {
        $provider = $this->localProvider();
        $disk     = Storage::disk('public');
        $files    = collect($disk->allFiles())
            ->reject(fn (string $path): bool => $this->shouldSkipLocalImport($path))
            ->values();

        if ($files->isEmpty()) {
            return 0;
        }

        $existingPaths = MediaAsset::query()
            ->where('driver', 'local')
            ->whereIn('path', $files->all())
            ->pluck('path')
            ->all();

        $existing = array_flip($existingPaths);
        $imported = 0;

        foreach ($files as $path) {
            if (isset($existing[$path])) {
                continue;
            }

            $this->importLocalAsset($provider, $path, $admin);
            $imported++;

            if ($imported >= $limit) {
                break;
            }
        }

        return $imported;
    }

    private function localProvider(): MediaStorageProvider
    {
        return MediaStorageProvider::query()->firstOrCreate(
            ['slug' => 'local'],
            [
                'name'       => 'Local Storage',
                'driver'     => 'local',
                'visibility' => 'public',
                'prefix'     => 'media',
                'is_active'  => true,
                'created_by' => auth('admin')->id(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>   $data
     * @return array<int, MediaAsset>
     */
    public function uploadMany(array $data, ?Admin $admin = null): array
    {
        $provider = isset($data['storage_provider_id'])
            ? MediaStorageProvider::query()->findOrFail($data['storage_provider_id'])
            : $this->activeProvider();

        $assets = [];

        foreach ($data['files'] as $file) {
            if ($file instanceof UploadedFile) {
                $assets[] = $this->upload($file, $provider, $data, $admin);
            }
        }

        return $assets;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upload(UploadedFile $file, MediaStorageProvider $provider, array $data, ?Admin $admin = null): MediaAsset
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $original  = $file->getClientOriginalName();
        $baseName  = pathinfo($original, PATHINFO_FILENAME);
        $fileName  = now()->format('Ymd_His').'_'.Str::slug($baseName, '_').'_'.Str::random(8).'.'.$extension;
        $folder    = $this->normalizeFolder($data['folder'] ?? null);
        $directory = collect([$provider->resolvedPrefix(), $folder, now()->format('Y/m/d')])
            ->filter()
            ->implode('/');
        $path = "{$directory}/{$fileName}";
        $disk = $this->diskFor($provider);

        if ($extension === 'svg') {
            $disk->put($path, $this->sanitiseSvg(file_get_contents($file->getRealPath()) ?: ''), [
                'visibility' => $provider->visibility,
            ]);
        } else {
            $stream = fopen($file->getRealPath(), 'rb');

            if ($stream === false) {
                throw new RuntimeException(__('Unable to read the uploaded file.'));
            }

            try {
                $disk->put($path, $stream, [
                    'visibility' => $provider->visibility,
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        $dimensions = $this->dimensions($file, $extension);

        return MediaAsset::query()->create([
            'media_storage_provider_id' => $provider->id,
            'uploaded_by_admin_id'      => $admin?->id,
            'title'                     => $data['title']    ?? null,
            'alt_text'                  => $data['alt_text'] ?? null,
            'caption'                   => $data['caption']  ?? null,
            'original_name'             => $original,
            'file_name'                 => $fileName,
            'extension'                 => $extension,
            'mime_type'                 => $file->getMimeType(),
            'aggregate_type'            => $this->aggregateType((string) $file->getMimeType(), $extension),
            'disk'                      => 'media',
            'driver'                    => $provider->driver,
            'visibility'                => $provider->visibility,
            'folder'                    => $folder,
            'path'                      => $path,
            'url'                       => $this->publicUrl($provider, $path),
            'size'                      => (int) $file->getSize(),
            'width'                     => $dimensions['width'],
            'height'                    => $dimensions['height'],
            'metadata'                  => [
                'provider_name' => $provider->name,
                'provider_slug' => $provider->slug,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAsset(MediaAsset $asset, array $data): MediaAsset
    {
        $asset->update([
            'title'    => $data['title']    ?? null,
            'alt_text' => $data['alt_text'] ?? null,
            'caption'  => $data['caption']  ?? null,
            'folder'   => $this->normalizeFolder($data['folder'] ?? null),
        ]);

        return $asset->refresh();
    }

    public function deleteAsset(MediaAsset $asset): void
    {
        $provider = $asset->storageProvider;

        if ($provider instanceof MediaStorageProvider) {
            $this->diskFor($provider)->delete($asset->path);
        } elseif ($asset->driver === 'local') {
            Storage::disk('public')->delete($asset->path);
        }

        $asset->delete();
    }

    public function activateProvider(MediaStorageProvider $provider): void
    {
        MediaStorageProvider::query()
            ->whereKeyNot($provider->id)
            ->update(['is_active' => false]);

        $provider->forceFill(['is_active' => true])->save();
    }

    /**
     * @return array{status: string, message: string}
     */
    public function testProvider(MediaStorageProvider $provider): array
    {
        $path = $provider->resolvedPrefix().'/.connection-test-'.Str::random(10).'.txt';

        try {
            $disk = $this->diskFor($provider);
            $disk->put($path, 'Digikash media storage test: '.now()->toDateTimeString(), [
                'visibility' => $provider->visibility,
            ]);

            if (! $disk->exists($path)) {
                throw new RuntimeException(__('The test file was not visible after upload.'));
            }

            $disk->delete($path);

            $provider->forceFill([
                'last_checked_at'    => now(),
                'last_check_status'  => 'success',
                'last_check_message' => __('Connection successful.'),
            ])->save();

            return ['status' => 'success', 'message' => __('Storage connection successful.')];
        } catch (Throwable $e) {
            report($e);

            $provider->forceFill([
                'last_checked_at'    => now(),
                'last_check_status'  => 'failed',
                'last_check_message' => $e->getMessage(),
            ])->save();

            return ['status' => 'error', 'message' => __('Storage connection failed: :message', ['message' => $e->getMessage()])];
        }
    }

    public function diskFor(MediaStorageProvider $provider): Filesystem
    {
        return Storage::build($provider->filesystemConfig());
    }

    public function publicUrl(MediaStorageProvider $provider, string $path): string
    {
        if (filled($provider->url)) {
            return rtrim((string) $provider->url, '/').'/'.ltrim($path, '/');
        }

        if ($provider->isLocal()) {
            return asset('storage/'.ltrim($path, '/'));
        }

        try {
            return $this->diskFor($provider)->url($path);
        } catch (Throwable) {
            return $path;
        }
    }

    private function normalizeFolder(mixed $folder): ?string
    {
        $folder = trim((string) $folder);

        if ($folder === '') {
            return null;
        }

        return Str::of($folder)
            ->replace('\\', '/')
            ->replaceMatches('#/+#', '/')
            ->trim('/')
            ->explode('/')
            ->map(fn (string $segment): string => Str::slug($segment, '-'))
            ->filter()
            ->implode('/');
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function dimensions(UploadedFile $file, string $extension): array
    {
        if (! in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($file->getRealPath());

        return [
            'width'  => isset($size[0]) ? (int) $size[0] : null,
            'height' => isset($size[1]) ? (int) $size[1] : null,
        ];
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function dimensionsFromPublicDisk(string $path, string $extension): array
    {
        if (! in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) {
            return ['width' => null, 'height' => null];
        }

        $absolutePath = Storage::disk('public')->path($path);
        $size         = is_file($absolutePath) ? @getimagesize($absolutePath) : false;

        return [
            'width'  => isset($size[0]) ? (int) $size[0] : null,
            'height' => isset($size[1]) ? (int) $size[1] : null,
        ];
    }

    private function importLocalAsset(MediaStorageProvider $provider, string $path, ?Admin $admin = null): MediaAsset
    {
        $fileName   = basename($path);
        $extension  = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $folder     = str_replace('\\', '/', dirname($path));
        $folder     = $folder === '.' ? null : $folder;
        $mimeType   = $this->publicDiskMimeType($path);
        $dimensions = $this->dimensionsFromPublicDisk($path, $extension);

        return MediaAsset::query()->create([
            'media_storage_provider_id' => $provider->id,
            'uploaded_by_admin_id'      => $admin?->id,
            'title'                     => null,
            'alt_text'                  => null,
            'caption'                   => null,
            'original_name'             => $fileName,
            'file_name'                 => $fileName,
            'extension'                 => $extension,
            'mime_type'                 => $mimeType,
            'aggregate_type'            => $this->aggregateType($mimeType ?? '', $extension),
            'disk'                      => 'public',
            'driver'                    => 'local',
            'visibility'                => 'public',
            'folder'                    => $folder,
            'path'                      => $path,
            'url'                       => $this->publicUrl($provider, $path),
            'size'                      => (int) Storage::disk('public')->size($path),
            'width'                     => $dimensions['width'],
            'height'                    => $dimensions['height'],
            'metadata'                  => [
                'provider_name' => $provider->name,
                'provider_slug' => $provider->slug,
                'imported_from' => 'public_disk',
            ],
        ]);
    }

    private function publicDiskMimeType(string $path): ?string
    {
        try {
            return Storage::disk('public')->mimeType($path) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function shouldSkipLocalImport(string $path): bool
    {
        $normalized = str_replace('\\', '/', trim($path));
        $basename   = basename($normalized);

        return $normalized === ''
            || str_starts_with($basename, '.')
            || str_contains($normalized, '/.')
            || str_ends_with($normalized, '/');
    }

    private function aggregateType(string $mimeType, string $extension): string
    {
        if (str_starts_with($mimeType, 'image/') || in_array($extension, ['svg', 'ico'], true)) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (in_array($extension, ['zip', 'rar'], true)) {
            return 'archive';
        }

        if (in_array($extension, ['pdf', 'doc', 'docx', 'txt', 'csv', 'xml', 'json', 'ppt', 'pptx', 'ods', 'odt', 'xls', 'xlsx'], true)) {
            return 'document';
        }

        return 'other';
    }
}
