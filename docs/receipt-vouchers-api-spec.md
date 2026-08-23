# مواصفة API سندات القبض (Receipt Vouchers)

> **الغرض:** مرجع حصرّي لشاشة سندات القبض **وكل الإجراءات التابعة لها** — مواءمة الموبايل مع الويب، ولـ Cursor.  
> **الحالة:** ✅ منفّذ على Laravel.  
> **سندات الصرف (mirror للمورد):** [`docs/payment-vouchers-api-spec.md`](payment-vouchers-api-spec.md)  
> **فواتير المبيعات (فتح سند من الفاتورة):** [`docs/sales-api-spec.md`](sales-api-spec.md)  
> **مرجع عام:** [`docs/mobile-api-parity-spec.md`](mobile-api-parity-spec.md)

---

## 1) مرجع الويب (مصدر الحقيقة)

| الملف | الدور |
|-------|--------|
| `app/Filament/Tenant/Resources/ReceiptVoucherResource.php` | قائمة + فورم |
| `.../Pages/CreateReceiptVoucher.php` | عميل (allocation) أو طرف آخر (payments repeater) |
| `.../Pages/EditReceiptVoucher.php` | عرض الدفعات من `invoice.salesPayments` |
| `ReceiptVoucherAllocationService` | FIFO / selected + تسوية عدة فواتير |
| `InvoicePaymentTermsService::recordAllocatedCustomerReceipt()` | تسجيل القبض + محاسبة |
| `CompletesVoucherPaymentAccounting` | قيود debit/credit لكل سطر |

---

## 2) الفرق: API القديم vs الويب + API الحالي

| الموضوع | API القديم | الويب + API الحالي |
|---------|------------|---------------------|
| إنشاء عميل | `payments[]` + `invoice_id` واحدة | **`paid_amount` + `allocation_mode`** (FIFO/selected) |
| تسوية متعددة | ❌ | ✅ عدة فواتير مبيعات غير مدفوعة |
| محاسبة | ❌ أو جزئية | ✅ `transactionCompleted` على كل سطر |
| طرف آخر | payments repeater | **`payments[]`** + `for=other_entity` (legacy path) |
| Edit show | `voucher.payments` فقط | **`invoice.salesPayments`** إن وُجدت |
| قائمة | حقول أساسية | `paidAmount`, `paidAmountPercent`, `entityName`, `actions` |
| prefill | ❌ | `GET receipt-vouchers/prefill?invoice_id=&order_id=` |
| preview | ❌ | `POST .../utils/allocation-preview` |

مسار legacy (`invoice_id` + `payments[]` بدون `paid_amount`) **ما زال يعمل** للتوافق.

---

## 3) Headers و Base

```
Authorization: Bearer {token}
Tenant-Id: {tenant_id}
Accept: application/json
```

**Base:** `/api/v1/tenant/shop/`

**Envelope:** `{ statusCode, statusText, message, data, errors, locale, filters? }`

**Body:** snake_case. **JSON:** camelCase.

**تواريخ:** `Y-m-d` (مفضّل) أو `d-m-Y` في store؛ `addPayment` يستخدم `Y-m-d`.

---

## 4) المسارات

| Method | Path | الوصف |
|--------|------|--------|
| GET | `receipt-vouchers` | قائمة + فلاتر + `filters.listSummaries` |
| POST | `receipt-vouchers` | إنشاء (allocation أو legacy) |
| GET | `receipt-vouchers/{id}` | تفاصيل + payments |
| POST | `receipt-vouchers/payments/add` | إضافة دفعة لسند legacy |
| POST | `receipt-vouchers/utils/allocation-preview` | معاينة التوزيع |
| GET | `receipt-vouchers/prefill` | تعبئة من فاتورة/طلب |
| GET | `receipt-vouchers/utils/get-customer-invoices/{customer_acc4_code}` | dropdown + lines |
| GET | `receipt-vouchers/utils/get-other-entities` | `userCreatedOtherPartyAccountOptions()` |
| GET | `receipt-vouchers/utils/get-invoice-info/{id}` | إجماليات الفاتورة |
| GET | `receipt-vouchers/utils/get-credit-accounts` | `collectionAccountOptions()` |
| GET | `receipt-vouchers/utils/voucher-payment-accounts` | `voucherOtherEntityPaymentAccountOptions()` |

---

## 5) قائمة `GET receipt-vouchers`

**Query params:**

| Param | نوع | ملاحظات |
|-------|-----|---------|
| `search` | string | رقم السند / اسم الحساب / رقم الفاتورة |
| `for` | string \| string[] | `customer` \| `other_entity` |
| `invoice_id` | int | |
| `invoice_ids[]` | int[] | |
| `acc4_code` | string | |
| `acc4_codes[]` | string[] | |
| `date` | date | تاريخ السند |
| `created_from` / `created_until` | date | تاريخ الإنشاء |
| `from_date` / `to_date` | d-m-Y | legacy range |
| `sort` | `latest` \| `oldest` | افتراضي `latest` |
| `include_summaries` | bool | افتراضي `true` |
| `paginate` | bool | |

**عنصر القائمة (camelCase):**

```json
{
  "id": 1,
  "no": "RV-001",
  "for": "customer",
  "invoiceId": 10,
  "invoiceNo": "SI-010",
  "date": "23-08-2026",
  "entityName": "عميل ABC",
  "paidAmount": "1500.00",
  "paidAmountNumeric": 1500,
  "invoiceTotal": "3000.00",
  "paidAmountPercent": "50.00",
  "actions": { "canEdit": true }
}
```

**`filters.listSummaries`:** `{ paidAmount, currency }`

---

## 6) إنشاء عميل — allocation (مثل الويب)

```http
POST /api/v1/tenant/shop/receipt-vouchers
Content-Type: application/json
```

```json
{
  "date": "2026-08-23",
  "for": "customer",
  "acc4_code": "1217000123",
  "debit_acc4_code": "120100001",
  "paid_amount": 2500,
  "allocation_mode": "fifo",
  "selected_invoice_ids": [],
  "description": "تحصيل جزئي"
}
```

| الحقل | إلزامي | ملاحظات |
|-------|--------|---------|
| `for` | نعم | `customer` |
| `acc4_code` | نعم | حساب العميل (acc4) |
| `paid_amount` | نعم | ≥ 0.01 — **وجوده يفعّل allocation** |
| `debit_acc4_code` | لا | افتراضي `Acc4::defaultCollectionAccountCode()` |
| `allocation_mode` | لا | `fifo` (افتراضي) \| `selected` |
| `selected_invoice_ids[]` | عند selected | فواتير محددة |
| `preselected_invoice_id` | لا | يُستخدم في preview إذا selected فارغ |

---

## 7) إنشاء — legacy

### 7.1 عميل (فاتورة واحدة + payments)

```json
{
  "date": "2026-08-23",
  "for": "customer",
  "acc4_code": "1217000123",
  "invoice_id": 10,
  "payments": [
    {
      "acc4_code": "120100001",
      "date": "2026-08-23",
      "amount": 500,
      "statement": "دفعة نقدية"
    }
  ]
}
```

### 7.2 طرف آخر (`other_entity`)

```json
{
  "date": "2026-08-23",
  "for": "other_entity",
  "acc4_code": "1218000001",
  "payments": [
    {
      "acc4_code": "120100001",
      "date": "2026-08-23",
      "amount": 200,
      "statement": "قبض من طرف آخر"
    }
  ]
}
```

**مرفقات:** `multipart/form-data` — `payments.0.attachments[]` (png/jpg/webp، max 2048KB).

---

## 8) معاينة التوزيع

```http
POST /api/v1/tenant/shop/receipt-vouchers/utils/allocation-preview
```

```json
{
  "acc4_code": "1217000123",
  "paid_amount": 2500,
  "allocation_mode": "selected",
  "selected_invoice_ids": [10, 11],
  "preselected_invoice_id": 10
}
```

**Response `data.customerInvoices[]`:**

```json
{
  "invoice_id": 10,
  "no": "SI-010",
  "date": "2026-08-01",
  "invoice_total": "3000.00",
  "remaining": "1500.00",
  "remaining_raw": 1500,
  "selected": true,
  "allocated": "1500.00",
  "allocated_raw": 1500
}
```

---

## 9) Prefill

```http
GET /api/v1/tenant/shop/receipt-vouchers/prefill?invoice_id=10
GET /api/v1/tenant/shop/receipt-vouchers/prefill?order_id=5
```

**Response:** `for`, `allocationMode`, `acc4Code`, `debitAcc4Code`, `paidAmount`, `preselectedInvoiceId`, `customerInvoices[]`.

---

## 10) تفاصيل `GET receipt-vouchers/{id}`

- **`payments`:** إن وُجدت `invoice.salesPayments` → تُرجع كل دفعات الفاتورة (مثل Edit الويب).
- كل سطر دفع: `debitAcc4Code`, `creditAcc4Code`, `transactionCompleted`, `attachments[]`.

---

## 11) إضافة دفعة `POST receipt-vouchers/payments/add`

```json
{
  "receipt_voucher_id": 1,
  "acc4_code": "120100001",
  "date": "2026-08-23",
  "amount": 100,
  "statement": "دفعة إضافية"
}
```

`amount` ≥ 0.01. مرفقات: `attachments[]` (max 2048KB).

---

## 12) Utils — حسابات

| Endpoint | يعادل الويب |
|----------|-------------|
| `GET .../get-credit-accounts` | حساب التحصيل (عميل) |
| `GET .../voucher-payment-accounts` | حساب سطر الدفع (طرف آخر) |
| `GET .../get-other-entities` | header طرف آخر |

**Response:** `{ "code": "اسم الحساب", ... }` (map code → label).

---

## 13) Cursor prompt — شاشة الموبايل

```
Implement Receipt Vouchers mobile screens matching docs/receipt-vouchers-api-spec.md:

1. List: GET receipt-vouchers with search, for filter, summaries footer (filters.listSummaries.paidAmount).
2. Create customer flow: pick client → paid_amount → allocation_mode fifo|selected → live preview via POST allocation-preview → POST store with paid_amount (NOT payments[] only).
3. Create other_entity: payments repeater + voucher-payment-accounts utils.
4. Detail/Edit: GET show — render payments from response (may be full invoice.salesPayments).
5. From sales invoice action canCompletePayment: prefill?invoice_id= then same create flow.
6. Use snake_case in requests, camelCase in models. Dates Y-m-d in API body.
7. Do NOT delete vouchers (web has no delete).
```

---

## 14) Validation errors شائعة

| Error | السبب |
|-------|-------|
| `paid_amount` exceeds remaining | المبلغ أكبر من مجموع المتبقي |
| `selected_invoice_ids` empty in selected mode | لم تُختر فواتير |
| `acc4_code` out of scope | حساب لا ينتمي للعميل/طرف آخر |

---

**آخر تحديث:** 2026-08-23
