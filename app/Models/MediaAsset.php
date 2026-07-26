<?php

namespace App\Models;

use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory;

    protected $fillable = [
        'media_storage_provider_id',
        'uploaded_by_admin_id',
        'title',
        'alt_text',
        'caption',
        'original_name',
        'file_name',
        'extension',
        'mime_type',
        'aggregate_type',
        'disk',
        'driver',
        'visibility',
        'folder',
        'path',
        'url',
        'size',
        'width',
        'height',
        'metadata',
    ];

    public function storageProvider(): BelongsTo
    {
        return $this->belongsTo(MediaStorageProvider::class, 'media_storage_provider_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }

    public function isImage(): bool
    {
        return $this->aggregate_type === 'image';
    }

    public function humanSize(): string
    {
        return Number::fileSize((int) $this->size);
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->original_name;
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size'     => 'integer',
            'width'    => 'integer',
            'height'   => 'integer',
        ];
    }
}
