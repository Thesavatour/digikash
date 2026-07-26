<?php

namespace App\Services;

use App\Exceptions\NotifyErrorException;
use App\Models\ProjectLicense;
use App\Models\ProjectUpdate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use ZipArchive;

class ProjectUpdaterService
{
    private bool $licenseRefreshed = false;

    public function __construct(private readonly ProjectUpdateServerClient $client) {}

    public function licenseWasRefreshed(): bool
    {
        return $this->licenseRefreshed;
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $latest = ProjectUpdate::query()->latest('checked_at')->latest('id')->first();

        return [
            'license'         => ProjectLicense::current(),
            'latest'          => $latest,
            'history'         => ProjectUpdate::query()->latest('created_at')->limit(10)->get(),
            'checks'          => $this->environmentChecks(),
            'failureCategory' => $latest && $latest->status === 'failed'
                ? $this->classifyInstallError((string) $latest->error_message)
                : null,
        ];
    }

    /**
     * Buckets a raw install error message into one of a handful of known
     * categories so the UI can highlight the matching troubleshooting section.
     */
    public function classifyInstallError(?string $message): ?string
    {
        if (blank($message)) {
            return null;
        }

        $lower = mb_strtolower((string) $message);

        if (str_contains($lower, 'permission denied')
            || str_contains($lower, 'failed to open stream')
            || str_contains($lower, 'not writable')
            || str_contains($lower, 'is not readable')
            || str_contains($lower, 'operation not permitted')) {
            return 'permissions';
        }

        if (str_contains($lower, 'maximum execution time')
            || str_contains($lower, 'execution time of')
            || str_contains($lower, 'timed out')
            || str_contains($lower, 'allowed memory size')) {
            return 'timeout';
        }

        if (str_contains($lower, 'no space left')
            || str_contains($lower, 'enospc')
            || str_contains($lower, 'disk full')
            || str_contains($lower, 'disk space')) {
            return 'disk';
        }

        if (str_contains($lower, 'sqlstate')
            || str_contains($lower, 'pdo')
            || str_contains($lower, 'connection refused')
            || str_contains($lower, 'database')
            || str_contains($lower, 'migration')) {
            return 'database';
        }

        if (str_contains($lower, 'zip')
            || str_contains($lower, 'could not be opened')
            || str_contains($lower, 'could not be unpacked')) {
            return 'zip';
        }

        return 'other';
    }

    public function activate(string $purchaseCode): ProjectLicense
    {
        $data    = $this->client->activate($purchaseCode);
        $license = $data['license'] ?? $data;

        if (! is_array($license)) {
            throw new NotifyErrorException(__('The license activation response was invalid.'));
        }

        $slug   = (string) config('project_updater.product_slug');
        $domain = request()->getHost();

        $values = [
            'item_id'         => (string) ($license['item_id'] ?? config('project_updater.item_id')),
            'purchase_code'   => $purchaseCode,
            'license_token'   => $license['token']           ?? $license['license_token'] ?? null,
            'buyer_username'  => $license['buyer']           ?? $license['buyer_username'] ?? null,
            'status'          => $license['status']          ?? 'active',
            'support_until'   => $license['supported_until'] ?? $license['support_until'] ?? null,
            'activated_at'    => now(),
            'last_checked_at' => now(),
            'metadata'        => $license,
        ];

        $model = ProjectLicense::query()
            ->where('product_slug', $slug)
            ->where('domain', $domain)
            ->first();

        if ($model) {
            // Wipe the existing encrypted purchase_code from the model's raw
            // attributes (and sync the original) BEFORE filling new values, so
            // save()'s dirty-check doesn't try to decrypt a stored cipher that
            // was written under a rotated/lost APP_KEY. Without this, the cast
            // throws DecryptException("MAC is invalid") during isDirty().
            $rawAttributes                  = $model->getAttributes();
            $rawAttributes['purchase_code'] = null;
            $model->setRawAttributes($rawAttributes, sync: true);

            $model->fill($values);
            $model->save();

            return $model;
        }

        return ProjectLicense::query()->create(array_merge($values, [
            'product_slug' => $slug,
            'domain'       => $domain,
        ]));
    }

    public function checkForUpdates(): ProjectUpdate
    {
        $this->licenseRefreshed = false;

        $license = ProjectLicense::current();

        if (! $license?->isActive()) {
            throw new NotifyErrorException(__('Activate the project license before checking for updates.'));
        }

        // If the local token is missing entirely, refresh it up-front using the stored
        // purchase code so the user does not have to manually re-activate.
        $storedPurchaseCode = $license->safePurchaseCode();

        if (blank($license->license_token) && filled($storedPurchaseCode)) {
            $license                = $this->activate($storedPurchaseCode);
            $storedPurchaseCode     = $license->safePurchaseCode();
            $this->licenseRefreshed = true;
        }

        try {
            $data = $this->client->check($license->license_token);
        } catch (NotifyErrorException $e) {
            // The server rejected the stored token. If we have the original purchase
            // code we can transparently refresh and retry once — most token-mismatch
            // errors are caused by the licensing server rotating or losing the token.
            if (! $this->isLicenseTokenError($e->getMessage())) {
                throw $e;
            }

            if (blank($storedPurchaseCode)) {
                // Differentiate "no purchase code stored" from "purchase code can't be
                // decrypted" so the operator knows whether to dig out their Envato
                // code or to investigate APP_KEY rotation.
                $rawPurchaseCode = $license->getRawOriginal('purchase_code');

                throw new NotifyErrorException(filled($rawPurchaseCode)
                    ? __('The license token is invalid and the stored purchase code could not be decrypted (the application encryption key may have changed). Please re-activate the license with your Envato purchase code.')
                    : __('The license token is no longer valid and cannot be refreshed automatically. Please re-activate the license with your purchase code.'));
            }

            try {
                $license = $this->activate($storedPurchaseCode);
            } catch (NotifyErrorException $refreshError) {
                throw new NotifyErrorException(__(
                    'Update check failed (:original) and license refresh also failed (:refresh).',
                    ['original' => $e->getMessage(), 'refresh' => $refreshError->getMessage()]
                ));
            }

            $this->licenseRefreshed = true;
            $data                   = $this->client->check($license->license_token);
        }

        $update = $data['update'] ?? $data;

        if (! is_array($update)) {
            throw new NotifyErrorException(__('The update check response was invalid.'));
        }

        $license->forceFill(['last_checked_at' => now()])->save();

        return ProjectUpdate::query()->updateOrCreate(
            [
                'version' => (string) ($update['version'] ?? config('app.version')),
                'channel' => (string) ($update['channel'] ?? config('project_updater.channel')),
            ],
            [
                'status' => version_compare((string) ($update['version'] ?? config('app.version')), (string) config('app.version'), '>')
                    ? 'available'
                    : 'current',
                'package_url'   => $update['package_url']  ?? null,
                'checksum'      => $update['checksum']     ?? null,
                'signature'     => $update['signature']    ?? null,
                'changelog'     => $update['changelog']    ?? [],
                'requirements'  => $update['requirements'] ?? [],
                'release_date'  => $update['release_date'] ?? null,
                'checked_at'    => now(),
                'metadata'      => $update,
                'error_message' => null,
            ]
        );
    }

    public function refreshLicense(): ProjectLicense
    {
        $license = ProjectLicense::current();

        if (! $license) {
            throw new NotifyErrorException(__('No license is currently activated.'));
        }

        $purchaseCode = $license->safePurchaseCode();

        if (blank($purchaseCode)) {
            $rawPurchaseCode = $license->getRawOriginal('purchase_code');

            throw new NotifyErrorException(filled($rawPurchaseCode)
                ? __('Cannot refresh the license because the stored purchase code could not be decrypted (the application encryption key may have changed). Please re-activate the license with your Envato purchase code.')
                : __('Cannot refresh the license because the original purchase code is not stored. Please activate the license again.'));
        }

        $refreshed              = $this->activate($purchaseCode);
        $this->licenseRefreshed = true;

        return $refreshed;
    }

    /**
     * Rewrite the 'version' line in config/project_updater.php so the next
     * request reads the freshly-installed version. Idempotent: a no-op if the
     * file is missing, unwritable, or already on the right version.
     */
    public function writeInstalledVersionToConfig(string $version): void
    {
        $version    = trim($version);
        $configPath = config_path('project_updater.php');

        if ($version === '' || ! File::exists($configPath) || ! File::isWritable($configPath)) {
            return;
        }

        $original = (string) File::get($configPath);

        // Replace only the first top-level `'version' => '...'` to avoid
        // touching unrelated version strings further down in the file.
        $pattern = "/('version'\s*=>\s*)'(?:\\\\.|[^'\\\\])*'/u";

        $replaced = preg_replace(
            $pattern,
            '$1'."'".addcslashes($version, "\\'")."'",
            $original,
            1,
            $count
        );

        if ($replaced === null || $count === 0 || $replaced === $original) {
            return;
        }

        File::put($configPath, $replaced);
    }

    private function isLicenseTokenError(string $message): bool
    {
        $lower = mb_strtolower($message);

        foreach ([
            'unknown license token',
            'invalid license token',
            'license token',
            'license_token',
            'unknown token',
            'invalid token',
            'token expired',
            'token invalid',
            'token not found',
            'token mismatch',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function install(ProjectUpdate $update, bool $includeSystemBackup = false): ProjectUpdate
    {
        if (! (bool) config('project_updater.install_enabled', true)) {
            throw new NotifyErrorException(__('Project update installation is disabled by configuration.'));
        }

        if (! $update->isInstallable()) {
            throw new NotifyErrorException(__('This update cannot be installed from its current status.'));
        }

        if (blank($update->package_url) || blank($update->checksum)) {
            throw new NotifyErrorException(__('The update package URL and checksum are required.'));
        }

        // A real install (download + ZIP extract + db dump + storage zip + file
        // copy + migrations) easily exceeds PHP's default 30s max_execution_time.
        // Disable it for this request so the script isn't killed mid-update, and
        // ignore the user-abort signal so a closed browser doesn't leave the
        // application half-updated.
        @set_time_limit(0);
        @ignore_user_abort(true);

        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        try {
            // Pre-flight: confirm the web user can actually write to the key
            // application directories BEFORE we download / extract / back up.
            // Otherwise the install fails halfway through the copy phase with
            // a cryptic "copy(...): Failed to open stream: Permission denied"
            // after we've already wasted disk space and time.
            $this->assertWritablePaths();

            $packagePath = $this->downloadPackage($update);
            $this->verifyPackage($packagePath, (string) $update->checksum, $update->signature);

            $extractPath = $this->extractPackage($packagePath, $update);
            $manifest    = $this->readManifest($extractPath);
            $backupPath  = $this->backupManifestFiles($manifest, $update);

            if ($includeSystemBackup) {
                $this->createDatabaseAndStorageBackup($update, $backupPath);
            }

            Artisan::call('down');
            $this->copyManifestFiles($extractPath, $manifest);
            Artisan::call('migrate', ['--force' => true]);

            // Ensure the locally-stored version string reflects what we just
            // installed. config('app.version') reads from
            // config/project_updater.php's 'version' key — if the update
            // package didn't refresh that file (or shipped without it), the UI
            // would still show the old version. We rewrite the line explicitly
            // so the displayed version is always correct after install.
            $this->writeInstalledVersionToConfig((string) $update->version);

            Artisan::call('optimize:clear');

            $update->forceFill([
                'status'        => 'installed',
                'package_path'  => $packagePath,
                'backup_path'   => $backupPath,
                'installed_at'  => now(),
                'error_message' => null,
            ])->save();
        } catch (\Throwable $e) {
            $update->forceFill([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e instanceof NotifyErrorException
                ? $e
                : new NotifyErrorException(__('Project update failed: :message', ['message' => $e->getMessage()]));
        } finally {
            if (app()->isDownForMaintenance()) {
                try {
                    Artisan::call('up');
                } catch (\Throwable) {
                    // Don't mask the original failure if lifting maintenance mode fails.
                }
            }
        }

        return $update->refresh();
    }

    /**
     * @return array<int, array{label: string, status: bool, help: string, value: string}>
     */
    public function environmentChecks(): array
    {
        $diskFreeBytes = $this->diskFreeBytes();
        $minDiskBytes  = 500 * 1024 * 1024;

        return [
            [
                'label'  => __('PHP version'),
                'status' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'help'   => __('PHP 8.2 or higher is required to run Digikash.'),
                'value'  => PHP_VERSION,
            ],
            [
                'label'  => __('ZIP extension'),
                'status' => class_exists(ZipArchive::class),
                'help'   => __('Required to extract update packages.'),
                'value'  => class_exists(ZipArchive::class) ? __('enabled') : __('missing'),
            ],
            [
                'label'  => __('Storage writable'),
                'status' => File::isWritable(storage_path('app')),
                'help'   => __('Required to download packages and create backups.'),
                'value'  => File::isWritable(storage_path('app')) ? __('yes') : __('no'),
            ],
            [
                'label'  => __('Updater server'),
                'status' => filled(config('project_updater.server_url')),
                'help'   => __('Configure PROJECT_UPDATER_SERVER_URL on production.'),
                'value'  => filled(config('project_updater.server_url')) ? __('configured') : __('missing'),
            ],
            [
                'label'  => __('Disk space'),
                'status' => $diskFreeBytes === null || $diskFreeBytes >= $minDiskBytes,
                'help'   => __('At least 500 MB free disk space is recommended before installing.'),
                'value'  => $diskFreeBytes === null ? __('unknown') : $this->formatBytes($diskFreeBytes).' '.__('free'),
            ],
            [
                'label'  => __('Database connection'),
                'status' => $this->canConnectToDatabase(),
                'help'   => __('A working database connection is required for migrations.'),
                'value'  => $this->canConnectToDatabase() ? __('ok') : __('unreachable'),
            ],
        ];
    }

    private function diskFreeBytes(): ?float
    {
        try {
            $free = @disk_free_space(base_path());

            return $free === false ? null : (float) $free;
        } catch (\Throwable) {
            return null;
        }
    }

    private function canConnectToDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return number_format($bytes / 1024 ** 3, 1).' GB';
        }

        if ($bytes >= 1024 ** 2) {
            return number_format($bytes / 1024 ** 2, 1).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }

    private function downloadPackage(ProjectUpdate $update): string
    {
        $body = $this->client->download((string) $update->package_url);
        $path = trim((string) config('project_updater.packages_path'), '/').'/'.$update->version.'-'.Str::uuid().'.zip';

        Storage::disk((string) config('project_updater.storage_disk'))->put($path, $body);

        return $path;
    }

    public function verifyPackage(string $packagePath, string $checksum, ?string $signature = null): void
    {
        $absolutePath = Storage::disk((string) config('project_updater.storage_disk'))->path($packagePath);
        $actual       = hash_file('sha256', $absolutePath);

        if (! hash_equals(strtolower($checksum), strtolower((string) $actual))) {
            throw new NotifyErrorException(__('Update package checksum verification failed.'));
        }

        $publicKey = config('project_updater.public_key');

        if (filled($publicKey) && filled($signature)) {
            $verified = openssl_verify($checksum, base64_decode((string) $signature, true) ?: '', (string) $publicKey, OPENSSL_ALGO_SHA256);

            if ($verified !== 1) {
                throw new NotifyErrorException(__('Update package signature verification failed.'));
            }
        }
    }

    private function extractPackage(string $packagePath, ProjectUpdate $update): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new NotifyErrorException(__('The PHP ZIP extension is required to install updates.'));
        }

        $disk                = Storage::disk((string) config('project_updater.storage_disk'));
        $archivePath         = $disk->path($packagePath);
        $extractPath         = trim((string) config('project_updater.extract_path'), '/').'/'.$update->version.'-'.Str::uuid();
        $absoluteExtractPath = $disk->path($extractPath);

        File::ensureDirectoryExists($absoluteExtractPath);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new NotifyErrorException(__('The update package could not be opened.'));
        }

        $zip->extractTo($absoluteExtractPath);
        $zip->close();

        return $extractPath;
    }

    /**
     * @return array{files: list<array{path: string}>}
     */
    private function readManifest(string $extractPath): array
    {
        $manifestPath = Storage::disk((string) config('project_updater.storage_disk'))->path($extractPath.'/manifest.json');

        if (! File::exists($manifestPath)) {
            throw new NotifyErrorException(__('The update package manifest is missing.'));
        }

        $manifest = json_decode((string) File::get($manifestPath), true);

        if (! is_array($manifest) || ! isset($manifest['files']) || ! is_array($manifest['files'])) {
            throw new NotifyErrorException(__('The update package manifest is invalid.'));
        }

        return $manifest;
    }

    /**
     * @param array{files: list<array{path: string}>} $manifest
     */
    private function backupManifestFiles(array $manifest, ProjectUpdate $update): string
    {
        $backupPath = trim((string) config('project_updater.backups_path'), '/').'/'.$update->version.'-'.now()->format('YmdHis');
        $backupRoot = Storage::disk((string) config('project_updater.storage_disk'))->path($backupPath);

        File::ensureDirectoryExists($backupRoot);

        foreach ($manifest['files'] as $file) {
            $relativePath = $this->safeRelativePath((string) ($file['path'] ?? ''));
            $source       = base_path($relativePath);

            if (File::exists($source)) {
                File::ensureDirectoryExists(dirname($backupRoot.'/'.$relativePath));
                File::copy($source, $backupRoot.'/'.$relativePath);
            }
        }

        File::put($backupRoot.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        return $backupPath;
    }

    public function createDatabaseAndStorageBackup(ProjectUpdate $update, ?string $backupPath = null): string
    {
        $backupPath ??= trim((string) config('project_updater.backups_path'), '/').'/'.$update->version.'-'.now()->format('YmdHis');
        $backupRoot = Storage::disk((string) config('project_updater.storage_disk'))->path($backupPath);

        File::ensureDirectoryExists($backupRoot);

        $this->writeDatabaseAndStorageBackup($backupRoot, $update->version);

        return $backupPath;
    }

    /**
     * @return array{path: string, name: string, backup_path: string}
     */
    public function createDownloadableRecoveryBackup(): array
    {
        // Backups (mysqldump + zip of storage/) routinely exceed PHP's default
        // 30s max_execution_time. Lift the limit so the script can finish.
        @set_time_limit(0);
        @ignore_user_abort(true);

        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        $license = ProjectLicense::current();

        if (! $license?->isActive()) {
            throw new NotifyErrorException(__('Activate the project license before downloading a recovery backup.'));
        }

        $version    = (string) config('app.version');
        $stamp      = now()->format('YmdHis');
        $backupPath = trim((string) config('project_updater.backups_path'), '/').'/manual-'.$version.'-'.$stamp;
        $backupRoot = Storage::disk((string) config('project_updater.storage_disk'))->path($backupPath);

        File::ensureDirectoryExists($backupRoot);
        $this->writeDatabaseAndStorageBackup($backupRoot, $version);

        $fileName    = 'digikash-recovery-'.$version.'-'.$stamp.'.zip';
        $archivePath = trim((string) config('project_updater.backups_path'), '/').'/downloads/'.$fileName;
        $absoluteZip = Storage::disk((string) config('project_updater.storage_disk'))->path($archivePath);

        File::ensureDirectoryExists(dirname($absoluteZip));
        $this->archiveBackupDirectory($backupRoot, $absoluteZip);

        return [
            'path'        => $absoluteZip,
            'name'        => $fileName,
            'backup_path' => $backupPath,
        ];
    }

    private function writeDatabaseAndStorageBackup(string $backupRoot, string $version): void
    {
        $this->backupDatabase($backupRoot.'/database.sql');
        $this->backupStorageDirectory($backupRoot.'/storage.zip', $backupRoot);

        File::put($backupRoot.'/restore-note.txt', implode(PHP_EOL, [
            'Digikash update backup',
            'Version: '.$version,
            'Created: '.now()->toDateTimeString(),
            'database.sql contains a database dump.',
            'storage.zip contains the Laravel storage folder, excluding this backup directory.',
        ]));
    }

    private function backupDatabase(string $targetPath): void
    {
        File::ensureDirectoryExists(dirname($targetPath));

        $connection = DB::connection();
        $driver     = $connection->getDriverName();
        $pdo        = $connection->getPdo();
        $tables     = $this->databaseTables($driver);
        $handle     = fopen($targetPath, 'wb');

        if ($handle === false) {
            throw new NotifyErrorException(__('The database backup file could not be created.'));
        }

        fwrite($handle, '-- Digikash database backup'.PHP_EOL);
        fwrite($handle, '-- Generated at: '.now()->toDateTimeString().PHP_EOL.PHP_EOL);

        if ($driver === 'mysql') {
            fwrite($handle, 'SET FOREIGN_KEY_CHECKS=0;'.PHP_EOL.PHP_EOL);
        }

        foreach ($tables as $table) {
            $quotedTable = $this->quoteIdentifier($table, $driver);

            fwrite($handle, 'DROP TABLE IF EXISTS '.$quotedTable.';'.PHP_EOL);
            fwrite($handle, $this->createTableStatement($table, $driver).';'.PHP_EOL.PHP_EOL);
            $this->writeTableRows($handle, $pdo, $table, $driver);
            fwrite($handle, PHP_EOL);
        }

        if ($driver === 'mysql') {
            fwrite($handle, 'SET FOREIGN_KEY_CHECKS=1;'.PHP_EOL);
        }

        fclose($handle);
    }

    /**
     * @return list<string>
     */
    private function databaseTables(string $driver): array
    {
        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
                ->map(fn (object $row): string => (string) $row->name)
                ->values()
                ->all();
        }

        if ($driver === 'mysql') {
            return collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
                ->map(fn (object $row): string => (string) array_values((array) $row)[0])
                ->values()
                ->all();
        }

        throw new NotifyErrorException(__('Database backup is not supported for the active database driver: :driver', ['driver' => $driver]));
    }

    private function createTableStatement(string $table, string $driver): string
    {
        if ($driver === 'sqlite') {
            $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

            return (string) ($row->sql ?? '');
        }

        $row = DB::selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($table, $driver));

        return (string) (array_values((array) $row)[1] ?? '');
    }

    /**
     * @param resource $handle
     */
    private function writeTableRows(mixed $handle, PDO $pdo, string $table, string $driver): void
    {
        $statement = $pdo->query('SELECT * FROM '.$this->quoteIdentifier($table, $driver));

        if ($statement === false) {
            throw new NotifyErrorException(__('The database table could not be read for backup: :table', ['table' => $table]));
        }

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $columns = array_map(fn (string $column): string => $this->quoteIdentifier($column, $driver), array_keys($row));
            $values  = array_map(fn (mixed $value): string => $this->sqlValue($pdo, $value), array_values($row));

            fwrite($handle, 'INSERT INTO '.$this->quoteIdentifier($table, $driver).' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).');'.PHP_EOL);
        }
    }

    private function sqlValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $pdo->quote((string) $value);
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        $quote = $driver === 'mysql' ? '`' : '"';

        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }

    private function backupStorageDirectory(string $targetPath, string $backupRoot): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new NotifyErrorException(__('The PHP ZIP extension is required to create the storage backup.'));
        }

        $zip = new ZipArchive;

        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new NotifyErrorException(__('The storage backup archive could not be created.'));
        }

        $sourceRoot    = $this->normalizedPath(storage_path());
        $excludedRoots = collect([
            $backupRoot,
            $this->storageDiskPath((string) config('project_updater.backups_path')),
            $this->storageDiskPath((string) config('project_updater.packages_path')),
            $this->storageDiskPath((string) config('project_updater.extract_path')),
        ])
            ->filter()
            ->map(fn (string $path): string => $this->normalizedPath($path))
            ->values()
            ->all();

        foreach (File::allFiles(storage_path(), true) as $file) {
            $path = $this->normalizedPath((string) $file->getRealPath());

            if ($this->pathStartsWithAny($path, $excludedRoots)) {
                continue;
            }

            $relativePath = ltrim(Str::after($path, $sourceRoot), '/');

            if ($relativePath !== '') {
                $zip->addFile($path, $relativePath);
            }
        }

        $zip->close();
    }

    private function archiveBackupDirectory(string $backupRoot, string $targetPath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new NotifyErrorException(__('The PHP ZIP extension is required to create the downloadable backup.'));
        }

        $zip = new ZipArchive;

        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new NotifyErrorException(__('The downloadable backup archive could not be created.'));
        }

        $sourceRoot = $this->normalizedPath($backupRoot);

        foreach (File::allFiles($backupRoot, true) as $file) {
            $path         = $this->normalizedPath((string) $file->getRealPath());
            $relativePath = ltrim(Str::after($path, $sourceRoot), '/');

            if ($relativePath !== '') {
                $zip->addFile($path, $relativePath);
            }
        }

        $zip->close();
    }

    private function storageDiskPath(string $path): string
    {
        return Storage::disk((string) config('project_updater.storage_disk'))->path(trim($path, '/'));
    }

    /**
     * @param list<string> $roots
     */
    private function pathStartsWithAny(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($path === $root || str_starts_with($path, $root.'/')) {
                return true;
            }
        }

        return false;
    }

    private function normalizedPath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param array{files: list<array{path: string}>} $manifest
     */
    private function copyManifestFiles(string $extractPath, array $manifest): void
    {
        $disk = Storage::disk((string) config('project_updater.storage_disk'));

        foreach ($manifest['files'] as $file) {
            $relativePath = $this->safeRelativePath((string) ($file['path'] ?? ''));
            $source       = $disk->path($extractPath.'/'.$relativePath);
            $target       = base_path($relativePath);

            if (! File::exists($source)) {
                throw new NotifyErrorException(__('Update file missing from package: :path', ['path' => $relativePath]));
            }

            File::ensureDirectoryExists(dirname($target));

            try {
                File::copy($source, $target);
            } catch (\Throwable $e) {
                // PHP's copy() throws a plain ErrorException with a generic
                // "Failed to open stream: Permission denied" — re-throw with
                // the buyer-facing fix command so they don't have to grep
                // their server logs to figure out what went wrong.
                if (str_contains($e->getMessage(), 'Permission denied')) {
                    throw new NotifyErrorException($this->buildPermissionErrorMessage($target, $relativePath));
                }

                throw $e;
            }
        }
    }

    /**
     * Pre-flight check before download — verify the web user can write to
     * the application's key directories. Bails out with an actionable error
     * message naming the failing path, current PHP user, file owner, and
     * the exact chown command to run.
     */
    private function assertWritablePaths(): void
    {
        $samplePaths = array_filter([
            base_path('app'),
            base_path('bootstrap/cache'),
            base_path('config'),
            base_path('database'),
            base_path('public'),
            base_path('resources'),
            base_path('routes'),
        ], 'is_dir');

        foreach ($samplePaths as $path) {
            if (! is_writable($path)) {
                throw new NotifyErrorException($this->buildPermissionErrorMessage($path, basename($path)));
            }
        }
    }

    /**
     * Build an actionable error message for a permission failure. Surfaces
     * the failing absolute path, who owns it, who PHP runs as, and a copy-
     * pasteable chown command — so the buyer can fix it in one SSH command
     * instead of guessing.
     */
    private function buildPermissionErrorMessage(string $absolutePath, string $relativePath): string
    {
        $owner   = $this->describePosixUser((int) @fileowner($absolutePath));
        $current = function_exists('posix_geteuid')
            ? $this->describePosixUser((int) @posix_geteuid())
            : 'the PHP / web server user';

        $appRoot      = base_path();
        $userForChown = $current !== 'the PHP / web server user' ? $current : 'www-data';

        return __(
            "The update cannot write to :path.\n\n".
            "PHP is running as ':current' but that file is owned by ':owner'. ".
            "Your web server user needs ownership of the application files to install updates.\n\n".
            "Fix on your server (one SSH command):\n".
            "sudo chown -R :user::user :root\n\n".
            'Then click "Install update" again. The update was NOT applied — your site is unchanged.',
            [
                'path'    => $absolutePath,
                'current' => $current,
                'owner'   => $owner,
                'user'    => $userForChown,
                'root'    => $appRoot,
            ]
        );
    }

    /**
     * Resolve a POSIX uid to a human-readable name. Falls back gracefully
     * when posix_* extensions aren't loaded (e.g. on Windows hosts).
     */
    private function describePosixUser(int $uid): string
    {
        if (! function_exists('posix_getpwuid')) {
            return 'uid '.$uid;
        }

        $info = @posix_getpwuid($uid);

        return is_array($info) && isset($info['name'])
            ? (string) $info['name']
            : 'uid '.$uid;
    }

    private function safeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '../')) {
            throw new NotifyErrorException(__('Update package contains an unsafe file path.'));
        }

        foreach (config('project_updater.protected_paths', []) as $protectedPath) {
            $protectedPath = trim((string) $protectedPath, '/');

            if ($path === $protectedPath || str_starts_with($path, $protectedPath.'/')) {
                throw new NotifyErrorException(__('Update package tried to modify a protected path: :path', ['path' => $path]));
            }
        }

        return $path;
    }
}
