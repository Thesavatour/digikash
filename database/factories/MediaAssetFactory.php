<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\MediaStorageProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_storage_provider_id' => MediaStorageProvider::factory(),
            'title'                     => $this->faker->words(3, true),
            'alt_text'                  => $this->faker->sentence(4),
            'caption'                   => $this->faker->sentence(),
            'original_name'             => 'sample.jpg',
            'file_name'                 => 'sample.jpg',
            'extension'                 => 'jpg',
            'mime_type'                 => 'image/jpeg',
            'aggregate_type'            => 'image',
            'disk'                      => 'media',
            'driver'                    => 'local',
            'visibility'                => 'public',
            'folder'                    => 'media',
            'path'                      => 'media/sample.jpg',
            'url'                       => asset('storage/media/sample.jpg'),
            'size'                      => 1024,
            'width'                     => 640,
            'height'                    => 360,
            'metadata'                  => [],
        ];
    }
}
