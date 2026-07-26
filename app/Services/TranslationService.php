<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use JoeDixon\Translation\Drivers\Translation;

class TranslationService
{
    public function __construct(
        private readonly Translation $translation
    ) {}

    /**
     * Get all languages
     */
    public function allLanguages(): Collection
    {
        return $this->translation->allLanguages();
    }

    /**
     * Get groups for a language
     */
    public function getGroupsFor(string $language): Collection
    {
        return $this->translation->getGroupsFor($language);
    }

    /**
     * Add language
     */
    public function addLanguage(string $code, string $name): void
    {
        $this->translation->addLanguage($code, $name);
    }

    /**
     * Filter translations with error handling for server compatibility
     */
    public function filterTranslationsFor(string $languageCode, ?string $filter = null): Collection
    {
        try {
            // Try using the original method
            return $this->translation->filterTranslationsFor($languageCode, $filter);
        } catch (\Throwable $e) {
            // Fallback: Manual implementation to avoid reference passing issues
            return $this->manualFilterTranslations($languageCode, $filter);
        }
    }

    /**
     * Add translation
     */
    public function add($request, string $language, bool $isGroupTranslation): void
    {
        $this->translation->add($request, $language, $isGroupTranslation);
    }

    /**
     * Return flattened translation rows in small pages to keep the admin UI fast.
     *
     * @return array{rows:LengthAwarePaginator<int, array{group:string,key:string,source:string,target:string,missing:bool}>, total:int, missing:int, translated:int}
     */
    public function paginatedRowsFor(
        string $languageCode,
        ?string $filter = null,
        ?string $group = null,
        int $page = 1,
        int $perPage = 50,
        ?string $langPath = null
    ): array {
        $langPath       = $langPath ?? resource_path('lang');
        $sourceLocale   = config('app.locale', 'en');
        $fallbackLocale = config('app.fallback_locale', 'en');
        $page           = max(1, $page);
        $perPage        = max(1, $perPage);
        $offset         = ($page - 1) * $perPage;
        $rows           = [];
        $matched        = 0;
        $missing        = 0;

        $consume = function (array $row) use (&$rows, &$matched, &$missing, $offset, $perPage, $filter): void {
            if (! $this->matchesTranslationFilter($row, $filter)) {
                return;
            }

            if ($row['missing']) {
                $missing++;
            }

            if ($matched >= $offset && count($rows) < $perPage) {
                $rows[] = $row;
            }

            $matched++;
        };

        if ($group === null || $group === '' || $group === 'single') {
            $sourceSingle   = $this->jsonTranslationsFor($langPath, $sourceLocale);
            $fallbackSingle = $sourceLocale === $fallbackLocale ? [] : $this->jsonTranslationsFor($langPath, $fallbackLocale);
            $targetSingle   = $this->jsonTranslationsFor($langPath, $languageCode);
            $singleKeys     = array_values(array_unique(array_merge(array_keys($fallbackSingle), array_keys($sourceSingle), array_keys($targetSingle))));

            foreach ($singleKeys as $key) {
                $target = $this->translationText($targetSingle[$key] ?? null);

                $consume([
                    'group'   => 'single',
                    'key'     => (string) $key,
                    'source'  => $this->translationText($sourceSingle[$key] ?? $fallbackSingle[$key] ?? null, (string) $key),
                    'target'  => $target,
                    'missing' => trim($target) === '',
                ]);
            }
        }

        if ($group !== 'single') {
            $groups = ($group !== null && $group !== '')
                ? [$group]
                : $this->groupNamesFor($langPath, $sourceLocale, $fallbackLocale, $languageCode);

            foreach ($groups as $groupName) {
                $sourceGroup   = $this->phpGroupTranslationsFor($langPath, $sourceLocale, $groupName);
                $fallbackGroup = $sourceLocale === $fallbackLocale ? [] : $this->phpGroupTranslationsFor($langPath, $fallbackLocale, $groupName);
                $targetGroup   = $this->phpGroupTranslationsFor($langPath, $languageCode, $groupName);
                $groupKeys     = array_values(array_unique(array_merge(array_keys($fallbackGroup), array_keys($sourceGroup), array_keys($targetGroup))));

                foreach ($groupKeys as $key) {
                    $target = $this->translationText($targetGroup[$key] ?? null);

                    $consume([
                        'group'   => (string) $groupName,
                        'key'     => (string) $key,
                        'source'  => $this->translationText($sourceGroup[$key] ?? $fallbackGroup[$key] ?? null, (string) $key),
                        'target'  => $target,
                        'missing' => trim($target) === '',
                    ]);
                }
            }
        }

        $paginator = new LengthAwarePaginator(
            $rows,
            $matched,
            $perPage,
            $page,
            [
                'path'  => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        return [
            'rows'       => $paginator,
            'total'      => $matched,
            'missing'    => $missing,
            'translated' => $matched - $missing,
        ];
    }

    /**
     * Build a report of how many source-language keys are missing from each of
     * the other languages.
     *
     * The source language is the configured fallback locale. A "missing" key is
     * one that exists in the source language (single JSON or grouped PHP files)
     * but is absent from the target language — exactly the set that
     * `translation:sync-missing-translation-keys` will create.
     *
     * @return array{total:int, languages:array<string,int>}
     */
    public function missingKeysReport(?string $langPath = null): array
    {
        $completion = $this->completionReport($langPath);

        $languages = [];
        $total     = 0;

        foreach ($completion['languages'] as $locale => $metrics) {
            if ($locale === $completion['source_locale']) {
                continue;
            }

            $missing = $metrics['missing'];

            $languages[$locale] = $missing;
            $total += $missing;
        }

        return [
            'total'     => $total,
            'languages' => $languages,
        ];
    }

    /**
     * Build per-locale translation completion metrics against the source locale.
     *
     * @return array{source_locale:string, source_total:int, languages:array<string, array{translated:int,total:int,missing:int,percentage:int}>}
     */
    public function completionReport(?string $langPath = null): array
    {
        $langPath     = $langPath ?? resource_path('lang');
        $sourceLocale = config('app.fallback_locale', 'en');
        $sourceKeys   = $this->keysForLocale($langPath, $sourceLocale);
        $sourceTotal  = count($sourceKeys);
        $languages    = [];

        foreach ($this->availableLocales($langPath) as $locale) {
            $missing    = $locale === $sourceLocale ? 0 : count(array_diff($sourceKeys, $this->keysForLocale($langPath, $locale)));
            $translated = max(0, $sourceTotal - $missing);

            $languages[$locale] = [
                'translated' => $translated,
                'total'      => $sourceTotal,
                'missing'    => $missing,
                'percentage' => $sourceTotal > 0 ? (int) round(($translated / $sourceTotal) * 100) : 100,
            ];
        }

        return [
            'source_locale' => $sourceLocale,
            'source_total'  => $sourceTotal,
            'languages'     => $languages,
        ];
    }

    /**
     * Discover every locale that has a single JSON file or a grouped directory.
     *
     * @return array<int,string>
     */
    private function availableLocales(string $langPath): array
    {
        $locales = [];

        if (File::isDirectory($langPath)) {
            foreach (File::files($langPath) as $file) {
                if ($file->getExtension() === 'json') {
                    $locales[] = $file->getFilenameWithoutExtension();
                }
            }

            foreach (File::directories($langPath) as $directory) {
                $locales[] = basename($directory);
            }
        }

        return array_values(array_unique($locales));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonTranslationsFor(string $langPath, string $locale): array
    {
        $file = "{$langPath}/{$locale}.json";

        if (! File::exists($file)) {
            return [];
        }

        $decoded = json_decode(File::get($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function phpGroupTranslationsFor(string $langPath, string $locale, string $group): array
    {
        $file = "{$langPath}/{$locale}/{$group}.php";

        if (! File::exists($file)) {
            return [];
        }

        $entries = File::getRequire($file);

        return Arr::dot(is_array($entries) ? $entries : []);
    }

    private function translationText(mixed $value, string $fallback = ''): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? $fallback);
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function groupNamesFor(string $langPath, string ...$locales): array
    {
        $groups = [];

        foreach ($locales as $locale) {
            $groupPath = "{$langPath}/{$locale}";

            if (! File::isDirectory($groupPath)) {
                continue;
            }

            foreach (File::files($groupPath) as $file) {
                if ($file->getExtension() === 'php') {
                    $groups[] = $file->getFilenameWithoutExtension();
                }
            }
        }

        sort($groups);

        return array_values(array_unique($groups));
    }

    /**
     * @param array{group:string,key:string,source:string,target:string,missing:bool} $row
     */
    private function matchesTranslationFilter(array $row, ?string $filter): bool
    {
        $filter = trim((string) $filter);

        if ($filter === '') {
            return true;
        }

        foreach (['group', 'key', 'source', 'target'] as $field) {
            if (stripos((string) $row[$field], $filter) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect every translatable key for a locale, namespaced so single and
     * grouped keys can never collide.
     *
     * @return array<int,string>
     */
    private function keysForLocale(string $langPath, string $locale): array
    {
        $keys = [];

        $jsonFile = "{$langPath}/{$locale}.json";

        if (File::exists($jsonFile)) {
            $decoded = json_decode(File::get($jsonFile), true) ?? [];

            foreach (array_keys($decoded) as $key) {
                $keys[] = "single::{$key}";
            }
        }

        $groupPath = "{$langPath}/{$locale}";

        if (File::isDirectory($groupPath)) {
            foreach (File::files($groupPath) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $group   = $file->getFilenameWithoutExtension();
                $entries = File::getRequire($file->getPathname());

                foreach (array_keys(Arr::dot(is_array($entries) ? $entries : [])) as $key) {
                    $keys[] = "{$group}::{$key}";
                }
            }
        }

        return $keys;
    }

    /**
     * Manual translation filtering to avoid reference passing issues
     */
    private function manualFilterTranslations(string $languageCode, ?string $filter = null): Collection
    {
        $langPath       = resource_path('lang');
        $appLocale      = config('app.locale', 'en');
        $fallbackLocale = config('app.fallback_locale', 'en');

        // Use fallback locale as reference, unless we're translating fallback itself
        $referenceLocale = ($languageCode === $fallbackLocale) ? $appLocale : $fallbackLocale;

        // Get single translations from JSON file
        $singleTranslations = $this->getSingleTranslations($langPath, $languageCode, $referenceLocale, $filter);

        // Get group translations from PHP files
        $groupTranslations = $this->getGroupTranslations($langPath, $languageCode, $referenceLocale, $filter);

        return new Collection([
            'single' => new Collection(['single' => $singleTranslations]),
            'group'  => $groupTranslations,
        ]);
    }

    /**
     * Get single translations from JSON file
     */
    private function getSingleTranslations(
        string $langPath,
        string $languageCode,
        string $fallbackLocale,
        ?string $filter
    ): Collection {
        $targetJsonFile   = "{$langPath}/{$languageCode}.json";
        $fallbackJsonFile = "{$langPath}/{$fallbackLocale}.json";

        $targetTranslations   = File::exists($targetJsonFile) ? json_decode(File::get($targetJsonFile), true)     ?? [] : [];
        $fallbackTranslations = File::exists($fallbackJsonFile) ? json_decode(File::get($fallbackJsonFile), true) ?? [] : [];

        // Merge keys from both files
        $allKeys      = array_unique(array_merge(array_keys($targetTranslations), array_keys($fallbackTranslations)));
        $translations = new Collection;

        foreach ($allKeys as $key) {
            $fallbackValue = $fallbackTranslations[$key] ?? $key;
            $targetValue   = $targetTranslations[$key]   ?? '';

            if ($filter && stripos($key, $filter) === false && stripos($fallbackValue, $filter) === false && stripos($targetValue, $filter) === false) {
                continue;
            }

            $translations[$key] = [
                $fallbackLocale => $fallbackValue,
                $languageCode   => $targetValue,
            ];
        }

        return $translations;
    }

    /**
     * Get group translations from PHP files
     */
    private function getGroupTranslations(
        string $langPath,
        string $languageCode,
        string $fallbackLocale,
        ?string $filter
    ): Collection {
        $targetGroupPath   = "{$langPath}/{$languageCode}";
        $fallbackGroupPath = "{$langPath}/{$fallbackLocale}";

        $groups = new Collection;

        // Get all group files from both directories
        $allGroups = collect();

        if (File::isDirectory($fallbackGroupPath)) {
            foreach (File::files($fallbackGroupPath) as $file) {
                $allGroups->push($file->getFilenameWithoutExtension());
            }
        }

        if (File::isDirectory($targetGroupPath)) {
            foreach (File::files($targetGroupPath) as $file) {
                $allGroups->push($file->getFilenameWithoutExtension());
            }
        }

        $allGroups = $allGroups->unique();

        foreach ($allGroups as $groupName) {
            $fallbackFile = "{$fallbackGroupPath}/{$groupName}.php";
            $targetFile   = "{$targetGroupPath}/{$groupName}.php";

            $fallbackTranslations = File::exists($fallbackFile) ? File::getRequire($fallbackFile) : [];
            $targetTranslations   = File::exists($targetFile) ? File::getRequire($targetFile) : [];

            $mergedTranslations = $this->mergeTranslationsWithLanguages(
                $fallbackTranslations,
                $targetTranslations,
                $fallbackLocale,
                $languageCode,
                $filter
            );

            if (! empty($mergedTranslations)) {
                $groups[$groupName] = $mergedTranslations;
            }
        }

        return $groups;
    }

    /**
     * Merge translations from fallback and target languages
     */
    private function mergeTranslationsWithLanguages(
        array $fallbackArray,
        array $targetArray,
        string $fallbackLocale,
        string $targetLocale,
        ?string $filter,
        string $prefix = ''
    ): array {
        $result = [];

        // Get all keys from both arrays
        $allKeys = array_unique(array_merge(array_keys($fallbackArray), array_keys($targetArray)));

        foreach ($allKeys as $key) {
            $fallbackValue = $fallbackArray[$key] ?? '';
            $targetValue   = $targetArray[$key]   ?? '';
            $fullKey       = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($fallbackValue) || is_array($targetValue)) {
                // Handle nested arrays
                $fallbackNested = is_array($fallbackValue) ? $fallbackValue : [];
                $targetNested   = is_array($targetValue) ? $targetValue : [];

                $nested = $this->mergeTranslationsWithLanguages(
                    $fallbackNested,
                    $targetNested,
                    $fallbackLocale,
                    $targetLocale,
                    $filter,
                    $fullKey
                );

                if (! empty($nested)) {
                    $result[$key] = $nested;
                }
            } else {
                // Handle string values
                if ($filter && stripos($key, $filter) === false && stripos((string) $fallbackValue, $filter) === false && stripos((string) $targetValue, $filter) === false) {
                    continue;
                }

                $result[$key] = [
                    $fallbackLocale => $fallbackValue ?: $key,
                    $targetLocale   => $targetValue,
                ];
            }
        }

        return $result;
    }
}
