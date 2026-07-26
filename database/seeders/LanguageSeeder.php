<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LanguageSeeder extends Seeder
{
    /**
     * Seed the default English language plus a fully-translated, right-to-left
     * Arabic language so RTL support is demonstrable out of the box.
     *
     * Uses firstOrCreate so it is strictly non-destructive: existing language
     * rows (their uploaded flags, status, default flag) are never overwritten.
     * The translation FILES (resources/lang/ar.json + resources/lang/ar/*.php)
     * ship with the repo; this seeder guarantees the matching database rows
     * exist and that each locale's files are present on a fresh install.
     */
    public function run(): void
    {
        $languages = [
            [
                'code'       => 'en',
                'flag'       => 'general/static/flags/us.svg',
                'name'       => 'English',
                'is_default' => true,
                'is_rtl'     => false,
                'status'     => true,
            ],
            [
                'code'       => 'ar',
                'flag'       => 'general/static/flags/ar.svg',
                'name'       => 'Arabic',
                'is_default' => false,
                'is_rtl'     => true,
                'status'     => true,
            ],
        ];

        foreach ($languages as $language) {
            Language::firstOrCreate(
                ['code' => $language['code']],
                $language
            );

            $this->ensureLanguageFiles($language['code']);
        }

        Language::flushCache();
    }

    /**
     * Make sure the JSON file and group directory exist for a locale so the
     * translation editor and the joedixon driver never hit a missing path.
     */
    private function ensureLanguageFiles(string $code): void
    {
        $jsonPath  = resource_path("lang/{$code}.json");
        $groupPath = resource_path("lang/{$code}");

        if (! File::exists($jsonPath)) {
            File::put($jsonPath, "{\n}\n");
        }

        if (! File::isDirectory($groupPath)) {
            File::makeDirectory($groupPath, 0755, true);
        }
    }
}
