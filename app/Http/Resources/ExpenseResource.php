<?php

namespace App\Http\Resources;

use App\Services\MediaService;
use App\Models\Invoice;
use Carbon\Carbon;

class ExpenseResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $tax = (float) ($this->tax ?? 0);
        $amountNet = (float) $this->amount;
        $total = (float) $this->total;
        $taxPercent = $this->taxProfile
            ? (float) collect($this->taxProfile->taxes)->sum('percent')
            : 0;

        return $this->filterFields([
            'id' => $this->id,
            'expenseCategoryId' => $this->expense_category_id,
            'expenseCategoryName' => $this->category?->name,
            'description' => $this->description,
            'amount' => number_format($amountNet, currency_decimals(), '.', ''),
            'amountNumeric' => round($amountNet, currency_decimals()),
            'amountFormatted' => $this->amount_formatted.' '.main_currency_iso_code(),
            'amountIncludesTax' => $tax > 0,
            'amountWithoutTax' => number_format($amountNet, currency_decimals(), '.', ''),
            'amountWithoutTaxNumeric' => round($amountNet, currency_decimals()),
            'grossAmount' => number_format($total, currency_decimals(), '.', ''),
            'grossAmountNumeric' => round($total, currency_decimals()),
            'tax' => number_format($tax, currency_decimals(), '.', ''),
            'taxNumeric' => round($tax, currency_decimals()),
            'taxFormatted' => $this->tax_formatted.' '.main_currency_iso_code(),
            'taxPercent' => round($taxPercent, currency_decimals()),
            'taxProfileId' => $this->tax_profile_id,
            'taxProfile' => $this->taxProfile ? new TaxProfileResource($this->taxProfile) : null,
            'total' => number_format($total, currency_decimals(), '.', ''),
            'totalNumeric' => round($total, currency_decimals()),
            'totalFormatted' => number_format($total, currency_decimals(), '.', '').' '.main_currency_iso_code(),
            'amountWritten' => numbers_to_words($amountNet),
            'taxWritten' => numbers_to_words($tax),
            'totalWritten' => numbers_to_words($total),
            'debitAcc4Code' => (string) $this->debit_acc4_code,
            'creditAcc4Code' => (string) $this->credit_acc4_code,
            'debitAccount' => $this->whenLoaded('debitAccount', fn () => new Acc4Resource($this->debitAccount)),
            'creditAccount' => $this->whenLoaded('creditAccount', fn () => new Acc4Resource($this->creditAccount)),
            'date' => Carbon::parse($this->date)->format('d-m-Y'),
            'dateFormatted' => Carbon::parse($this->date)->format('Y-m-d'),
            'dateDisplay' => Carbon::parse($this->date)->format('F j, Y'),
            'attachments' => MediaService::mediaUrls($this->getMedia('attachments')),
            'mediaCount' => $this->getMedia('attachments')->count(),
            'invoiceId' => $this->meta['invoice_id'] ?? null,
            'invoiceNo' => isset($this->meta['invoice_id'])
                ? Invoice::query()->find($this->meta['invoice_id'])?->no
                : null,
            'meta' => $this->meta,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'createdAtFormatted' => $this->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
            'actions' => [
                'canEdit' => true,
                'canDelete' => false,
            ],
            'canDelete' => false,
        ]);
    }
}
