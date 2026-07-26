{{--
    Server Troubleshooting modal — referenced from updater.blade.php.
    All copy is plain English + commands. Each section is collapsible and can
    be auto-opened from JS via data-open-category="permissions|timeout|disk|database|zip".
--}}
<div class="pu-overlay" id="pu-help-modal" data-open="false">
    <div class="pu-modal pu-modal--lg project-updater" data-state="modal" role="dialog" aria-labelledby="pu-help-modal-title">
        <header class="pu-modal__head">
            <h2 class="pu-modal__title" id="pu-help-modal-title">
                <i class="fas fa-life-ring" style="margin-right:8px; color:var(--pu-primary);"></i>
                {{ __('Server Troubleshooting') }}
            </h2>
            <p class="pu-modal__sub">
                {{ __('Step-by-step server fixes for the most common update failures. Run each command on the server hosting Digikash (via SSH), not in the admin panel.') }}
            </p>
        </header>

        <div class="pu-modal__body pu-help">

            {{-- ── 1. cPanel-specific permissions fix (most common host) ─ --}}
            <section class="pu-help-section" data-category="permissions" data-open="true">
                <button type="button" class="pu-help-section__head" data-action="toggle-help-section">
                    <span>
                        <i class="fas fa-chevron-right pu-help-section__chevron"></i>
                        <i class="fas fa-server" style="color:var(--pu-danger); margin: 0 8px 0 6px;"></i>
                        {{ __('Permission denied — cPanel / shared hosting') }}
                    </span>
                    <span class="pu-pill pu-pill--danger">{{ __('Most cPanel hosts') }}</span>
                </button>
                <div class="pu-help-section__body">
                    <p class="pu-help__lead">
                        {{ __('On cPanel-based hosting, PHP runs as your cPanel account user (e.g.') }}
                        <code>myuser</code>{{ __('), and every file must be owned by that user with safe permissions. The error usually appears after a backup restore, an FTP upload from another machine, or after the host\'s nightly security scan resets ownership. Pick the option that matches your access level:') }}
                    </p>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">
                            <i class="fas fa-folder-open" style="color:var(--pu-primary); margin-right:6px;"></i>
                            {{ __('Option A — Fix from cPanel File Manager (no SSH needed)') }}
                        </div>
                        <div class="pu-help__step-desc">
                            {{ __('This is the easiest path if you only have cPanel access. Most shared hosting customers will use this.') }}
                        </div>
                        <ol class="pu-help__list">
                            <li>{{ __('Log into your cPanel control panel.') }}</li>
                            <li>{{ __('Open') }} <strong>{{ __('Files → File Manager') }}</strong>.</li>
                            <li>{{ __('Navigate to your Digikash install directory — usually') }} <code>public_html/</code> {{ __('or') }} <code>public_html/digikash/</code>.</li>
                            <li>{{ __('Click') }} <strong>{{ __('Select All') }}</strong> {{ __('(top toolbar).') }}</li>
                            <li>{{ __('Click') }} <strong>{{ __('Permissions') }}</strong> {{ __('in the toolbar.') }}</li>
                            <li>{{ __('Set permissions to') }} <strong><code>0644</code></strong>{{ __(', check') }} ☑ <strong>{{ __('Recurse into subdirectories') }}</strong> {{ __('and') }} ☑ <strong>{{ __('Apply only to files') }}</strong>{{ __('. Click') }} <strong>{{ __('Change Permissions') }}</strong>.</li>
                            <li>{{ __('Click') }} <strong>{{ __('Select All') }}</strong> {{ __('again, then') }} <strong>{{ __('Permissions') }}</strong>.</li>
                            <li>{{ __('Set permissions to') }} <strong><code>0755</code></strong>{{ __(', check') }} ☑ <strong>{{ __('Recurse') }}</strong> {{ __('and') }} ☑ <strong>{{ __('Apply only to directories') }}</strong>{{ __('. Click') }} <strong>{{ __('Change Permissions') }}</strong>.</li>
                            <li>{{ __('Open the') }} <code>storage/</code> {{ __('folder, select all, set to') }} <code>0755</code> {{ __('with') }} ☑ <strong>{{ __('Recurse') }}</strong>{{ __('. Repeat for') }} <code>bootstrap/cache/</code>.</li>
                            <li>{{ __('Close File Manager, refresh this admin page, and click') }} <strong>{{ __('Retry install') }}</strong>.</li>
                        </ol>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">
                            <i class="fas fa-terminal" style="color:var(--pu-primary); margin-right:6px;"></i>
                            {{ __('Option B — Fix from cPanel Terminal (if your host enables it)') }}
                        </div>
                        <div class="pu-help__step-desc">
                            {{ __('cPanel > Advanced > Terminal. Then paste these commands one at a time. Replace') }} <code>~/public_html</code> {{ __('with your actual install path if Digikash sits in a subfolder:') }}
                        </div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd ~/public_html
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 755 storage bootstrap/cache
php artisan optimize:clear</pre>
                        <div class="pu-help__callout pu-help__callout--warning" style="margin-top:10px;">
                            <strong><i class="fas fa-circle-exclamation"></i> {{ __('Do NOT use sudo on cPanel') }}</strong>
                            <p>
                                {{ __('You don\'t have root access on shared hosting — the commands above run as your account user, which is exactly what PHP needs. Adding') }} <code>sudo</code> {{ __('will fail with "command not allowed".') }}
                            </p>
                        </div>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">
                            <i class="fas fa-key" style="color:var(--pu-primary); margin-right:6px;"></i>
                            {{ __('Option C — Fix from SSH (if your host enables SSH access)') }}
                        </div>
                        <div class="pu-help__step-desc">
                            {{ __('cPanel > Advanced > SSH Access. Upload your public key, then from your local terminal:') }}
                        </div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>ssh myuser@yourdomain.com -p 22
cd ~/public_html
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 755 storage bootstrap/cache
php artisan optimize:clear
exit</pre>
                        <p class="pu-help__hint">
                            {{ __('Some cPanel hosts use a non-standard SSH port (often 2222 or 21098). Check the SSH Access tab in cPanel for the exact port.') }}
                        </p>
                    </div>

                    <div class="pu-help__callout pu-help__callout--info">
                        <strong><i class="fas fa-circle-info"></i> {{ __('Still failing? File ownership is probably wrong') }}</strong>
                        <p>
                            {{ __('After a backup restore or upload from another host, ownership often gets reset to') }} <code>nobody</code>{{ __(' or') }} <code>root</code> {{ __('instead of your cPanel user. Verify in Terminal:') }}
                        </p>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>ls -la ~/public_html | head -10</pre>
                        <p class="pu-help__hint" style="margin-top:8px;">
                            {{ __('The owner column (3rd) should show YOUR cPanel username — not') }} <code>nobody</code>, <code>root</code>{{ __(', or') }} <code>apache</code>{{ __('. If it\'s wrong, you cannot fix it yourself on shared hosting. Two paths:') }}
                        </p>
                        <ol class="pu-help__list" style="margin-top:6px;">
                            <li>
                                {{ __('Open a support ticket with your host and ask them to run:') }}
                                <pre class="pu-help-code" style="margin-top:6px;"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>chown -R myuser:myuser /home/myuser/public_html</pre>
                                {{ __('(Replace') }} <code>myuser</code> {{ __('with your real cPanel username — they\'ll know it.)') }}
                            </li>
                            <li>
                                {{ __('Or check cPanel for a built-in tool: search the cPanel home for') }} <strong>"Fix File Ownership"</strong>, <strong>"File Permission Fixer"</strong>{{ __(', or') }} <strong>"Restore Permissions"</strong>{{ __('. Many hosts include it under') }} <em>{{ __('Files') }}</em> {{ __('or') }} <em>{{ __('Advanced') }}</em>{{ __('. Click → Run.') }}
                            </li>
                        </ol>
                    </div>

                    <div class="pu-help__callout pu-help__callout--warning">
                        <strong><i class="fas fa-lightbulb"></i> {{ __('LiteSpeed / suPHP / CloudLinux notes') }}</strong>
                        <p>
                            {{ __('Most cPanel hosts run') }} <strong>LiteSpeed</strong> {{ __('or') }} <strong>Apache + suPHP</strong> {{ __('with CloudLinux\'s CageFS — all of these use the') }} <em>{{ __('single-user model') }}</em>{{ __(': PHP runs as your account user, never as') }} <code>www-data</code>{{ __(' or') }} <code>nobody</code>{{ __('. That means the standard') }} <code>chown www-data:www-data</code> {{ __('advice from VPS tutorials does NOT apply — use your cPanel username instead. If you see "Permission denied" even with') }} <code>755</code> {{ __('directories, the underlying cause is almost always ownership, not mode bits.') }}
                        </p>
                    </div>
                </div>
            </section>

            {{-- ── 2. Permission denied — VPS / root SSH server ────────── --}}
            <section class="pu-help-section" data-category="permissions">
                <button type="button" class="pu-help-section__head" data-action="toggle-help-section">
                    <span>
                        <i class="fas fa-chevron-right pu-help-section__chevron"></i>
                        <i class="fas fa-lock" style="color:var(--pu-danger); margin: 0 8px 0 6px;"></i>
                        {{ __('Permission denied — VPS / dedicated server (root SSH)') }}
                    </span>
                </button>
                <div class="pu-help-section__body">
                    <p class="pu-help__lead">
                        {{ __('Use this section if you manage your own VPS, dedicated server, or DigitalOcean / Vultr / Linode-style box with root SSH access. On these setups PHP runs as a system user like') }}
                        <code>www-data</code>, <code>nginx</code>, {{ __('or') }} <code>apache</code>{{ __(' — different from your deploy user — and you need') }} <code>sudo</code> {{ __('to fix it.') }}
                    </p>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">1. {{ __('Find which user PHP runs as') }}</div>
                        <div class="pu-help__step-desc">
                            {{ __('SSH into the server and run:') }}
                        </div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>ps aux | grep -E 'php-fpm|nginx|apache' | grep -v grep | head -5</pre>
                        <p class="pu-help__hint">
                            {{ __('Look at the first column — that is the user PHP runs as. Common values:') }}
                            <code>www-data</code> {{ __('(Ubuntu/Debian),') }} <code>nginx</code> {{ __('(many distros),') }} <code>apache</code>
                            {{ __('(CentOS),') }} <code>forge</code> {{ __('(Laravel Forge),') }} <code>ploi</code> {{ __('(Ploi).') }}
                        </p>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">2. {{ __('Give that user ownership of the project') }}</div>
                        <div class="pu-help__step-desc">
                            {{ __('Replace') }} <code>www-data</code> {{ __('with the user from step 1, and') }} <code>/var/www/digikash</code>
                            {{ __('with your real project path:') }}
                        </div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo chown -R www-data:www-data /var/www/digikash</pre>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">3. {{ __('Reset safe file and directory permissions') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd /var/www/digikash
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;
sudo chmod -R 775 storage bootstrap/cache</pre>
                        <p class="pu-help__hint">
                            <code>644</code> {{ __('= readable by everyone, writable by owner.') }}
                            <code>755</code> {{ __('= traversable directory.') }}
                            <code>775</code> {{ __('= group-writable (needed for Laravel\'s logs/cache).') }}
                        </p>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">4. {{ __('Clear Laravel caches and retry') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd /var/www/digikash
sudo -u www-data php artisan optimize:clear</pre>
                        <p class="pu-help__hint">
                            {{ __('Then refresh this page and click') }} <strong>{{ __('Retry install') }}</strong>.
                        </p>
                    </div>

                    <div class="pu-help__callout pu-help__callout--info">
                        <strong><i class="fas fa-info-circle"></i> {{ __('Managed hosting (Forge / Ploi / Cloudways / RunCloud)') }}</strong>
                        <p>
                            {{ __('These panels run PHP as your deploy user (often') }} <code>forge</code> {{ __('or') }} <code>ploi</code>{{ __('). Use that username in step 2 instead of') }} <code>www-data</code>{{ __(', and replace') }} <code>/var/www/digikash</code> {{ __('with the actual path shown in your panel — usually') }} <code>/home/forge/your-site.com</code>.
                        </p>
                    </div>

                    <div class="pu-help__callout pu-help__callout--warning">
                        <strong><i class="fas fa-shield-check"></i> {{ __('SELinux (CentOS / RHEL / Fedora / AlmaLinux)') }}</strong>
                        <p>
                            {{ __('If permissions look correct but writes still fail, SELinux may be blocking them. Test by running:') }}
                        </p>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo setenforce 0</pre>
                        <p class="pu-help__hint">
                            {{ __('If the update now succeeds, configure SELinux properly instead of leaving it permissive:') }}
                        </p>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo chcon -R -t httpd_sys_rw_content_t /var/www/digikash
sudo setenforce 1</pre>
                    </div>
                </div>
            </section>

            {{-- ── 2. Timeout / memory ─────────────────────────────────── --}}
            <section class="pu-help-section" data-category="timeout">
                <button type="button" class="pu-help-section__head" data-action="toggle-help-section">
                    <span>
                        <i class="fas fa-chevron-right pu-help-section__chevron"></i>
                        <i class="fas fa-clock" style="color:var(--pu-warning); margin: 0 8px 0 6px;"></i>
                        {{ __('"Maximum execution time exceeded" / out of memory') }}
                    </span>
                </button>
                <div class="pu-help-section__body">
                    <p class="pu-help__lead">
                        {{ __('PHP killed the install before it could finish. Updates routinely take 60+ seconds; the PHP default is 30. Raise the limit in') }} <code>php.ini</code>.
                    </p>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('1. Edit the FPM php.ini for your PHP version') }}</div>
                        <p class="pu-help__hint">{{ __('Adjust the version (8.3 here) to match what you run:') }}</p>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo nano /etc/php/8.3/fpm/php.ini</pre>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('2. Set these values') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M</pre>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('3. Reload PHP-FPM and the web server') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx</pre>
                        <p class="pu-help__hint">
                            {{ __('If you use Apache instead, edit') }} <code>/etc/php/8.3/apache2/php.ini</code> {{ __('and reload Apache:') }} <code>sudo systemctl reload apache2</code>.
                        </p>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('4. Verify the new limit took effect') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>php -r "echo ini_get('max_execution_time'), PHP_EOL;"</pre>
                        <p class="pu-help__hint">
                            {{ __('You should see') }} <code>300</code>{{ __('. If it still says') }} <code>30</code>{{ __(', the FPM service did not reload — try a full restart:') }}
                            <code>sudo systemctl restart php8.3-fpm</code>.
                        </p>
                    </div>
                </div>
            </section>

            {{-- ── 3. Disk space ───────────────────────────────────────── --}}
            <section class="pu-help-section" data-category="disk">
                <button type="button" class="pu-help-section__head" data-action="toggle-help-section">
                    <span>
                        <i class="fas fa-chevron-right pu-help-section__chevron"></i>
                        <i class="fas fa-database" style="color:var(--pu-warning); margin: 0 8px 0 6px;"></i>
                        {{ __('"No space left on device"') }}
                    </span>
                </button>
                <div class="pu-help-section__body">
                    <p class="pu-help__lead">
                        {{ __('Updates need ~500 MB free for the download, backup ZIP, and extracted files. Free up some space before retrying.') }}
                    </p>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('1. Check available space') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>df -h /var/www
df -h /tmp</pre>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('2. Clear old Laravel logs and update artifacts') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd /var/www/digikash
sudo find storage/logs -name "*.log" -mtime +7 -delete
sudo rm -rf storage/app/updates/packages/* storage/app/updates/extracted/*</pre>
                        <p class="pu-help__hint">
                            {{ __('This removes log files older than 7 days plus leftover update packages from previous runs. Recovery backups under') }} <code>storage/app/updates/backups/</code> {{ __('are preserved — delete them manually if you need more space.') }}
                        </p>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('3. Free system-level caches') }}</div>
                        <p class="pu-help__hint">{{ __('Ubuntu / Debian:') }}</p>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo apt clean
sudo journalctl --vacuum-time=7d</pre>
                    </div>
                </div>
            </section>

            {{-- ── 4. Database / migrations ────────────────────────────── --}}
            <section class="pu-help-section" data-category="database">
                <button type="button" class="pu-help-section__head" data-action="toggle-help-section">
                    <span>
                        <i class="fas fa-chevron-right pu-help-section__chevron"></i>
                        <i class="fas fa-server" style="color:var(--pu-info); margin: 0 8px 0 6px;"></i>
                        {{ __('Database connection or migration failed') }}
                    </span>
                </button>
                <div class="pu-help-section__body">
                    <p class="pu-help__lead">
                        {{ __('Either the database is unreachable, or a migration in the new version conflicts with your current schema.') }}
                    </p>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('1. Confirm the database is reachable from the app') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd /var/www/digikash
php artisan tinker --execute="DB::connection()->getPdo() ? print('OK') : print('FAIL');"</pre>
                        <p class="pu-help__hint">
                            {{ __('If this prints anything other than') }} <code>OK</code>{{ __(', fix your database credentials in') }} <code>.env</code> {{ __('(') }}<code>DB_HOST</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>{{ __(') and try again.') }}
                        </p>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('2. Preview what migrations would run') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd /var/www/digikash
php artisan migrate --force --pretend</pre>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('3. Run migrations manually') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd /var/www/digikash
php artisan migrate --force</pre>
                        <p class="pu-help__hint">
                            {{ __('If a specific migration fails, restore your database from the recovery backup ZIP first, then contact support with the exact error.') }}
                        </p>
                    </div>
                </div>
            </section>

            {{-- ── 5. ZIP extension ────────────────────────────────────── --}}
            <section class="pu-help-section" data-category="zip">
                <button type="button" class="pu-help-section__head" data-action="toggle-help-section">
                    <span>
                        <i class="fas fa-chevron-right pu-help-section__chevron"></i>
                        <i class="fas fa-file-zipper" style="color:var(--pu-info); margin: 0 8px 0 6px;"></i>
                        {{ __('"ZIP extension is required" / archive could not be opened') }}
                    </span>
                </button>
                <div class="pu-help-section__body">
                    <p class="pu-help__lead">
                        {{ __('PHP\'s ZIP extension is missing, so the updater cannot extract the package archive.') }}
                    </p>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('Ubuntu / Debian') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo apt update
sudo apt install -y php8.3-zip
sudo systemctl reload php8.3-fpm</pre>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('CentOS / RHEL / AlmaLinux') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>sudo dnf install -y php-zip
sudo systemctl reload php-fpm</pre>
                    </div>

                    <div class="pu-help__step">
                        <div class="pu-help__step-title">{{ __('Verify ZIP is loaded') }}</div>
                        <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>php -m | grep -i zip</pre>
                        <p class="pu-help__hint">
                            {{ __('You should see two lines:') }} <code>Zip</code> {{ __('and') }} <code>zlib</code>.
                        </p>
                    </div>
                </div>
            </section>

            {{-- ── 6. Still stuck ──────────────────────────────────────── --}}
            <section class="pu-help-section" data-category="other">
                <button type="button" class="pu-help-section__head" data-action="toggle-help-section">
                    <span>
                        <i class="fas fa-chevron-right pu-help-section__chevron"></i>
                        <i class="fas fa-life-ring" style="color:var(--pu-text-muted); margin: 0 8px 0 6px;"></i>
                        {{ __('Still stuck? Gather diagnostics for support') }}
                    </span>
                </button>
                <div class="pu-help-section__body">
                    <p class="pu-help__lead">
                        {{ __('If none of the above fixes worked, collect this output and share it with support so they can pinpoint the issue:') }}
                    </p>

                    <pre class="pu-help-code"><button type="button" class="pu-help-code__copy" data-action="copy-code"><i class="fas fa-copy"></i> {{ __('Copy') }}</button>cd /var/www/digikash
php -v
php -m | sort
df -h .
ls -la storage bootstrap/cache
tail -n 100 storage/logs/laravel.log</pre>
                </div>
            </section>
        </div>

        <footer class="pu-modal__foot">
            <span style="font-size:var(--pu-fs-xs); color:var(--pu-text-muted); margin-right:auto;">
                {{ __('After fixing the issue, close this dialog and click Retry install.') }}
            </span>
            <button type="button" class="pu-btn pu-btn--primary" data-action="close-help-modal">
                {{ __('Close') }}
            </button>
        </footer>
    </div>
</div>
