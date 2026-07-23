<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VerifyFrontendAssets extends Command
{
    protected $signature = 'assets:verify {--theme : Show tenant theme CSS details only}';

    protected $description = 'Verify built frontend assets and notification badge files';

    public function handle(): int
    {
        $manifestPath = public_path('build/manifest.json');

        $this->info('Frontend build');
        $this->line('  manifest: '.$manifestPath);

        if (! file_exists($manifestPath)) {
            $this->error('  status: MISSING — run npm run build');

            return self::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true) ?: [];
        $themeEntry = $manifest['resources/css/filament/tenant/theme.css']['file'] ?? null;
        $themePath = $themeEntry ? public_path('build/'.$themeEntry) : null;

        $this->line('  theme css: '.($themePath ?: 'not found in manifest'));

        if ($themePath && file_exists($themePath)) {
            $css = File::get($themePath);
            $this->line('  theme file exists: YES');
            $this->line('  contains notification badge css: '.(str_contains($css, 'tenant-database-notifications-trigger') ? 'YES' : 'NO'));
            $this->line('  contains red color #dc2626: '.(str_contains($css, '#dc2626') ? 'YES' : 'NO'));
            $this->line('  file size: '.number_format(filesize($themePath) / 1024, 1).' KB');
            $this->line('  modified: '.date('Y-m-d H:i:s', filemtime($themePath)));
        } else {
            $this->error('  theme file exists: NO — run npm run build');
        }

        if (file_exists(public_path('hot'))) {
            $this->warn('  public/hot exists — Vite dev server mode is active');
            $this->line('  hot url: '.trim(File::get(public_path('hot'))));
        } else {
            $this->line('  public/hot: not present (production build mode)');
        }

        if (! $this->option('theme')) {
            $this->newLine();
            $this->info('Notification trigger view');

            $triggerPath = resource_path('views/filament/tenant/components/database-notifications-trigger.blade.php');
            $this->line('  path: '.$triggerPath);

            if (! file_exists($triggerPath)) {
                $this->error('  status: MISSING');
            } else {
                $blade = File::get($triggerPath);
                $this->line('  uses inline red badge: '.(str_contains($blade, '#dc2626') ? 'YES' : 'NO'));
                $this->line('  uses filament badge component: '.(str_contains($blade, ':badge=') ? 'YES' : 'NO'));
                $this->line('  modified: '.date('Y-m-d H:i:s', filemtime($triggerPath)));
            }

            $this->newLine();
            $this->info('Livewire override');
            $this->line('  TenantDatabaseNotifications registered: '.(
                class_exists(\App\Livewire\TenantDatabaseNotifications::class) ? 'YES' : 'NO'
            ));
        }

        $this->newLine();
        $this->comment('Browser check: open DevTools → Network → filter "theme" → confirm latest theme-*.css loads.');
        $this->comment('View source: search for data-notification-badge="custom" near the bell icon.');
        $this->comment('If missing, run: php artisan view:clear && php artisan optimize:clear');

        return self::SUCCESS;
    }
}
