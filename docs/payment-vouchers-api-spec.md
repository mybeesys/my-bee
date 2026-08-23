# مواصفة API سندات الصرف (Payment Vouchers)

> **الغرض:** مرجع حصرّي لشاشة سندات الصرف **وكل الإجراءات التابعة لها** — مواءمة الموبايل مع الويب، ولـ Cursor.  
> **الحالة:** ✅ منفّذ على Laravel.  
> **سندات القبض (mirror للعميل):** [`docs/receipt-vouchers-api-spec.md`](receipt-vouchers-api-spec.md)  
> **فواتير المشتريات (فتح سند من الفاتورة):** [`docs/purchases-api-spec.md`](purchases-api-spec.md)  
> **مرجع عام:** [`docs/mobile-api-parity-spec.md`](mobile-api-parity-spec.md)

---

## 1) مرجع الويب (مصدر الحقيقة)

| الملف | الدور |
|-------|--------|
| `app/Filament/Tenant/Resources/PaymentVoucherResource.php` | قائمة + فورم |
| `.../Pages/CreatePaymentVoucher.php` | مورد (allocation) أو طرف آخر |
| `.../Pages/EditPaymentVoucher.php` | عرض الدفعات من `invoice.purchasePayments` |
| `PaymentVoucherAllocationService` | FIFO / selected |
| `InvoicePaymentTermsService::recordAllocatedSupplierPayment()` | تسجيل الصرف + محاسبة |

---

## 2) الفرق: API القديم vs الويب + API الحالي

| الموضوع | API القديم | الويب + API الحالي |
|---------|------------|---------------------|
| إنشاء مورد | `payments[]` + `invoice_id` | **`paid_amount` + `allocation_mode`** |
| تسوية متعددة | ❌ | ✅ عدة فواتير مشتريات |
| محاسبة | credit/debit معكوسة أحياناً | ✅ credit = حساب الدفع، debit = المورد |
| Edit show | `voucher.payments` | **`invoice.purchasePayments`** |
| prefill / preview | ❌ | ✅ |

---

## 3) Headers و Base

نفس [`receipt-vouchers-api-spec.md`](receipt-vouchers-api-spec.md) — Base `/api/v1/tenant/shop/`.

---

## 4) المسارات

| Method | Path | الوصف |
|--------|------|--------|
| GET | `payment-vouchers` | قائمة |
| POST | `payment-vouchers` | إنشاء |
| GET | `payment-vouchers/{id}` | تفاصيل |
| POST | `payment-vouchers/payments/add` | دفعة إضافية (legacy) |
| POST | `payment-vouchers/utils/allocation-preview` | معاينة |
| GET | `payment-vouchers/prefill` | من فاتورة مشتريات |
| GET | `payment-vouchers/utils/get-supplier-invoices/{supplier_acc4_code}` | dropdown + lines |
| GET | `payment-vouchers/utils/get-other-entities` | طرف آخر |
| GET | `payment-vouchers/utils/get-invoice-info/{id}` | إجماليات |
| GET | `payment-vouchers/utils/get-credit-accounts` | `collectionAccountOptions()` |
| GET | `payment-vouchers/utils/voucher-payment-accounts` | حسابات سطر الدفع |

---

## 5) قائمة `GET payment-vouchers`

نفس فلاتر سندات القبض؛ `for`: `supplier` \| `other_entity`.

**عنصر القائمة:** `entityName` (اسم المورد)، `supplierId`, `paidAmount`, `paidAmountPercent`, `invoiceTotal`, `actions.canEdit`.

---

## 6) إنشاء مورد — allocation

```json
{
  "date": "2026-08-23",
  "for": "supplier",
  "acc4_code": "2117000456",
  "credit_acc4_code": "120100001",
  "paid_amount": 1800,
  "allocation_mode": "fifo",
  "selected_invoice_ids": [],
  "description": "سداد مورد"
}
```

| الحقل | ملاحظات |
|-------|---------|
| `paid_amount` | وجوده يفعّل allocation |
| `credit_acc4_code` | حساب الدفع (خزينة/بنك) — افتراضي collection default |
| `allocation_mode` | `fifo` \| `selected` |

---

## 7) Legacy

### مورد — فاتورة واحدة

```json
{
  "for": "supplier",
  "acc4_code": "2117000456",
  "invoice_id": 20,
  "date": "2026-08-23",
  "payments": [{ "acc4_code": "120100001", "date": "2026-08-23", "amount": 500, "statement": "..." }]
}
```

### طرف آخر

```json
{
  "for": "other_entity",
  "acc4_code": "1218000002",
  "date": "2026-08-23",
  "payments": [{ "acc4_code": "120100001", "date": "2026-08-23", "amount": 300, "statement": "..." }]
}
```

---

## 8) معاينة التوزيع

```http
POST /api/v1/tenant/shop/payment-vouchers/utils/allocation-preview
```

```json
{
  "acc4_code": "2117000456",
  "paid_amount": 1800,
  "allocation_mode": "selected",
  "selected_invoice_ids": [20]
}
```

**Response:** `data.supplierInvoices[]` — نفس شكل `customerInvoices` في القبض.

---

## 9) Prefill

```http
GET /api/v1/tenant/shop/payment-vouchers/prefill?invoice_id=20
```

**Response:** `acc4Code`, `creditAcc4Code`, `paidAmount`, `preselectedInvoiceId`, `supplierInvoices[]`.

---

## 10) تفاصيل و payments

`GET payment-vouchers/{id}` — payments من `invoice.purchasePayments` عند التوفر.

سطر الدفع: `creditAcc4Code` (حساب الدفع), `debitAcc4Code` (المورد), `transactionCompleted`.

---

## 11) إضافة دفعة

```http
POST /api/v1/tenant/shop/payment-vouchers/payments/add
```

```json
{
  "payment_voucher_id": 1,
  "acc4_code": "120100001",
  "date": "2026-08-23",
  "amount": 100,
  "statement": "دفعة إضافية"
}
```

---

## 12) Cursor prompt — شاشة الموبايل

```
Implement Payment Vouchers mobile screens matching docs/payment-vouchers-api-spec.md:

Mirror receipt vouchers UX but for suppliers:
- List + summaries from GET payment-vouchers
- Create supplier: paid_amount + allocation preview + POST store
- Create other_entity: payments repeater
- From purchase invoice complete payment: GET prefill?invoice_id=
- Detail shows purchasePayments when linked to invoice
- Accounting: credit = payment account, debit = supplier acc4
```

---

**آخر تحديث:** 2026-08-23
