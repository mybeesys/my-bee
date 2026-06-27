<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Console\Command;

class BackfillInvoiceVatJournals extends Command
{
    protected $signature = 'invoices:backfill-vat-journals {--tenant= : Limit to a tenant id} {--dry-run : List invoices only, do not post journals}';

    protected $description = 'Post missing VAT journal lines for confirmed sales and purchase invoices';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        $tenants = $tenantId
            ? Tenant::query()->whereKey($tenantId)->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return self::FAILURE;
        }

        $salesCount = 0;
        $purchaseCount = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant #{$tenant->id} — {$tenant->name}");

            request()->headers->set('Tenant-Id', (string) $tenant->id);

            $tenantUser = $tenant->users()->first();

            if ($tenantUser) {
                auth()->login($tenantUser);
            }

            Invoice::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'confirmed')
                ->where('temp', false)
                ->whereIn('type', [Invoice::$TYPE_SALES, Invoice::$TYPE_PURCHASES])
                ->orderBy('id')
                ->chunkById(100, function ($invoices) use ($dryRun, &$salesCount, &$purchaseCount) {
                    foreach ($invoices as $invoice) {
                        $invoice->loadMissing(['items', 'additionalCosts', 'services']);
                        $tax = round((float) $invoice->getTaxesAsAmount(), 4);

                        if ($tax <= 0) {
                            continue;
                        }

                        if ($invoice->type === Invoice::$TYPE_SALES) {
                            if ($invoice->hasSalesTaxJournal()) {
                                continue;
                            }

                            $this->line("  Sales {$invoice->no} (id {$invoice->id}) — VAT {$tax}");

                            if (! $dryRun) {
                                $invoice->postSalesRevenueJournal();
                            }

                            $salesCount++;

                            continue;
                        }

                        if ($invoice->hasPurchaseTaxJournal()) {
                            continue;
                        }

                        $this->line("  Purchase {$invoice->no} (id {$invoice->id}) — VAT {$tax}");

                        if (! $dryRun) {
                            $invoice->postPurchaseTaxJournalIfMissing();
                        }

                        $purchaseCount++;
                    }
                });
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry run complete. Sales: {$salesCount}, Purchases: {$purchaseCount}."
            : "Backfill complete. Sales: {$salesCount}, Purchases: {$purchaseCount}.");

        return self::SUCCESS;
    }
}
