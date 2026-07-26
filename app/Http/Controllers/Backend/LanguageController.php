<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\Language\IndexRequest;
use App\Http\Requests\Language\PositionUpdateRequest;
use App\Http\Requests\Language\StoreRequest;
use App\Http\Requests\Language\TranslateRequest;
use App\Http\Requests\Language\UpdateRequest;
use App\Models\Language;
use App\Services\TranslationService;
use App\Traits\FileManageTrait;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class LanguageController extends BaseController
{
    use AuthorizesRequests;
    use FileManageTrait;

    private TranslationService $translation;

    private string $languageFilesPath;

    public function __construct(TranslationService $translation)
    {
        $this->translation       = $translation;
        $this->languageFilesPath = resource_path('lang');
    }

    public static function permissions(): array
    {
        return [
            'index'                                                                         => 'language-list',
            'store'                                                                         => 'language-create',
            'edit|update|destroy|translate|translatedUpdate|syncMissingKeys|positionUpdate' => 'language-manage',
        ];
    }

    /**
     * Display a paginated listing of the Language resource.
     */
    public function index(IndexRequest $request)
    {
        $filters   = $request->validated();
        $search    = $filters['search']    ?? null;
        $status    = $filters['status']    ?? 'all';
        $direction = $filters['direction'] ?? 'all';
        $perPage   = (int) ($filters['per_page'] ?? 10);

        $allLanguages = Language::query()->get();

        $completionReport = $this->translation->completionReport();
        $missingReport    = $this->translation->missingKeysReport();

        $languageMetrics = $allLanguages
            ->mapWithKeys(fn (Language $language): array => [
                $language->code => $completionReport['languages'][$language->code] ?? [
                    'translated' => 0,
                    'total'      => $completionReport['source_total'],
                    'missing'    => $completionReport['source_total'],
                    'percentage' => $completionReport['source_total'] > 0 ? 0 : 100,
                ],
            ]);

        $averageCompletion = $allLanguages->isNotEmpty()
            ? (int) round($allLanguages->avg(fn (Language $language): int => $languageMetrics[$language->code]['percentage'] ?? 0))
            : 0;

        $languages = Language::query()
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('status', true))
            ->when($status === 'inactive', fn ($query) => $query->where('status', false))
            ->when($status === 'default', fn ($query) => $query->where('is_default', true))
            ->when($direction === 'ltr', fn ($query) => $query->where('is_rtl', false))
            ->when($direction === 'rtl', fn ($query) => $query->where('is_rtl', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('backend.languages.index', [
            'languages' => $languages,
            'filters'   => [
                'search'    => $search,
                'status'    => $status,
                'direction' => $direction,
                'per_page'  => $perPage,
            ],
            'languageMetrics' => $languageMetrics,
            'languageStats'   => [
                'total'              => $allLanguages->count(),
                'active'             => $allLanguages->where('status', true)->count(),
                'missing'            => $missingReport['total'],
                'average_completion' => $averageCompletion,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {

        $input = $request->validated();

        if (isset($input['is_default']) && $input['is_default']) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $data = [
            'flag'       => self::uploadImage($input['flag']),
            'name'       => $input['language_name'],
            'code'       => Str::lower($input['language_code']),
            'is_default' => $input['is_default'] ?? 0,
            'is_rtl'     => $input['is_rtl']     ?? 0,
            'status'     => $input['status']     ?? 0,
            'sort_order' => ((int) Language::query()->max('sort_order')) + 1,
        ];

        $this->translation->addLanguage($input['language_code'], $input['language_name']);
        Language::create($data);

        notifyEvs('success', __('Language Added Successfully'));

        return redirect()->route('admin.language.index');
    }

    public function positionUpdate(PositionUpdateRequest $request): JsonResponse
    {
        foreach ((array) $request->validated('positions') as $item) {
            Language::query()
                ->where('id', (int) $item['id'])
                ->update(['sort_order' => (int) $item['order']]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => __('Language order updated successfully.'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @throws Throwable
     */
    public function edit($id)
    {
        $language = Language::find($id);

        return view('backend.languages.edit', compact('language'))->render();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request, $id)
    {
        $input    = $request->validated();
        $language = Language::find($id);

        if (! isset($input['is_default']) && $language->is_default) {
            notifyEvs('error', 'Must have one default language');

            return redirect()->route('admin.language.index');
        }

        if (isset($input['is_default']) && $input['is_default'] !== $language->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        if (! isset($input['status']) && $language->is_default) {
            notifyEvs('error', 'Default language must be active');

            return redirect()->route('admin.language.index');
        }
        $languageCode = $language->code === 'en' ? $language->code : Str::lower($input['language_code']);
        $data         = [
            'name'       => $input['language_name'],
            'code'       => $languageCode,
            'is_default' => $input['is_default'] ?? 0,
            'is_rtl'     => $input['is_rtl']     ?? 0,
            'status'     => $input['status']     ?? 0,
        ];

        if (isset($input['flag'])) {
            $data['flag'] = self::uploadImage($input['flag'], $language->flag);
        }

        // Extract filename and directory name using pathinfo()
        $filePathInfo   = pathinfo("{$this->languageFilesPath}/{$language->code}.json");
        $folderPathInfo = pathinfo("{$this->languageFilesPath}/{$language->code}");

        // Build new file and directory paths
        $newFileName   = "{$this->languageFilesPath}/{$languageCode}.json";
        $newFolderName = "{$this->languageFilesPath}/{$languageCode}";

        // Rename both file and directory
        rename($filePathInfo['dirname'].'/'.$filePathInfo['basename'], $newFileName);
        rename($folderPathInfo['dirname'].'/'.$folderPathInfo['basename'], $newFolderName);

        $language->update($data);

        notifyEvs('success', 'Language Updated Successfully');

        return redirect()->route('admin.language.index');

    }

    public function translate(TranslateRequest $request, $languageCode)
    {
        $input = $request->validated();

        if ($request->has('language') && $request->get('language') !== $languageCode) {
            return redirect()
                ->route('admin.language.translate', ['code' => $request->get('language'), 'group' => $request->get('group'), 'filter' => $request->get('filter')]);
        }

        $languages = $this->translation->allLanguages();

        $groups = $this->translation->getGroupsFor(config('app.fallback_locale'))->merge('single');

        $perPage = (int) ($input['per_page'] ?? 50);
        $page    = (int) ($input['page'] ?? 1);

        $translationPage = $this->translation->paginatedRowsFor(
            $languageCode,
            $input['filter'] ?? null,
            $input['group']  ?? null,
            $page,
            $perPage
        );

        return view('backend.languages.translate', [
            'languageCode'     => $languageCode,
            'languages'        => $languages,
            'groups'           => $groups,
            'translationRows'  => $translationPage['rows'],
            'translationStats' => [
                'total'      => $translationPage['total'],
                'missing'    => $translationPage['missing'],
                'translated' => $translationPage['translated'],
            ],
            'translateFilters' => [
                'filter'   => $input['filter'] ?? null,
                'group'    => $input['group']  ?? null,
                'per_page' => $perPage,
            ],
        ]);

    }

    public function translatedUpdate(Request $request)
    {

        $keyGroup           = $request->group;
        $language           = $request->language;
        $isGroupTranslation = ! Str::contains($keyGroup, 'single');

        $this->translation->add($request, $language, $isGroupTranslation);

        notifyEvs('success', __('Keyword Updated Successfully'));

        return redirect()->back();
    }

    /**
     * Synchronise translation keys that exist in the source language but are
     * missing from the others.
     *
     * For AJAX/JSON callers a structured report is returned so the UI can show
     * an understandable progress summary; classic requests fall back to a flash
     * message and redirect for progressive enhancement.
     */
    public function syncMissingKeys(Request $request): JsonResponse|RedirectResponse
    {
        $report = $this->translation->missingKeysReport();

        Artisan::call('translation:sync-missing-translation-keys');

        if ($request->expectsJson() || $request->ajax()) {
            $names = Language::pluck('name', 'code');

            $languages = (new Collection($report['languages']))
                ->map(fn (int $added, string $code): array => [
                    'code'  => $code,
                    'name'  => $names[$code] ?? Str::upper($code),
                    'added' => $added,
                ])
                ->values();

            return response()->json([
                'success'   => true,
                'message'   => __('Translation Keys Synced Successfully'),
                'total'     => $report['total'],
                'languages' => $languages,
            ]);
        }

        notifyEvs('success', __('Translation Keys Synced Successfully'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $language = Language::find($id);

        try {
            $this->authorize('delete', $language);

            $languageCode          = $language->code;
            $languageFilePath      = "{$this->languageFilesPath}/$languageCode.json";
            $languageDirectoryPath = "{$this->languageFilesPath}/$languageCode";

            // Delete the language file and directory
            File::deleteDirectory($languageDirectoryPath);
            File::delete($languageFilePath);

            // Delete the language record from the database
            self::delete($language->flag);
            $language->delete();

            $keys = [
                'home_page_components',
                'header_navigations',
                'footer_navigations',
                'services',
                'blogs',
                'languages',
                'languagesPluck',
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }
            notifyEvs('success', 'Language Deleted Successfully');

            return redirect()->route('admin.language.index');

        } catch (Exception $e) {
            notifyEvs('error', $e->getMessage());

            return redirect()->back();
        }

    }
}
