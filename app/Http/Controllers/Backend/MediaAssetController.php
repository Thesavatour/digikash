<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\Backend\Media\StoreMediaAssetRequest;
use App\Http\Requests\Backend\Media\UpdateMediaAssetRequest;
use App\Models\MediaAsset;
use App\Models\MediaStorageProvider;
use App\Services\Handlers\MediaAssetHandler;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MediaAssetController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'index'   => 'media-list',
            'store'   => 'media-upload',
            'update'  => 'media-edit',
            'destroy' => 'media-delete',
        ];
    }

    public function index(Request $request, MediaAssetHandler $handler): View
    {
        $activeProvider = $handler->activeProvider();

        if (! $request->hasAny(['search', 'type', 'provider', 'folder', 'page', 'tab'])) {
            $handler->syncLocalPublicUploads(auth('admin')->user());
        }

        $providers = MediaStorageProvider::query()
            ->withCount('assets')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $assets = MediaAsset::query()
            ->with(['storageProvider', 'uploader'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->input('search');

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('alt_text', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn ($query): mixed => $query->where('aggregate_type', $request->input('type')))
            ->when($request->filled('provider'), fn ($query): mixed => $query->where('media_storage_provider_id', $request->integer('provider')))
            ->when($request->filled('folder'), fn ($query): mixed => $query->where('folder', $request->input('folder')))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $metrics = [
            'total'     => MediaAsset::query()->count(),
            'images'    => MediaAsset::query()->where('aggregate_type', 'image')->count(),
            'documents' => MediaAsset::query()->where('aggregate_type', 'document')->count(),
            'providers' => $providers->count(),
        ];

        $folders = MediaAsset::query()
            ->whereNotNull('folder')
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder');

        return view('backend.media.index', compact('assets', 'activeProvider', 'providers', 'metrics', 'folders'));
    }

    public function store(StoreMediaAssetRequest $request, MediaAssetHandler $handler): RedirectResponse
    {
        $assets = $handler->uploadMany($request->validated(), auth('admin')->user());

        notifyEvs('success', trans_choice('{1} Media file uploaded successfully.|[2,*] :count media files uploaded successfully.', count($assets), [
            'count' => count($assets),
        ]));

        return redirect()->route('admin.media.index');
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $mediaAsset, MediaAssetHandler $handler): RedirectResponse
    {
        $handler->updateAsset($mediaAsset, $request->validated());

        notifyEvs('success', __('Media details updated successfully.'));

        return redirect()->back();
    }

    public function destroy(MediaAsset $mediaAsset, MediaAssetHandler $handler): RedirectResponse
    {
        $handler->deleteAsset($mediaAsset);

        notifyEvs('success', __('Media file deleted successfully.'));

        return redirect()->back();
    }
}
