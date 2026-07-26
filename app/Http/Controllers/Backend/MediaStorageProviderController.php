<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\Backend\Media\StoreMediaStorageProviderRequest;
use App\Http\Requests\Backend\Media\UpdateMediaStorageProviderRequest;
use App\Models\MediaStorageProvider;
use App\Services\Handlers\MediaAssetHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class MediaStorageProviderController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'store|update|activate|test|destroy' => 'media-storage-manage',
        ];
    }

    public function store(StoreMediaStorageProviderRequest $request, MediaAssetHandler $handler): RedirectResponse
    {
        $provider = MediaStorageProvider::query()->create($this->payload($request->validated(), true));

        if ($provider->is_active) {
            $handler->activateProvider($provider);
        }

        notifyEvs('success', __('Media storage provider created successfully.'));

        return redirect()->route('admin.media.index', ['tab' => 'storage']);
    }

    public function update(UpdateMediaStorageProviderRequest $request, MediaStorageProvider $provider, MediaAssetHandler $handler): RedirectResponse
    {
        $provider->update($this->payload($request->validated(), false, $provider));

        if ($provider->is_active) {
            $handler->activateProvider($provider);
        }

        notifyEvs('success', __('Media storage provider updated successfully.'));

        return redirect()->route('admin.media.index', ['tab' => 'storage']);
    }

    public function activate(MediaStorageProvider $provider, MediaAssetHandler $handler): RedirectResponse
    {
        $handler->activateProvider($provider);

        notifyEvs('success', __('Active media storage provider updated.'));

        return redirect()->route('admin.media.index', ['tab' => 'storage']);
    }

    public function test(MediaStorageProvider $provider, MediaAssetHandler $handler): RedirectResponse
    {
        $result = $handler->testProvider($provider);

        notifyEvs($result['status'], $result['message']);

        return redirect()->route('admin.media.index', ['tab' => 'storage']);
    }

    public function destroy(MediaStorageProvider $provider): RedirectResponse
    {
        if ($provider->assets()->exists()) {
            notifyEvs('error', __('This storage provider has media files attached. Move or delete those files before removing the provider.'));

            return redirect()->route('admin.media.index', ['tab' => 'storage']);
        }

        if ($provider->is_active) {
            notifyEvs('error', __('The active storage provider cannot be deleted.'));

            return redirect()->route('admin.media.index', ['tab' => 'storage']);
        }

        $provider->delete();

        notifyEvs('success', __('Media storage provider deleted successfully.'));

        return redirect()->route('admin.media.index', ['tab' => 'storage']);
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data, bool $isCreate, ?MediaStorageProvider $provider = null): array
    {
        $payload = [
            'name'                    => $data['name'],
            'slug'                    => $data['slug'] ?: Str::slug($data['name']),
            'driver'                  => $data['driver'],
            'visibility'              => $data['visibility'],
            'prefix'                  => $data['prefix'] ?: 'media',
            'root'                    => $data['driver'] === 'local' ? ($data['root'] ?? null) : null,
            'url'                     => $data['url'] ?? null,
            'bucket'                  => $data['driver'] === 's3' ? ($data['bucket'] ?? null) : null,
            'region'                  => $data['driver'] === 's3' ? ($data['region'] ?? null) : null,
            'endpoint'                => $data['driver'] === 's3' ? ($data['endpoint'] ?? null) : null,
            'use_path_style_endpoint' => (bool) ($data['use_path_style_endpoint'] ?? false),
            'is_active'               => (bool) ($data['is_active'] ?? false),
            'updated_by'              => auth('admin')->id(),
        ];

        if ($isCreate) {
            $payload['created_by'] = auth('admin')->id();
        }

        if ($data['driver'] === 's3') {
            if ($isCreate || filled($data['access_key'] ?? null)) {
                $payload['access_key'] = $data['access_key'] ?? null;
            }

            if ($isCreate || filled($data['secret_key'] ?? null)) {
                $payload['secret_key'] = $data['secret_key'] ?? null;
            }
        } elseif ($provider) {
            $payload['access_key'] = null;
            $payload['secret_key'] = null;
        }

        return $payload;
    }
}
