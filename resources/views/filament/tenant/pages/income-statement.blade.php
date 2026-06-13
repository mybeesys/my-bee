@php
    $statement = $this->statement;
    $isAr = app()->getLocale() === 'ar';
    $alignAmount = $isAr ? 'text-left' : 'text-right';
@endphp

<x-filament-panels::page class="fi-page-income-statement">
    <div class="income-statement space-y-6">
        <form wire:submit.prevent="loadStatement">
            {{ $this->form }}
        </form>

        @if ($statement)
            <div class="income-statement__panel rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="income-statement__header mb-6 border-b border-gray-100 pb-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('fields.income_statement') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ filament()->getTenant()?->name }}
                        — {{ __('fields.income_statement_operational_basis') }}
                        @if ($statement['from'] || $statement['to'])
                            · {{ __('fields.income_statement_period') }}:
                            {{ $statement['from'] ? \Carbon\Carbon::parse($statement['from'])->translatedFormat('d M Y') : '…' }}
                            {{ __('fields.income_statement_period_to') }}
                            {{ $statement['to'] ? \Carbon\Carbon::parse($statement['to'])->translatedFormat('d M Y') : '…' }}
                        @endif
                    </p>
                </div>

                <div class="income-statement__summary mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="income-statement__card income-statement__card--sales">
                        <p class="income-statement__card-label">{{ __('fields.income_statement_sales_section') }}</p>
                        <p class="income-statement__card-value tabular-nums">{{ format_amount($statement['sales_total']) }}</p>
                        <p class="income-statement__card-currency">{{ $statement['currency'] }}</p>
                    </div>
                    <div class="income-statement__card income-statement__card--purchases">
                        <p class="income-statement__card-label">{{ __('fields.income_statement_purchases_section') }}</p>
                        <p class="income-statement__card-value tabular-nums">{{ format_amount($statement['purchases_total']) }}</p>
                        <p class="income-statement__card-currency">{{ $statement['currency'] }}</p>
                    </div>
                    <div class="income-statement__card income-statement__card--expenses">
                        <p class="income-statement__card-label">{{ __('fields.income_statement_expense_section') }}</p>
                        <p class="income-statement__card-value tabular-nums">{{ format_amount($statement['expenses_total']) }}</p>
                        <p class="income-statement__card-currency">{{ $statement['currency'] }}</p>
                    </div>
                    @php
                        $net = $statement['net_income'];
                        $netPositive = $net >= 0;
                    @endphp
                    <div class="income-statement__card income-statement__card--net {{ $netPositive ? 'income-statement__card--net-positive' : 'income-statement__card--net-negative' }}">
                        <p class="income-statement__card-label">{{ __('fields.income_statement_net_income') }}</p>
                        <p class="income-statement__card-value tabular-nums">{{ format_amount($net) }}</p>
                        <p class="income-statement__card-currency">{{ $statement['currency'] }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="income-statement__table w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 {{ $isAr ? 'text-right' : 'text-left' }} font-medium text-gray-700 dark:text-gray-200">
                                    {{ __('fields.income_statement_item') }}
                                </th>
                                <th class="px-4 py-3 {{ $alignAmount }} font-medium text-gray-700 dark:text-gray-200">
                                    {{ __('fields.amount') }} ({{ $statement['currency'] }})
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr class="income-statement__section income-statement__section--sales">
                                <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    {{ __('fields.income_statement_sales_section') }}
                                </td>
                            </tr>
                            @forelse ($statement['sales_lines'] as $line)
                                <tr>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                        @if ($line->code)
                                            <span class="text-gray-400">{{ $line->code }}</span>
                                            — {{ $line->name }}
                                        @else
                                            {{ $line->name }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums text-emerald-700 dark:text-emerald-400">
                                        {{ format_amount($line->net) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-2.5 text-gray-400 italic">
                                        {{ __('fields.income_statement_no_lines') }}
                                    </td>
                                </tr>
                            @endforelse
                            <tr class="font-semibold income-statement__subtotal income-statement__subtotal--sales">
                                <td class="px-4 py-3 text-gray-900 dark:text-white">
                                    {{ __('fields.income_statement_total_sales') }}
                                </td>
                                <td class="px-4 py-3 {{ $alignAmount }} tabular-nums text-emerald-700 dark:text-emerald-400">
                                    {{ format_amount($statement['sales_total']) }}
                                </td>
                            </tr>

                            <tr class="income-statement__section income-statement__section--purchases">
                                <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    {{ __('fields.income_statement_purchases_section') }}
                                </td>
                            </tr>
                            @forelse ($statement['purchases_lines'] as $line)
                                <tr>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                        @if ($line->code)
                                            <span class="text-gray-400">{{ $line->code }}</span>
                                            — {{ $line->name }}
                                        @else
                                            {{ $line->name }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums text-amber-700 dark:text-amber-400">
                                        {{ format_amount($line->net) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-2.5 text-gray-400 italic">
                                        {{ __('fields.income_statement_no_lines') }}
                                    </td>
                                </tr>
                            @endforelse
                            <tr class="font-semibold income-statement__subtotal income-statement__subtotal--purchases">
                                <td class="px-4 py-3 text-gray-900 dark:text-white">
                                    {{ __('fields.income_statement_total_purchases') }}
                                </td>
                                <td class="px-4 py-3 {{ $alignAmount }} tabular-nums text-amber-700 dark:text-amber-400">
                                    {{ format_amount($statement['purchases_total']) }}
                                </td>
                            </tr>

                            <tr class="income-statement__section income-statement__section--expenses">
                                <td colspan="2" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide">
                                    {{ __('fields.income_statement_expense_section') }}
                                </td>
                            </tr>
                            @forelse ($statement['expense_lines'] as $line)
                                <tr>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                        @if ($line->code)
                                            <span class="text-gray-400">{{ $line->code }}</span>
                                            — {{ $line->name }}
                                        @else
                                            {{ $line->name }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 {{ $alignAmount }} tabular-nums text-rose-700 dark:text-rose-400">
                                        {{ format_amount($line->net) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-2.5 text-gray-400 italic">
                                        {{ __('fields.income_statement_no_lines') }}
                                    </td>
                                </tr>
                            @endforelse
                            <tr class="font-semibold income-statement__subtotal income-statement__subtotal--expenses">
                                <td class="px-4 py-3 text-gray-900 dark:text-white">
                                    {{ __('fields.income_statement_total_expenses') }}
                                </td>
                                <td class="px-4 py-3 {{ $alignAmount }} tabular-nums text-rose-700 dark:text-rose-400">
                                    {{ format_amount($statement['expenses_total']) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="income-statement__footer border-t-2 border-gray-300 dark:border-gray-600">
                                <td class="px-4 py-4 text-base font-bold text-gray-950 dark:text-white">
                                    {{ __('fields.income_statement_net_income') }}
                                </td>
                                <td class="px-4 py-4 {{ $alignAmount }} text-base font-bold tabular-nums {{ $netPositive ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                    {{ format_amount($net) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
