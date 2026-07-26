<?php

namespace App\Models;

use Database\Factories\MediaStorageProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MediaStorageProvider extends Model
{
    /** @use HasFactory<MediaStorageProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'driver',
        'visibility',
        'prefix',
        'bucket',
        'region',
        'endpoint',
        'url',
        'root',
        'access_key',
        'secret_key',
        'use_path_style_endpoint',
        'is_active',
        'throw',
        'options',
        'last_checked_at',
        'last_check_status',
        'last_check_message',
        'created_by',
        'updated_by',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function filesystemConfig(): array
    {
        if ($this->driver === 's3') {
            return array_filter([
                'driver'                  => 's3',
                'key'                     => $this->access_key,
                'secret'                  => $this->secret_key,
                'region'                  => $this->region,
                'bucket'                  => $this->bucket,
                'url'                     => $this->url,
                'endpoint'                => $this->endpoint,
                'use_path_style_endpoint' => $this->use_path_style_endpoint,
                'visibility'              => $this->visibility,
                'throw'                   => $this->throw,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return [
            'driver'     => 'local',
            'root'       => $this->root ?: storage_path('app/public'),
            'url'        => $this->url ?: asset('storage'),
            'visibility' => $this->visibility,
            'throw'      => $this->throw,
        ];
    }

    public function resolvedPrefix(): string
    {
        $prefix = trim((string) $this->prefix, '/');

        return $prefix !== '' ? Str::of($prefix)->replace('\\', '/')->trim('/')->toString() : 'media';
    }

    public function isLocal(): bool
    {
        return $this->driver === 'local';
    }

    public function isS3Compatible(): bool
    {
        return $this->driver === 's3';
    }

    protected function casts(): array
    {
        return [
            'access_key'              => 'encrypted',
            'secret_key'              => 'encrypted',
            'use_path_style_endpoint' => 'boolean',
            'is_active'               => 'boolean',
            'throw'                   => 'boolean',
            'options'                 => 'array',
            'last_checked_at'         => 'datetime',
        ];
    }
}
