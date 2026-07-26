<?php

namespace Database\Factories;

use App\Models\MediaStorageProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaStorageProvider>
 */
class MediaStorageProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                    => 'Local Media',
            'slug'                    => 'local-media-'.$this->faker->unique()->numberBetween(1000, 9999),
            'driver'                  => 'local',
            'visibility'              => 'public',
            'prefix'                  => 'media',
            'is_active'               => false,
            'throw'                   => false,
            'use_path_style_endpoint' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
        ]);
    }

    public function s3(): static
    {
        return $this->state(fn (): array => [
            'name'                    => 'AWS S3',
            'slug'                    => 'aws-s3-'.$this->faker->unique()->numberBetween(1000, 9999),
            'driver'                  => 's3',
            'bucket'                  => 'digikash-media',
            'region'                  => 'us-east-1',
            'access_key'              => 'testing-key',
            'secret_key'              => 'testing-secret',
            'use_path_style_endpoint' => false,
        ]);
    }
}
