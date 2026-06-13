<?php

namespace App\Jobs;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanTemporaryInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = Invoice::where('temp', 1)
            ->where('created_at', '>=', Carbon::now()->subMinutes(20)->toDateTimeString())
            ->delete();

        logger("CleanTemporaryInvoicesJob cleared {$data} items.");
    }
}
