<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VerifyStorageLinks extends Command
{
    protected $signature = 'storage:verify {--fix : Recreate broken storage/cdn symlinks}';

    protected $description = 'Diagnose public storage symlinks and media file availability';

    public function handle(): int
    {
        $this->info('Application paths');
        $this->line('  base_path:   '.base_path());
        $this->line('  public_path: '.public_path());
        $this->line('  storage_path: '.storage_path('app/public'));
        $this->line('  APP_URL:     '.config('app.url'));
        $this->newLine();

        $links = [
            'storage' => storage_path('app/public'),
            'cdn' => base_path('cdn'),
        ];

        foreach ($links as $name => $target) {
            $linkPath = public_path($name);
            $this->inspectLink($name, $linkPath, $target);
        }

        $this->newLine();
        $this->info('Write test');

        $probePath = 'storage-verify-'.now()->timestamp.'.txt';
        $canWritePublic = false;
        $canWriteTemp = false;

        try {
            Storage::disk('public')->put($probePath, 'ok');
            $canWritePublic = Storage::disk('public')->exists($probePath);
            Storage::disk('public')->delete($probePath);
        } catch (\Throwable $exception) {
            $this->error('  public disk write failed: '.$exception->getMessage());
        }

        try {
            Storage::disk(config('livewire.temporary_file_upload.disk', 'local'))
                ->put('livewire-tmp/'.$probePath, 'ok');
            $canWriteTemp = Storage::disk(config('livewire.temporary_file_upload.disk', 'local'))
                ->exists('livewire-tmp/'.$probePath);
            Storage::disk(config('livewire.temporary_file_upload.disk', 'local'))
                ->delete('livewire-tmp/'.$probePath);
        } catch (\Throwable $exception) {
            $this->error('  livewire temp write failed: '.$exception->getMessage());
        }

        $this->line('  public disk writable: '.($canWritePublic ? 'YES' : 'NO'));
        $this->line('  livewire temp writable: '.($canWriteTemp ? 'YES' : 'NO'));

        if (! $canWritePublic || ! $canWriteTemp) {
            $this->warn('  Fix permissions: chmod -R 775 storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache');
        }

        $this->newLine();
        $this->info('Sample media files');

        $mediaItems = Media::query()->latest('id')->limit(5)->get();

        if ($mediaItems->isEmpty()) {
            $this->warn('  No media records found in database.');

            return self::SUCCESS;
        }

        foreach ($mediaItems as $media) {
            $relativePath = $media->getPathRelativeToRoot();
            $absolutePath = $media->getPath();
            $exists = Storage::disk($media->disk)->exists($relativePath);

            $this->line(sprintf(
                '  #%d [%s] %s',
                $media->id,
                $exists ? 'OK' : 'MISSING',
                $relativePath
            ));
            $this->line('      disk path: '.$absolutePath);
            $this->line('      url:       '.$media->getUrl());
        }

        if ($this->option('fix')) {
            $this->newLine();
            $this->prepareLinksForFix($links);
            $this->call('storage:link', ['--force' => true]);
            $this->newLine();
            $this->info('After fix:');

            foreach ($links as $name => $target) {
                $this->inspectLink($name, public_path($name), $target);
            }
        }

        return self::SUCCESS;
    }

    /** @param  array<string, string>  $links */
    protected function prepareLinksForFix(array $links): void
    {
        foreach ($links as $name => $target) {
            $linkPath = public_path($name);

            if (is_link($linkPath)) {
                continue;
            }

            if (! file_exists($linkPath)) {
                continue;
            }

            $backupPath = $linkPath.'.bak-'.now()->format('YmdHis');

            if (is_dir($linkPath)) {
                File::moveDirectory($linkPath, $backupPath);
                $this->warn("Moved blocking directory public/{$name} to ".basename($backupPath));

                continue;
            }

            File::move($linkPath, $backupPath);
            $this->warn("Moved blocking file public/{$name} to ".basename($backupPath));
        }
    }

    protected function inspectLink(string $name, string $linkPath, string $target): void
    {
        $this->line("Link: public/{$name}");
        $this->line('  expected at: '.$linkPath);
        $this->line('  should target: '.$target);

        if (! file_exists($linkPath) && ! is_link($linkPath)) {
            $this->error('  status: MISSING — run php artisan storage:link from the app root');

            return;
        }

        if (is_link($linkPath)) {
            $this->line('  status: symlink');
            $this->line('  points to: '.readlink($linkPath));
            $this->line('  resolves: '.(realpath($linkPath) ?: 'broken target'));

            if (! realpath($linkPath)) {
                $this->error('  symlink target is broken — remove the link and run php artisan storage:link');
            }

            return;
        }

        if (is_dir($linkPath)) {
            $this->warn('  status: real directory (not a symlink) — web requests cannot see uploaded files');
            $this->line('  files inside: '.count(File::files($linkPath)));
            $this->line('  fix: php artisan storage:verify --fix');

            return;
        }

        $this->warn('  status: exists but is not a symlink or directory');
    }
}
