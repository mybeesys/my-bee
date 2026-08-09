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
        $qrPayload = $qrService->subscriptionTlvBase64($subscription, $company['name'], $company['trn']);
        $qrDataUri = $qrService->qrDataUriFromPayload($qrPayload);

        if ($qrDataUri !== null && ! str_starts_with($qrDataUri, 'data:')) {
            $qrDataUri = 'data:image/png;base64,' . $qrDataUri;
        }

        return view('subscriptions.public-invoice', [
            'subscription' => $subscription,
            'company' => $company,
            'companyName' => $company['name'],
            'companyAddress' => $company['address'],
            'companyPhone' => $company['phone'],
            'companyMobile' => $company['mobile'],
            'companyEmail' => $company['email'],
            'companyTrn' => $company['trn'],
            'qrPayload' => $qrPayload,
            'qrDataUri' => $qrDataUri,
            'currency' => main_currency_iso_code(),
        ]);
    }
}
