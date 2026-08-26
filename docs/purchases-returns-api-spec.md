# مواصفة API مرتجع المشتريات (Purchase Returns)

> **الغرض:** مرجع حصرّي لمردود المشتريات — مواءمة الموبايل مع الويب.  
> **الحالة:** ✅ منفّذ على Laravel بنفس فلو الويب.  
> **تدقيق مقارن مع المبيعات:** [`docs/returns-parity-api-spec.md`](returns-parity-api-spec.md)  
> **تُفتح من فاتورة المشتريات:** [`docs/purchases-api-spec.md`](purchases-api-spec.md)

---

## 1) مرجع الويب

| الملف | الدور |
|-------|--------|
| `app/Filament/Tenant/Resources/PurchasesReturnsResource.php` | نموذج، validation |
| `.../Pages/CreatePurchasesReturns.php` | afterCreate: توسيع + محاسبة |
| `InteractsWithInvoiceReturnLineItems` | تسعير، settlePurchaseReturnPayment |
| `HandlesReturnCreditPayments` | استرداد آجل |

**API:**

| الملف | الدور |
|-------|--------|
| `app/Services/PurchaseReturnService.php` | `create()` |
| `app/Services/SalesReturnWorkflow.php` | منطق مشترك |
| `PurchasesReturnsController` | Endpoints |
| `StorePurchasesReturnsRequest` | Validation |

---

## 2) الفلو

```
فاتورة مشتريات confirmed
        ↓
POST purchases-returns  (invoice | supplier)
        ↓
تسعير البنود + validation
        ↓
settlePurchaseReturnPayment + credit refund اختياري
        ↓
transaction_completed = true
        ↓
أثر المخزون مشتق (purchase_return)
```

لا draft / لا confirm منفصل.

---

## 3) Endpoints

| Method | Path |
|--------|------|
| GET | `purchases-returns` |
| GET | `purchases-returns/{id}` |
| POST | `purchases-returns` |
| PATCH | `purchases-returns/{id}` → `notes` فقط |
| DELETE | `purchases-returns/{id}` |
| GET | `purchases-returns-get-available-invoices` |
| GET | `purchases-returns-list-invoice-items-for-create/{no}` |
| GET | `purchases-returns-returnable-products/{supplierId}` |

**Base:** `/api/v1/tenant/shop/`

---

## 4) أوضاع الإرجاع

### `returnMode: "invoice"`
- فاتورة مشتريات مؤكدة.
- بنود: `details[].invoiceItemId` + `qty`.
- متعدد المرتجعات مسموح إن بقي رصيد كميات/مبلغ.

### `returnMode: "supplier"`
- بدون فاتورة (`invoiceId` = null).
- `supplierId` + `details[].productLineKey` + `qty`.
- المفاتيح من `GET .../returnable-products/{supplierId}`.

---

## 5) فلاتر القائمة

| Param | مثال |
|-------|------|
| `supplier_id` | `3` |
| `invoice_no` | `PI-10` |
| `from_date` / `to_date` | `01-01-2026` / `26-08-2026` (`d-m-Y`) |
| `q` | بحث نصي |

---

## 6) POST أمثلة

### فاتورة + نقدي
```json
{
  "returnMode": "invoice",
  "invoiceNo": "PI-10",
  "paymentTerms": "cash",
  "details": [{ "invoiceItemId": 44, "qty": 1 }]
}
```

### مورد + آجل مع استرداد جزئي
```json
{
  "returnMode": "supplier",
  "supplierId": 3,
  "paymentTerms": "credit",
  "details": [{ "productLineKey": "p:9|u:1", "qty": 2 }],
  "creditPayment": {
    "accountCode": "120100001",
    "amount": 100,
    "date": "2026-08-26",
    "statement": "استرداد"
  }
}
```

### Legacy
```json
{
  "invoice_no": "PI-10",
  "notes": "قديم",
  "items": [{ "id": 44, "qty": 1 }]
}
```

---

## 7) Response

انظر الحقول في [`returns-parity-api-spec.md`](returns-parity-api-spec.md) §6 — يتضمن:
`returnMode`, `supplierId`, `supplierName`, `paymentTerms`, totals، `items[]` مع `price/tax/discount/total/transactionCompleted`.

---

## 8) Helpers

### فواتير قابلة للإرجاع
`GET purchases-returns-get-available-invoices`  
→ `[{ id, no, label, supplierId, supplierName, paymentTerms, paidAmount }]`

### بنود فاتورة
`GET purchases-returns-list-invoice-items-for-create/{no}`  
→ بنود ما زال لها كمية قابلة للإرجاع.

### منتجات لمورد
`GET purchases-returns-returnable-products/{supplierId}`  
→ `[{ productLineKey, name, returnableQty }]`

---

## 9) من فاتورة المشتريات

1. `actions.canPurchaseReturn === true`
2. إن `purchasesReturnId` → عرض المرتجع (أول واحد — مثل زر الويب)
3. وإلا إنشاء عبر helpers + POST
4. يمكن إنشاء مرتجعات إضافية إن بقيت كميات

---

*آخر تحديث: 2026-08-26*
