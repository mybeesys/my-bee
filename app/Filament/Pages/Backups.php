<?php

namespace App\Filament\Pages;

use App\Services\ShellService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';


//    public function getHeading(): string | Htmlable
//    {
//        return 'Application Backups';
//    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function create(string $option = ''): void
    {
        if ($option !== 'only-files') {
            $mysqldumpAvailable = (new ShellService())->available(ShellService::COMMAND_MYSQLDUMP);

            if (!$mysqldumpAvailable) {
                $this->dispatch('close-modal', id: 'backup-option');
                fns()->sendDanger('Service unavailable', 'mysqldump was not found.');
                return;
            }
        }
        /** @var FilamentSpatieLaravelBackupPlugin $plugin */
        $plugin = filament()->getPlugin('filament-spatie-backup');

        CreateBackupJob::dispatch(Option::from($option))
            ->onQueue($plugin->getQueue())
            ->afterResponse();

        $this->dispatch('close-modal', id: 'backup-option');

        Notification::make()
            ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_success'))
            ->success()
            ->send();
    }
}
