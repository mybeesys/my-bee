# مواصفة API فواتير المشتريات (Purchase Invoices)

> **الغرض:** مرجع حصرّي لشاشة فواتير المشتريات **وكل الإجراءات التابعة لها** — مواءمة التطبيق مع الويب، ولـ Cursor.  
> **الحالة:** ✅ منفّذ على Laravel.  
> **يكمل:** [`docs/supply-orders-api-spec.md`](supply-orders-api-spec.md) (التحويل من أمر توريد).  
> **الموردون (إنشاء سريع من الفورم):** [`docs/suppliers-api-spec.md`](suppliers-api-spec.md)  
> **مرجع عام:** [`docs/mobile-api-parity-spec.md`](mobile-api-parity-spec.md)

---

## 1) مرجع الويب

| الملف | الدور |
|-------|--------|
| `app/Filament/Tenant/Resources/PurchaseInvoiceResource.php` | قائمة، فورم (مورد، شروط دفع، مستودع، بنود بسعر/ضريبة/خصم، تكاليف إضافية) |
| `.../Pages/CreatePurchaseInvoice.php` | إنشاء مؤكد مباشرة + `confirmPurchaseInvoice()` + دفعة آجل اختيارية |
| `.../Pages/EditPurchaseInvoice.php` | تعديل البنود فقط إذا `locked_at` فارغ |
| `Invoice::confirmPurchaseInvoice()` | قفل + مخزون + قيود محاسبية |
| `InvoicePaymentTermsService` | دفعة آجل (`recordCreditPayment`) |

**API:** `PurchaseInvoiceController` + `PurchaseInvoiceService::commit()`. لا تكرّر منطق المخزون/المحاسبة في Flutter.

---

## 2) الفرق: قبل / بعد

| الموضوع | API القديم | الويب + API الحالي |
|---------|------------|---------------------|
| إنشاء | `POST purchases` → مسودة **temp** فارغة ثم add-item ثم `save` (تبقى `purchase_order` غير مؤكدة) ثم `update-status` | **`POST purchases/commit`** فاتورة **مؤكدة** دفعة واحدة (مثل الويب) |
| `payment_terms` | ❌ | `cash` \| `credit` (الافتراضي **credit**) |
| دفعة آجل | ❌ | `credit_payment` اختياري مع الآجل |
| تفاصيل | ❌ `GET purchases/{id}` كان يفشل | `GET purchases/{id}` |
| تحويل من أمر توريد | `start-purchase-invoice` يبني temp بأسعار 0 (`update-item` يرفض `unit_cost < 1`) | `GET supply-orders/{id}/purchase-prefill` ثم `commit` بالأسعار |
| قفل بعد التأكيد | غير موثّق | `lockedAt` + `canEdit: false` — لا تعديل بنود بعد التأكيد |
| مشاركة / PDF | ❌ | `shareUrl` + `pdfUrl` |
| مرتجع مشتريات | شاشة موجودة قديماً | `actions.canPurchaseReturn` + `purchasesReturnId` |
| إكمال الدفع (سند صرف) | `paymentVoucherId` فقط | `actions.canCompletePayment` + سند صرف |
| دفعة آجل بعد القفل | فقط مع `commit` | `POST purchases/{id}/credit-payment` (مثل تبويب الدفعات في الويب) |

**مسار الـ temp القديم ما زال يعمل** — لا تحذفه. الشاشات الجديدة تستخدم `commit`.

---

## 3) Headers و Base

```
Authorization: Bearer {token}
Tenant-Id: {tenant_id}
Accept: application/json
```

**Base:** `/api/v1/tenant/shop/`

**Envelope:** `{ statusCode, statusText, message, data, errors, locale }`

**Body:** snake_case. **JSON:** camelCase.

**تواريخ القائمة (legacy):** `d-m-Y` ما زال مقبولاً. `Y-m-d` مقبول أيضاً ويُحوَّل داخلياً.  
**تاريخ `commit` و `credit_payment.date`:** `Y-m-d` (مثل DatePicker الويب). `d-m-Y` مقبول ويُحوَّل.

---

## 4) شاشات الويب

```mermaid
flowchart TD
    A[قائمة فواتير المشتريات] --> B[إنشاء جديد]
    A --> C[من أمر توريد]
    A --> D[عرض / قفل بعد التأكيد]
    A --> E[مشاركة رابط / PDF]
    A --> F[مرتجع مشتريات]
    A --> G[تفاصيل الدفع / سند صرف]
    B --> H[مورد + شروط دفع + مستودع + بنود بسعر]
    H --> I[اختياري: تكاليف إضافية]
    H --> J[آجل: دفعة جزئية]
    H --> K[commit = تأكيد + مخزون + قيود]
    K -->|نقد| L[مؤكدة بدون سند تلقائي]
    K -->|آجل| M[due أو partial حسب الدفعة]
    C --> N[prefill ثم نفس فورم الإنشاء]
    D --> F
    D --> G
    D --> O[دفعة آجل إضافية إن لم تُدفع بالكامل]
```

**فورم الإنشاء:** رقم (سيرفر)، تاريخ (آخر 30 يوماً حتى اليوم)، مورد (إنشاء سريع)، `payment_terms` افتراضي credit، مستودع (إنشاء سريع)، بنود: منتج + كمية + **سعر** + خصم + ضريبة، تبويب تكاليف إضافية، تبويب دفعات (ظاهر للآجل).

**بعد الحفظ:** الحالة `confirmed` و`locked_at` مُعبأ. النقد **لا** يُسجّل سند صرف تلقائياً في المشتريات (عكس المبيعات) — هذا سلوك الويب.

لا خدمات ولا extras على بنود المشتريات (عكس المبيعات). تبويبات الويب: خصم + دفعات (آجل) + تكاليف إضافية.

### 4.1 إجراءات صف القائمة (مثل `ActionGroup` في الويب)

كل صف في `GET purchases` يرجع `actions` + روابط جاهزة. **لا تخفِ إجراء موجود في الويب.**

| إجراء الويب | متى يظهر | ماذا يفعل الموبايل |
|-------------|----------|---------------------|
| **رابط الفاتورة** | `actions.canShare` | انسخ/افتح `shareUrl`. زر تحميل: `pdfUrl` |
| **مرتجع مشتريات** | `actions.canPurchaseReturn` | إن `purchasesReturnId` → `GET purchases-returns/{id}`. وإلا إنشاء: `GET purchases-returns-list-invoice-items-for-create/{no}` ثم `POST purchases-returns` مع `invoice_no` = رقم الفاتورة. الويب يسمح بمرتجع **واحد** لكل فاتورة |
| **تفاصيل الدفع** | `actions.canCompletePayment` (`isPaid=false`) | إن `paymentVoucherId` → `GET/PATCH payment-vouchers/{id}`. وإلا → `POST payment-vouchers` مع `invoice_id` و`for=supplier` |
| **دفعة آجل** | `actions.canCreditPayment` | `POST purchases/{id}/credit-payment` (تبويب الدفعات يعمل بعد القفل) |
| فتح/عرض | دائماً | `GET purchases/{id}` — البنود مقفولة إذا `canEdit=false` |

الويب **لا يحذف** فاتورة مشتريات من الجدول. لا تضع زر حذف.

### 4.2 أزرار أعلى القائمة (مثل الويب)

1. **إنشاء فاتورة** → فورم `commit`. معطّل إذا حد الاشتراك `purchase_invoices`.
2. **من أمر توريد** → عندما توجد شاشة أوامر توريد: `GET supply-orders` ثم `GET supply-orders/{id}/purchase-prefill` ثم نفس فورم الإنشاء (عبّئ الأسعار) ثم `commit`. لا تربط هذا الزر قبل بناء شاشة أوامر التوريد.

### 4.3 فورم الإنشاء (مطابقة الويب)

| الحقل | إلزامي | ملاحظات |
|-------|--------|---------|
| رقم الفاتورة | سيرفر | لا ترسل `no` |
| التاريخ | لا (افتراضي اليوم) | آخر 30 يوماً حتى اليوم |
| المورد | نعم | بحث `GET suppliers` + إنشاء سريع `POST suppliers` بالاسم |
| `payment_terms` | لا | افتراضي `credit`. `cash` \| `credit` |
| المستودع | نعم | `GET /api/v1/tenant/settings/warehouses` + إنشاء سريع `POST .../warehouses` بالاسم |
| البنود | نعم | سعر شراء (`unit_cost`)، كمية 1–250000، خصم بند، ضريبة. **بدون extras** |
| تكاليف إضافية | لا | تبويب |
| خصم إجمالي | لا | `discount_option` / `discount_method` / `discount_amount` أو `discount_percent` |
| دفعة آجل | لا | ظاهرة فقط إذا `credit`. حسابات: `GET .../utils/accounts/collection` |

**نقد (`cash`):** الفاتورة مؤكدة وقد تبقى `settlementStatusKey: due` — لا تنشئ سند صرف من عندك.

**منتجات الفورم:**  
`POST /api/v1/tenant/shop/list-products-for-advanced-creation` مع `{ "for": "purchases" }`.

`selectVariantOptions` بقي كما هو (مسار المبيعات/temp القديم: لون ثم مقاس).  
**للـ commit استخدم `variants[]` — كل عنصر هو تركيبة جاهزة بـ `id`:**

```json
{
  "id": 15,
  "type": "variants",
  "name": "تيشيرت",
  "taxProfileId": 3,
  "selectVariantOptions": [
    {
      "variantLibraryName": "اللون",
      "variantLibraryId": 1,
      "options": [{ "id": 10, "name": "أحمر" }, { "id": 11, "name": "أزرق" }]
    }
  ],
  "variants": [
    { "id": 44, "productId": 15, "name": "أحمر / L", "sku": "TSH-R-L", "variantLibraryOptionsIds": [10, 21] },
    { "id": 45, "productId": 15, "name": "أزرق / M", "sku": "TSH-B-M", "variantLibraryOptionsIds": [11, 22] }
  ],
  "selectExtras": []
}
```

- `type=basic`: أرسل `product_id` فقط، `variants` فارغة.
- `type=variants`: اعرض `variants` كقائمة اختيار (كل صف = `product_variant_id`). لا تحسب التركيب من `selectVariantOptions`.
- بديل: أرسل `selected_variant_options_ids: [10, 21]` بدون `product_variant_id` والسيرفر يحوّلها. المسار الأوضح للموبايل هو `variants[].id`.

**حسابات التحصيل لدفعة الآجل:**  
`GET /api/v1/tenant/settings/utils/accounts/collection`

**مستودعات:** `GET /api/v1/tenant/settings/warehouses`  
**ملفات ضريبة:** `GET /api/v1/tenant/settings/tax-profiles`  
**أنواع التكلفة الإضافية:** `GET /api/v1/tenant/settings/additional-costs-types`  
**موردون:** `GET /api/v1/tenant/shop/suppliers` — إنشاء سريع: `POST /suppliers` بالاسم.

---

## 5) Endpoints

| Method | Path | الوصف |
|--------|------|--------|
| `GET` | `purchases` | قائمة (غير temp) |
| `GET` | `purchases/{id}` | تفاصيل + `actions` |
| `POST` | `purchases/commit` | **إنشاء مؤكد دفعة واحدة (المسار الجديد)** |
| `POST` | `purchases/{id}/credit-payment` | دفعة آجل بعد القفل |
| `POST` | `purchases` | مسودة temp (legacy) |
| `POST` | `purchases/add-item` | legacy |
| `POST` | `purchases/update-item` | legacy — `unit_cost` min **1** |
| `POST` | `purchases/delete-item` | legacy |
| `POST` | `purchases/add-additional-cost` | legacy |
| `POST` | `purchases/update-additional-cost` | legacy |
| `POST` | `purchases/delete-additional-cost` | legacy |
| `POST` | `purchases/apply-overall-discount` | legacy |
| `POST` | `purchases/remove-overall-discount` | legacy |
| `POST` | `purchases/save` | legacy — `temp=false`، تبقى `purchase_order` |
| `POST` | `purchases/update-status` | legacy — `confirmed` يستدعي `confirmPurchaseInvoice` |
| `POST` | `purchases-clear-temp-invoices` | حذف المسودات temp |
| `GET` | `supply-orders/{id}/purchase-prefill` | JSON للـ commit بدون إنشاء فاتورة |
| `POST` | `supply-orders/{id}/start-purchase-invoice` | legacy temp بأسعار 0 |
| `GET` | `suppliers/{id}/purchase-invoices` | قائمة مختصرة من صفحة المورد |

`PUT`/`PATCH` `purchases/{id}` و `DELETE` غير مدعومين (مثل الويب بعد القفل / لا حذف في الجدول).

---

## 6) GET — القائمة

```
GET /api/v1/tenant/shop/purchases
```

**Query:**

| المعامل | ملاحظات |
|---------|---------|
| `search` | رقم الفاتورة أو اسم المورد |
| `status` | `purchase_order` \| `confirmed` \| `cancelled` |
| `payment_terms` | `cash` \| `credit` |
| `supplier_id`, `warehouse_id`, `user_id` | |
| `from_date`, `to_date`, `date` | `d-m-Y` أو `Y-m-d` |
| `sort` | `latest` (افتراضي) \| `oldest` |
| `paginate`, `per_page` | مثل العملاء |
| `payment_status` | قيم إنجليزية قديمة: `Post paid`, `Partly paid`, `Paid` |
| `payment_method`, `transaction_ref`, `discount_method` | legacy |

القائمة لا تتضمّن `temp=true`.

---

## 7) POST — commit (المسار المعتمد)

```
POST /api/v1/tenant/shop/purchases/commit
```

```json
{
  "supplier_id": 7,
  "warehouse_id": 1,
  "date": "2026-08-16",
  "payment_terms": "credit",
  "prices_includes_taxes": true,
  "notes": null,
  "items": [
    {
      "product_id": 12,
      "product_variant_id": null,
      "qty": 10,
      "unit_cost": 25.5,
      "discount": 0,
      "tax_profile_id": 3
    },
    {
      "product_id": 15,
      "product_variant_id": 44,
      "qty": 2,
      "unit_cost": 40,
      "tax_profile_id": 3
    }
  ],
  "additional_costs": [
    {
      "additional_cost_type_id": 1,
      "cost": 50,
      "statement": "شحن",
      "tax_profile_id": null
    }
  ],
  "credit_payment": {
    "account_code": "120100001",
    "amount": 100,
    "date": "2026-08-16",
    "statement": "دفعة أولى"
  }
}
```

### قواعد

- `supplier_id` و `warehouse_id` و `items` إلزامية. بند واحد على الأقل.
- `unit_cost` يمكن إرساله باسم `price`. الحد الأدنى `0.01`.
- `qty` عدد صحيح ≥ 1.
- منتج `type=variants`: **إلزامي** `product_variant_id` (من `variants[].id`) أو `selected_variant_options_ids`. بدونها `422`.
- `payment_terms` افتراضي `credit`. `cash` لا يُنشئ سند صرف تلقائي (مثل الويب).
- `credit_payment` **اختياري**، وفقط مع الآجل. `amount` ≤ المتبقي بعد التأكيد. إن فشل الدفع تُلغى الفاتورة كلها (`422`).
- `date` ضمن آخر 30 يوماً حتى اليوم.
- حد الاشتراك `purchase_invoices`: `400`.
- السيرفر يولّد `no`، يحسب الضريبة، يستدعي `confirmPurchaseInvoice()` (مخزون + قيود)، ثم الدفعة الآجلة إن وُجدت.
- `201` + نفس شكل `GET purchases/{id}`.
- `variant` يجب أن يتبع `product_id`.

**نقد (`cash`):** الفاتورة مؤكدة وقد تبقى `settlementStatusKey: due` ما لم يُسجَّل سند صرف لاحقاً.

**آجل بدون دفعة:** `due`. مع دفعة جزئية: `partial`. مع دفعة تغطي الكل: `paid`.

---

## 8) GET — تفاصيل

```
GET /api/v1/tenant/shop/purchases/{id}
```

حقول **مضافة** (القديمة باقية):

```json
{
  "id": 88,
  "no": "10459001",
  "uid": "...",
  "status": "confirmed",
  "paymentTerms": "credit",
  "settlementStatusKey": "partial",
  "settlementStatus": "...",
  "pricesIncludesTaxes": true,
  "lockedAt": "2026-08-16 15:04:00",
  "supplierId": 7,
  "warehouseId": 1,
  "shareUrl": "https://…/invoices/{uid}",
  "pdfUrl": "https://…/invoices/{uid}/pdf",
  "canUpdateStatus": false,
  "canEdit": false,
  "hasPurchaseReturn": false,
  "purchasesReturnsCount": 0,
  "purchasesReturnId": null,
  "paymentVoucherId": 12,
  "actions": {
    "canShare": true,
    "canPurchaseReturn": true,
    "canCompletePayment": true,
    "canCreditPayment": true,
    "canEdit": false
  },
  "items": [
    {
      "id": 1,
      "productId": 12,
      "productVariantId": null,
      "taxProfileId": 3,
      "qty": 10,
      "price": "25.50",
      "discount": "0.00",
      "tax": "...",
      "subTotal": "..."
    }
  ]
}
```

`date` / `createdAt` بقيت بصيغة `F j, Y, g:i a` للتوافق مع التطبيق القديم.

`settlementStatusKey`: `cash` \| `paid` \| `due` \| `partial`.

---

## 8.1 دفعة آجل بعد القفل

```
POST /api/v1/tenant/shop/purchases/{id}/credit-payment
```

```json
{
  "account_code": "120100001",
  "amount": 50,
  "date": "2026-08-17",
  "statement": "دفعة ثانية"
}
```

- فقط فاتورة مشتريات `confirmed` و`payment_terms=credit` وغير مدفوعة بالكامل.
- `amount` ≤ المتبقي. حسابات: `GET .../settings/utils/accounts/collection`.
- `200` + الفاتورة محدّثة (`paidAmount`, `paymentVoucherId`, `settlementStatusKey`).

---

## 8.2 مرتجع المشتريات (من صف الفاتورة)

لا تعِد بناء API المرتجع هنا. استخدم المسارات الموجودة:

1. `actions.canPurchaseReturn === true`
2. إن `purchasesReturnId` → `GET purchases-returns/{purchasesReturnId}`
3. وإلا → `GET purchases-returns-list-invoice-items-for-create/{no}` ثم `POST purchases-returns` `{ invoice_no, notes, items: [{ id, qty }] }`
4. مساعدة لاختيار فاتورة من شاشة المرتجعات: `GET purchases-returns-get-available-invoices` (فواتير بلا مرتجع)

الويب: مرتجع **واحد** لكل فاتورة مشتريات.

---

## 8.3 إكمال الدفع (سند صرف)

يظهر الزر إذا `!isPaid`.

| الحالة | الإجراء |
|--------|---------|
| `paymentVoucherId` موجود | افتح سند الصرف للتعديل / إضافة دفعة |
| لا يوجد | `POST /api/v1/tenant/shop/payment-vouchers` مع `invoice_id` |

هذا غير `credit-payment`: السند شاشة مستقلة؛ دفعة الآجل تسجّل من تبويب الفاتورة.

---

## 9) التحويل من أمر توريد

**نفّذ هذا القسم فقط عندما توجد شاشة أوامر توريد في الموبايل.** الـ endpoints جاهزة على الباك؛ لا حاجة لمسار مؤقت داخل فاتورة المشتريات.

```
GET /api/v1/tenant/shop/supply-orders/{id}/purchase-prefill
```

```json
{
  "supplyOrderId": 3,
  "supplyOrderNo": "10458291",
  "description": "توريد مواد خام",
  "supplierId": 7,
  "supplierName": "مؤسسة الإمداد",
  "items": [
    {
      "productId": 12,
      "productVariantId": null,
      "name": "سكر",
      "qty": 10,
      "unitCost": null,
      "taxProfileId": 3
    }
  ]
}
```

لا يُنشئ فاتورة. المستخدم يعبّئ `unit_cost` لكل بند ثم `POST purchases/commit` مع `supplier_id` من الرد + `warehouse_id` من الفورم + البنود بالأسعار.

الأمر بلا بنود: `400`.

### مسار temp القديم (لا تستخدمه في الشاشات الجديدة)

`POST supply-orders/{id}/start-purchase-invoice` → فاتورة temp بأسعار 0 → `update-item` (min 1) → `save` → `update-status: confirmed`.

---

## 10) مسار temp القديم (للتوافق فقط)

1. `POST purchases` → `{ no, uid }`
2. `POST purchases/add-item` `{ purchase_invoice_uid, product_id, type: basic|variants, qty, unit_cost, ... }`
3. تكاليف/خصم إجمالي اختيارياً
4. `POST purchases/save` `{ uid, no, date: d-m-Y, supplier_id, warehouse_id, payment_terms? }` — **لا تؤكّد**
5. `POST purchases/update-status` `{ purchase_invoice_uid, status: confirmed|cancelled, payment_terms?, credit_payment? }`

`save` يقبل الآن `payment_terms` و `prices_includes_taxes` اختيارياً.  
`update-status` عند `confirmed` يقبل `credit_payment` اختيارياً.

---

## 11) Prompt جاهز لـ Cursor (Flutter)

انسخ هذا البرومبت كما هو:

```
Implement Purchase Invoices to match the MyBee web app using docs/purchases-api-spec.md as the single source of truth. Also implement EVERY related row/header action from the web list and invoice screen.

Web: Filament PurchaseInvoiceResource.
API base: /api/v1/tenant/shop/
Auth: Bearer + Tenant-Id.

NEW create MUST use POST purchases/commit. Do not use the temp draft flow for new UI.

Must implement:

A) List GET purchases
- Columns: no, supplier, settlementStatusKey, date, paidAmount (+ percent), invoice total, optional additional costs, purchase-return indicator if purchasesReturnsCount > 0.
- Search no / supplier.
- Row actions from data.actions (do not invent visibility):
  1. Share: copy/open shareUrl; download pdfUrl.
  2. Purchase return: if purchasesReturnId open GET purchases-returns/{id}, else GET purchases-returns-list-invoice-items-for-create/{no} then POST purchases-returns with invoice_no.
  3. Complete payment if canCompletePayment: payment voucher via paymentVoucherId or POST payment-vouchers with invoice_id.
  4. Credit payment if canCreditPayment: POST purchases/{id}/credit-payment.
- Header: Create. Convert from supply order: SKIP until Supply Orders screen exists (then GET purchase-prefill → commit).

B) Create form
- supplier (GET/POST suppliers), warehouse (GET/POST settings/warehouses), payment_terms default credit, date Y-m-d last 30 days.
- Lines: POST list-products-for-advanced-creation { "for": "purchases" }. Show basic AND variants. Variants: pick variants[].id as product_variant_id. unit_cost >= 0.01, qty 1–250000. NO extras, NO services.
- Tabs: additional costs, credit payment only if credit (collection accounts from GET settings/utils/accounts/collection). Optional overall discount.
- Cash: after commit do NOT create a payment voucher automatically.
- Stock/accounting errors from server: show message, do not keep a draft.

C) Show GET purchases/{id}. Locked after confirm (canEdit false). Still allow credit-payment, purchase-return, share, and payment voucher.

D) Keep old temp endpoints only if existing production code already depends on them.

Body snake_case, response camelCase.
```

---

## 12) QA

| # | سيناريو | المتوقع |
|---|---------|---------|
| 1 | `POST commit` بمورد + مستودع + بند بسعر | `201`، `status=confirmed`، `lockedAt` غير فارغ، حركة مخزون |
| 2 | بدون بنود | `422` على `items` |
| 3 | `unit_cost` = 0 | `422` |
| 4 | آجل + `credit_payment.amount` أكبر من الإجمالي | `422`، لا فاتورة |
| 5 | نقد | مؤكدة، غالباً `settlementStatusKey=due` (لا سند تلقائي) |
| 6 | حد الاشتراك | `400` |
| 7 | prefill ثم commit | نفس المورد والكميات مع الأسعار المُرسلة |
| 8 | `GET purchases/{id}` بعد commit | بنود + `paymentTerms` + `supplierId` |
| 9 | مسار temp القديم `store` → add-item → save | ما زال `purchase_order` حتى `update-status` |
| 10 | variant لا يتبع المنتج | `422` على `items` |
| 11 | منتج variants بدون `product_variant_id` | `422` |
| 13 | `shareUrl` / `pdfUrl` | يفتحان الصفحة العامة / PDF |
| 14 | `canPurchaseReturn` | يفتح إنشاء مرتجع أو المرتجع الموجود |
| 15 | `POST credit-payment` بعد القفل | يزيد المدفوع |
| 16 | قائمة | إجراءات الصف من `actions` فقط |

---

*آخر تحديث: 2026-08-17.*
