<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class Deploy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deploy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        self::artisan('filament:clear-cached-components');
        self::artisan('icons:clear');
//        self::artisan('opcache:clear');
        self::artisan('optimize:clear');

//        self::artisan('migrate:fresh --seed');

        self::artisan('filament:assets');
        self::artisan('filament:cache-components');
        self::artisan('icons:cache');
        self::artisan('optimize');
//        self::artisan('opcache:compile --force');

        $this->info('Deployment Finished');
    }

    protected function artisan($command, $parameters = []): void
    {
        $this->alert("Executing {$command} command");
        Artisan::call($command, $parameters);
        $output = Artisan::output();
        $this->info($output);
    }
}
