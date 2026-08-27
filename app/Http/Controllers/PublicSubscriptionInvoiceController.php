<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\InvoiceZatcaQrService;
use Illuminate\View\View;

class PublicSubscriptionInvoiceController extends Controller
{
    public function show(string $uid): View
    {
        $subscription = Subscription::query()
            ->where('uid', $uid)
            ->with(['client', 'plan'])
            ->firstOrFail();

        $company = platform_company_profile();
        $qrService = InvoiceZatcaQrService::instance();
        $documentUrl = route('public.subscription-invoice.show', ['uid' => $subscription->uid]);
        $qr = $qrService->buildSubscriptionQr(
            $subscription,
            (string) ($company['name'] ?? ''),
            (string) ($company['trn'] ?? ''),
            $documentUrl,
        );

        return view('subscriptions.public-invoice', [
            'subscription' => $subscription,
            'company' => $company,
            'companyName' => $company['name'],
            'companyAddress' => $company['address'],
            'companyPhone' => $company['phone'],
            'companyMobile' => $company['mobile'],
            'companyEmail' => $company['email'],
            'companyTrn' => $company['trn'],
            'qrPayload' => $qr['qrPayload'],
            'qrDataUri' => $qr['qrDataUri'],
            'qrKind' => $qr['qrKind'],
            'currency' => main_currency_iso_code(),
        ]);
    }
}
