<?php

namespace App\Services\Concerns;

use App\Models\Acc4;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InvoicePaymentTermsService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait ResolvesInvoiceProductLines
{
    /**
     * @param  array<string, mixed>  $line
     * @return array{product_id: int, product_variant_id: int|null, name: string}
     */
    public function resolveProduct(array $line, string $errorKey = 'items'): array
    {
        $product = Product::query()->with('variants')->findOrFail($line['product_id']);
        $variantId = $line['product_variant_id'] ?? null;

        if (empty($variantId) && ! empty($line['selected_variant_options_ids'])) {
            $variantId = $this->variantIdFromOptionIds($product, $line['selected_variant_options_ids'], $errorKey);
        }

        if ($product->type === Product::$TYPE_VARIANTS && empty($variantId)) {
            throw ValidationException::withMessages([
                $errorKey => __('validation.required', ['attribute' => 'product_variant_id']),
            ]);
        }

        if (! empty($variantId)) {
            $variant = $product->variants->firstWhere('id', (int) $variantId)
                ?? ProductVariant::query()->findOrFail($variantId);

            if ((int) $variant->product_id !== (int) $product->id) {
                throw ValidationException::withMessages([
                    $errorKey => __('validation.exists', ['attribute' => 'product_variant_id']),
                ]);
            }

            return [
                'product_id' => (int) $product->id,
                'product_variant_id' => (int) $variant->id,
                'name' => $variant->name,
            ];
        }

        return [
            'product_id' => (int) $product->id,
            'product_variant_id' => null,
            'name' => $product->name,
        ];
    }

    /**
     * @param  array<int, mixed>  $optionIds
     */
    protected function variantIdFromOptionIds(Product $product, array $optionIds, string $errorKey = 'items'): int
    {
        $wanted = array_map('intval', $optionIds);
        sort($wanted);

        foreach ($product->variants as $variant) {
            $have = array_map('intval', $variant->variant_library_options_ids ?? []);
            sort($have);

            if ($have === $wanted) {
                return (int) $variant->id;
            }
        }

        throw ValidationException::withMessages([
            $errorKey => __('validation.exists', ['attribute' => 'selected_variant_options_ids']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordOptionalCreditPayment($invoice, array $payload): void
    {
        $credit = $payload['credit_payment'] ?? null;

        if (! is_array($credit)) {
            return;
        }

        $amount = (float) ($credit['amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        InvoicePaymentTermsService::instance()->recordCreditPayment($invoice, [
            'amount' => $amount,
            'account_code' => $credit['account_code'] ?? Acc4::defaultCollectionAccountCode(),
            'date' => $this->parseDate($credit['date'] ?? null),
            'statement' => $credit['statement'] ?? '',
        ]);
    }

    public function parseDate(mixed $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        if (! is_string($date) || trim($date) === '') {
            return now();
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::parse($date);
    }
}
