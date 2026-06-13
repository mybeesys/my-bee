@php
    use App\Filament\Tenant\Resources\SalesInvoiceResource;
@endphp

<div class="customer-view__profile rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="customer-view__profile-header mb-5 flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-5 dark:border-gray-800">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                {{ __('fields.client') }}
            </p>
            <h2 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                {{ $record->name }}
            </h2>
            @if ($record->no)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('fields.reference_code') }}: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->no }}</span>
                </p>
            @endif
        </div>
        @if ($statement)
            <div class="customer-view__balance-badge {{ $balancePositive ? 'customer-view__balance-badge--due' : 'customer-view__balance-badge--clear' }}">
                <span class="customer-view__balance-badge-label">{{ __('fields.customer_balance_due') }}</span>
                <span class="customer-view__balance-badge-value tabular-nums">{{ format_amount($balanceDue) }} {{ $statement['currency'] }}</span>
            </div>
        @endif
    </div>

    <div class="customer-view__details grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="customer-view__detail">
            <span class="customer-view__detail-label">{{ __('fields.phone') }}</span>
            <span class="customer-view__detail-value">{{ $record->phone ?: '—' }}</span>
        </div>
        <div class="customer-view__detail">
            <span class="customer-view__detail-label">{{ __('fields.email') }}</span>
            <span class="customer-view__detail-value">{{ $record->email ?: '—' }}</span>
        </div>
        <div class="customer-view__detail">
            <span class="customer-view__detail-label">{{ __('fields.delivery_address') }}</span>
            <span class="customer-view__detail-value">{{ $record->delivery_address ?: '—' }}</span>
        </div>
        <div class="customer-view__detail">
            <span class="customer-view__detail-label">{{ __('fields.customer_location') }}</span>
            <span class="customer-view__detail-value">{{ $record->location ?: '—' }}</span>
        </div>
        @if ($record->trn)
            <div class="customer-view__detail">
                <span class="customer-view__detail-label">{{ __('fields.trn') }}</span>
                <span class="customer-view__detail-value">{{ $record->trn }}</span>
            </div>
        @endif
        <div class="customer-view__detail">
            <span class="customer-view__detail-label">{{ __('fields.join_date') }}</span>
            <span class="customer-view__detail-value">{{ $record->created_at?->translatedFormat('d M Y') }}</span>
        </div>
        @if ($statement)
            <div class="customer-view__detail">
                <span class="customer-view__detail-label">{{ __('fields.account') }}</span>
                <span class="customer-view__detail-value tabular-nums">{{ $statement['account_code'] }}</span>
            </div>
        @endif
    </div>
</div>

@if ($statement)
    <div class="customer-view__summary grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="customer-view__card customer-view__card--orders">
            <p class="customer-view__card-label">{{ __('fields.orders') }}</p>
            <p class="customer-view__card-value tabular-nums">{{ $statement['orders_count'] }}</p>
        </div>
        <div class="customer-view__card customer-view__card--invoices">
            <p class="customer-view__card-label">{{ __('fields.invoices') }}</p>
            <p class="customer-view__card-value tabular-nums">{{ $statement['invoices_count'] }}</p>
        </div>
        <div class="customer-view__card customer-view__card--unpaid">
            <p class="customer-view__card-label">{{ __('fields.customer_unpaid_invoices') }}</p>
            <p class="customer-view__card-value tabular-nums">{{ format_amount($statement['unpaid_total']) }}</p>
            <p class="customer-view__card-currency">{{ $statement['currency'] }}</p>
        </div>
        <div class="customer-view__card customer-view__card--balance {{ $balancePositive ? 'customer-view__card--balance-due' : 'customer-view__card--balance-clear' }}">
            <p class="customer-view__card-label">{{ __('fields.balance') }}</p>
            <p class="customer-view__card-value tabular-nums">{{ format_amount($statement['current_balance']) }}</p>
            <p class="customer-view__card-currency">{{ $statement['currency'] }}</p>
        </div>
    </div>
@endif

<div class="customer-view__statement rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="customer-view__statement-header mb-6 border-b border-gray-100 pb-4 dark:border-gray-800">
        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
            {{ __('fields.account_statement') }}
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $record->name }}
            @if ($statement && (($statement['from'] ?? null) || ($statement['to'] ?? null)))
                — {{ __('fields.income_statement_period') }}:
                {{ $statement['from'] ? \Carbon\Carbon::parse($statement['from'])->translatedFormat('d M Y') : '…' }}
                {{ __('fields.income_statement_period_to') }}
                {{ $statement['to'] ? \Carbon\Carbon::parse($statement['to'])->translatedFormat('d M Y') : '…' }}
            @endif
        </p>
    </div>

    <form wire:submit.prevent="loadStatement" class="mb-6">
        {{ $this->filtersForm }}
    </form>

    @if ($statement === null)
        <div class="customer-view__empty rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-6 text-center dark:border-amber-800 dark:bg-amber-950/20">
            <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                {{ __('fields.customer_account_statement_no_account') }}
            </p>
        </div>
    @else
        <div class="overflow-x-auto overflow-y-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="customer-view__table w-full min-w-[720px] text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 {{ $isAr ? 'text-right' : 'text-left' }} font-medium text-gray-700 dark:text-gray-200">
                            {{ __('fields.date') }}
                        </th>
                        <th class="px-4 py-3 {{ $isAr ? 'text-right' : 'text-left' }} font-medium text-gray-700 dark:text-gray-200">
                            {{ __('fields.voucher_no') }}
                        </th>
                        <th class="px-4 py-3 {{ $isAr ? 'text-right' : 'text-left' }} font-medium text-gray-700 dark:text-gray-200">
                            {{ __('fields.statement') }}
                        </th>
                        <th class="px-4 py-3 {{ $alignAmount }} font-medium text-gray-700 dark:text-gray-200">
                            {{ __('fields.debit') }}
                        </th>
                        <th class="px-4 py-3 {{ $alignAmount }} font-medium text-gray-700 dark:text-gray-200">
                            {{ __('fields.credit') }}
                        </th>
                        <th class="px-4 py-3 {{ $alignAmount }} font-medium text-gray-700 dark:text-gray-200">
                            {{ __('fields.balance') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @if ($statement['from'] && $statement['opening_balance'] != 0)
                        <tr class="customer-view__opening-row">
                            <td colspan="3" class="px-4 py-2.5 font-medium text-gray-600 dark:text-gray-300">
                                {{ __('fields.customer_opening_balance') }}
                            </td>
                            <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums text-gray-400">—</td>
                            <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums text-gray-400">—</td>
                            <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums font-medium text-gray-900 dark:text-white">
                                {{ format_amount($statement['opening_balance']) }}
                            </td>
                        </tr>
                    @endif

                    @forelse ($statement['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2.5 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                {{ $line['date']?->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-2.5 tabular-nums text-gray-700 dark:text-gray-300">
                                {{ $line['voucher_no'] ?: '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $line['statement'] }}
                                @if ($line['invoice_id'] && $line['invoice_no'])
                                    <a
                                        href="{{ SalesInvoiceResource::getUrl('edit', ['record' => $line['invoice_id']]) }}"
                                        class="ms-1 text-primary-600 hover:underline dark:text-primary-400"
                                        target="_blank"
                                    >
                                        #{{ $line['invoice_no'] }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums text-emerald-700 dark:text-emerald-400">
                                {{ $line['debit'] > 0 ? format_amount($line['debit']) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums text-rose-700 dark:text-rose-400">
                                {{ $line['credit'] > 0 ? format_amount($line['credit']) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums font-medium {{ $line['balance'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                                {{ format_amount($line['balance']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">
                                {{ __('fields.income_statement_no_lines') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($statement['lines']->isNotEmpty())
                    <tfoot>
                        <tr class="customer-view__footer border-t-2 border-gray-300 dark:border-gray-600">
                            <td colspan="3" class="px-4 py-4 font-bold text-gray-950 dark:text-white">
                                {{ __('fields.customer_statement_totals') }}
                            </td>
                            <td class="px-4 py-4 {{ $alignAmount }} font-bold tabular-nums text-emerald-700 dark:text-emerald-400">
                                {{ format_amount($statement['total_debit']) }}
                            </td>
                            <td class="px-4 py-4 {{ $alignAmount }} font-bold tabular-nums text-rose-700 dark:text-rose-400">
                                {{ format_amount($statement['total_credit']) }}
                            </td>
                            <td class="px-4 py-4 {{ $alignAmount }} font-bold tabular-nums {{ $statement['closing_balance'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-950 dark:text-white' }}">
                                {{ format_amount($statement['closing_balance']) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
</div>
