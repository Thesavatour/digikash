<?php

namespace App\Http\Controllers\Backend;

use App\Exceptions\NotifyErrorException;
use App\Http\Requests\Backend\ActivateProjectLicenseRequest;
use App\Http\Requests\Backend\DownloadProjectBackupRequest;
use App\Http\Requests\Backend\InstallProjectUpdateRequest;
use App\Models\ProjectUpdate;
use App\Services\ProjectUpdaterService;
use App\Support\LicenseGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectUpdaterController extends BaseController
{
    public function __construct(
        private readonly ProjectUpdaterService $updater,
        private readonly LicenseGuard $licenseGuard,
    ) {}

    public static function permissions(): array
    {
        return [
            'index|check'                                    => 'project-updater-view',
            'activate|install|backupDownload|refreshLicense' => 'project-updater-manage',
        ];
    }

    public function index(): View
    {
        return view('backend.app.updater', $this->updater->overview());
    }

    public function activate(ActivateProjectLicenseRequest $request): RedirectResponse
    {
        try {
            $license = $this->updater->activate((string) $request->validated('purchase_code'));

            if ($license->isActive()) {
                // Clear the runtime guard immediately so the "needs re-activation"
                // banner disappears at once instead of after the next recheck.
                $this->licenseGuard->markValidated();
            }

            notifyEvs('success', __('Project license activated successfully.'));
        } catch (NotifyErrorException $e) {
            notifyEvs('error', $e->getMessage());
        }

        return redirect()->route('admin.app.updater.index');
    }

    public function check(): RedirectResponse
    {
        try {
            $update = $this->updater->checkForUpdates();

            // A successful update check is a server-confirmed license signal,
            // so clear any stale runtime-guard banner.
            $this->licenseGuard->markValidated();

            if ($this->updater->licenseWasRefreshed()) {
                notifyEvs('info', __('Your license token was refreshed automatically before the update check.'));
            }

            notifyEvs(
                $update->status === 'available' ? 'success' : 'info',
                $update->status === 'available'
                    ? __('Version :version is available.', ['version' => $update->version])
                    : __('Your application is already up to date.')
            );
        } catch (NotifyErrorException $e) {
            notifyEvs('error', $e->getMessage());
        }

        return redirect()->route('admin.app.updater.index');
    }

    public function refreshLicense(): RedirectResponse
    {
        try {
            $license = $this->updater->refreshLicense();

            if ($license->isActive()) {
                $this->licenseGuard->markValidated();
            }

            notifyEvs('success', __('License token refreshed successfully.'));
        } catch (NotifyErrorException $e) {
            notifyEvs('error', $e->getMessage());
        }

        return redirect()->route('admin.app.updater.index');
    }

    public function install(InstallProjectUpdateRequest $request, ProjectUpdate $update): RedirectResponse|JsonResponse
    {
        try {
            $this->updater->install($update, $request->boolean('backup_database_storage'));

            session()->flash('updater_just_installed', $update->version);
            notifyEvs('success', __('Project update installed successfully.'));

            if ($request->expectsJson()) {
                return response()->json([
                    'success'  => true,
                    'version'  => $update->version,
                    'redirect' => route('admin.app.updater.index'),
                ]);
            }
        } catch (NotifyErrorException $e) {
            // For AJAX, the JS shows the toast directly. For full-page submits,
            // flash the error so the redirect surfaces it on next render.
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            notifyEvs('error', $e->getMessage());
        }

        return redirect()->route('admin.app.updater.index');
    }

    public function backupDownload(DownloadProjectBackupRequest $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $backup = $this->updater->createDownloadableRecoveryBackup();

            notifyEvs('success', __('Recovery backup is ready to download.'));

            return response()->download($backup['path'], $backup['name']);
        } catch (NotifyErrorException $e) {
            notifyEvs('error', $e->getMessage());
        }

        return redirect()->route('admin.app.updater.index');
    }
}
