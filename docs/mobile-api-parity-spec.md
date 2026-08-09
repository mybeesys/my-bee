# مواصفة مواءمة API التطبيق مع الويب (MyBee)

> **الهدف:** جعل تطبيق الموبايل يطابق سلوك الويب في: **مرتجع المبيعات**، **حسابات الأطراف الأخرى (المستوى الرابع)**، **الاشتراكات وكوبونات المنصة**.  
> **مبدأ العمل:** توسيع الـ API الحالي **بدون كسر** العقود القديمة — إضافة حقول/مسارات/معاملات اختيارية، واستخراج منطق الويب إلى Services مشتركة بدل نسخ الكود.

**مرجع الويب:**
- مرتجع المبيعات: `app/Filament/Tenant/Resources/SalesReturnsResource.php`
- حسابات المستوى الرابع: `app/Filament/Tenant/Resources/Acc4Resource.php` (slug: `finance/tree-accounts/level-four`)
- كشف الحساب (فلتر الحساب): `app/Filament/Tenant/Resources/AccountStatementResource.php`
- الاشتراك: `app/Livewire/ManageSubscription.php` + `app/Services/SubscriptionPricingService.php` + `app/Services/SubscriptionCouponService.php`

**بادئة API الحالية:** `/api/v1/tenant/shop/` (مع `auth:sanctum`, `Tenant-Id` header, tenant scopes)

---

## ✅ حالة التنفيذ (2026-08-04)

| المجال | الحالة | ملاحظات |
|--------|--------|---------|
| **مرتجع المبيعات** | ✅ منفّذ | `SalesReturnService` + أوضاع `invoice` / `customer` + مسارات returnable |
| **حسابات الأطراف الأخرى** | ✅ منفّذ | `GET/POST/PATCH/DELETE .../acc4/other-parties` + `?scope=other_parties` + utils |
| **سندات — get-other-entities** | ✅ منفّذ | يستخدم `userCreatedOtherPartyAccountOptions()` |
| **الاشتراكات** | ✅ منفّذ | `/api/v1/client/*` + middleware trial + `subscriptionSummary` في login/me |
| **اختبارات Feature** | ⏳ معلّق | لم تُضف بعد |

### مسارات جديدة (مرجع سريع)

```
GET  /api/v1/tenant/shop/sales-returns-returnable-products/{customerId}
GET  /api/v1/tenant/settings/acc4?scope=other_parties
GET  /api/v1/tenant/settings/acc4/other-parties
POST /api/v1/tenant/settings/acc4/other-parties          { "name": "..." }
PATCH /api/v1/tenant/settings/acc4/other-parties/{code}  { "name": "..." }
DELETE /api/v1/tenant/settings/acc4/other-parties/{code}
GET  /api/v1/tenant/settings/utils/accounts/collection
GET  /api/v1/tenant/settings/utils/accounts/other-parties
GET  /api/v1/tenant/settings/utils/accounts/voucher-payments
GET  /api/v1/tenant/settings/utils/accounts/statement

GET  /api/v1/client/subscription
GET  /api/v1/client/plans
POST /api/v1/client/subscription/quote
POST /api/v1/client/subscription/coupon/validate
POST /api/v1/client/subscription/subscribe
GET  /api/v1/client/subscription/usage
GET  /api/v1/client/subscription/coupons/available
```

**Middleware:** `ensure_client_subscription_active_api` على مجموعة `/api/v1/tenant/*` — يرجع `403` + `code: subscription_trial_expired` للعميل (`ROLE_CLIENT`) عند انتهاء التجربة. مسارات `/api/v1/client/*` و `/login` و `/me` **غير** محظورة.

**ملفات Laravel الجديدة:**
- `app/Services/SalesReturnService.php`, `SalesReturnWorkflow.php`
- `app/Services/SubscriptionApiService.php`
- `app/Http/Controllers/API/V1/ClientSubscriptionController.php`
- `app/Http/Middleware/EnsureClientSubscriptionActiveApi.php`
- `app/Http/Resources/Acc4OtherPartyResource.php`
- `app/Http/Requests/StoreAcc4OtherPartyRequest.php`, `UpdateAcc4OtherPartyRequest.php`

---

## 0) قواعد عامة للتنفيذ

1. **لا تحذف** endpoints أو حقول response موجودة — أضف حقولاً جديدة أو `v2` فقط إذا اضطررت لتغيير breaking.
2. **أعد استخدام** نفس الـ scopes والـ services من الويب (لا تعيد تعريف منطق محاسبي في Controller).
3. **التحقق (validation)** في API يجب أن يستدعي نفس الدوال/الخدمات التي يستخدمها Filament.
4. **JSON:** camelCase في Resources (مثل باقي API)، مع توثيق snake_case في body الطلبات إن لزم.
5. **اختبار القبول:** كل سيناريو موثّق أدناه يجب أن ينتج نفس side effects في DB (تفاصيل، قيود، `transaction_completed`, `payment_terms`).

---

## 1) مرجع فلاتر الحسابات (Acc4) — **اقرأ هذا أولاً**

هذا الجدول هو **مصدر الحقيقة** لأي قائمة/فلتر حسابات في API. لا تستخدم `Acc4::asOptions()` بدون scope في واجهات جديدة.

| الاستخدام في الويب | Scope / Method | SQL المنطقي |
|-------------------|----------------|-------------|
| **حسابات المستوى الرابع** (صفحة الإعدادات) | `Acc4::userCreatedOtherPartyAccounts()` | `item_type IS NULL` AND `acc3_code = '1217'` |
| **كشف الحساب — فلتر الحساب** | `Acc4::userCreatedOtherPartyAccountOptions()` | نفس أعلاه |
| **سند قبض/صرف — طرف آخر (header)** | `Acc4::userCreatedOtherPartyAccountOptions()` | نفس أعلاه |
| **سند — حساب تحصيل (عميل/مورد)** | `Acc4::collectionAccountOptions()` | `code = 120100001` OR `acc3_code = 1227` |
| **سند — حساب دفع لطرف آخر (سطر الدفع)** | `Acc4::voucherOtherEntityPaymentAccountOptions()` | خزينة + بنوك + `1228` + (`1217` AND `editable=true`) |
| **حسابات البنوك** (صفحة منفصلة) | `Acc4::bankAccounts()` | `item_type IS NULL` AND `acc3_code = 1227` |
| **استبعاد أصناف المخزون فقط** | `Acc4::excludeInventoryItems()` | **أوسع** — لا يُستخدم لـ level-four |

**ملف المرجع:** `app/Models/Acc4.php`

### ما يجب إصلاحه في API الحالي (Accounts)

| Endpoint حالي | المشكلة | التصحيح المطلوب |
|---------------|---------|-----------------|
| `GET /v1/tenant/settings/acc4` | يستخدم `excludeInventoryItems()` | **Index للـ other-party:** `userCreatedOtherPartyAccounts()` فقط |
| `POST /v1/tenant/settings/acc4` | يطلب `acc3_code` + `code` يدوياً | مثل `ManageAcc4s`: auto `acc3_code=1217`, `code=nextCodeForAcc3('1217')`, `editable=true`, `deletable=true` |
| `GET .../receipt-vouchers/utils/get-other-entities` | `asOptions(exclude: ...)` واسع جداً | `userCreatedOtherPartyAccountOptions()` |
| `GET .../payment-vouchers/utils/get-other-entities` | نفس المشكلة | نفس التصحيح |
| `GET .../get-credit-accounts` (قبض/صرف/مصروف) | خزينة + بنك فقط | endpoint منفصل لكل scope (انظر §2) |

---

## 2) Accounts API — مواصفة التعديل

### 2.1 قائمة حسابات الأطراف الأخرى (مثل level-four)

**مرجع الويب:** [Acc4Resource](https://client.mybeesystem.com/karam/finance/tree-accounts/level-four)

```
GET /api/v1/tenant/settings/acc4/other-parties
```

**Query:** لا شيء (tenant من header)

**Response 200:**
```json
{
  "data": [
    {
      "code": "1217000001",
      "name": "حساب مصروفات عامة",
      "acc3Code": "1217",
      "editable": true,
      "deletable": true,
      "canEdit": true,
      "canDelete": false
    }
  ]
}
```

**Implementation:**
- Query: `Acc4::query()->userCreatedOtherPartyAccounts()->orderBy('name')`
- `canEdit` / `canDelete`: `$record->canBeEdited()` / `$record->canBeDeleted()`
- **لا تغيّر** `GET /acc4` القديم في v1 — أضف endpoint جديد أو query param اختياري: `?scope=other_parties`

### 2.2 إنشاء حساب طرف آخر

```
POST /api/v1/tenant/settings/acc4/other-parties
```

**Body:**
```json
{ "name": "اسم الحساب" }
```

**Rules (مثل الويب):**
- `name`: required, string
- `UniqueAcc4OtherPartyNameRule` (tenant + `userCreatedOtherPartyAccounts`)

**Server-side (إجباري — لا يرسلها العميل):**
```php
$data['acc3_code'] = '1217';
$data['code'] = Acc4::nextCodeForAcc3('1217');
$data['editable'] = true;
$data['deletable'] = true;
$data['tenant_id'] = $tenantId;
```

### 2.3 تحديث / حذف

```
PATCH /api/v1/tenant/settings/acc4/{code}
DELETE /api/v1/tenant/settings/acc4/{code}
```

- **Update:** فقط `name`، وفقط إذا `$acc4->canBeEdited()`
- **Delete:** فقط إذا `$acc4->canBeDeleted()` (لا معاملات على `cashMovements`)

### 2.4 Utils للسندات (استبدال/توسيع الموجود)

| Endpoint مقترح | يعادل الويب | Method |
|----------------|-------------|--------|
| `GET .../utils/accounts/collection` | تحصيل عميل/مورد | `Acc4::collectionAccountOptions()` |
| `GET .../utils/accounts/other-parties` | طرف آخر — header | `Acc4::userCreatedOtherPartyAccountOptions()` |
| `GET .../utils/accounts/voucher-payments` | سطر دفع طرف آخر | `Acc4::voucherOtherEntityPaymentAccountOptions()` |
| `GET .../utils/accounts/statement` | كشف حساب | `Acc4::userCreatedOtherPartyAccountOptions()` |

**Response shape (موحّد):**
```json
{ "data": { "1217000001": "اسم الحساب", "120100001": "الخزينة" } }
```

**Validation on store (receipt/payment):** عند حفظ السند، تحقق أن `acc4_code` ضمن الـ options المناسبة للـ `for` (customer | supplier | other_entity).

---

## 3) Sales Returns API — مواصفة التعديل

> **📄 مرجع حصرّي ومحدّث:** [`docs/sales-returns-api-spec.md`](sales-returns-api-spec.md) — استخدمه في Cursor لمرتجع المبيعات فقط.

### 3.1 الوضع الحالي vs الويب

| | API الحالي | الويب |
|---|------------|-------|
| أوضاع الإرجاع | فاتورة فقط (`invoice_no`) | `invoice` **أو** `customer` |
| مرتجع لكل فاتورة | **مرة واحدة فقط** (`doesntHave('salesReturns')`) | **متعدد/جزئي** مسموح |
| حقول السطر | `qty` فقط | `price`, `tax`, `discount`, `total` |
| `payment_terms` | ❌ | `cash` \| `credit` + `refund_acc4_code` |
| محاسبة | ❌ | `settleSalesReturnPayment()` + `postSalesReturnAccounting()` |
| `transaction_completed` | ❌ | `true` بعد الإنشاء |
| تعديل البنود | مسموح في API | **مقفول** في الويب (notes فقط) |

**مرجع التنفيذ الويب:**
- `CreateSalesReturns.php` → `afterCreate()`
- `SalesReturnsResource::validateReturnDetailsForCreate()`
- Trait: `InteractsWithInvoiceReturnLineItems` (pricing, expansion, accounting)
- Trait: `HandlesReturnCreditPayments` (credit refund voucher)

### 3.2 استخراج Service مشترك (مطلوب قبل تعديل Controller)

أنشئ مثلاً `App\Services\SalesReturnService` يغلف:

```php
create(array $payload, Tenant $tenant, User $user): SalesReturns
validateForCreate(array $payload): ?string  // returns error message or null
```

**يستدعي داخلياً** (نفس ترتيب الويب):
1. `validateReturnDetailsForCreate($data)` من `SalesReturnsResource`
2. حفظ `SalesReturns` + `details`
3. `SalesReturnsResource::syncExpandedReturnDetails($record, $data, $returnMode, 'sales')`
4. `SalesReturnsResource::settleSalesReturnPayment($record, $data, $returnTotal)`
5. `processPendingReturnCreditRefund('sales', $returnTotal)` (من trait — استخرج إلى service)
6. `$detail->update(['transaction_completed' => true])`

> **مهم:** لا تكرر حسابات الأسعار — استخدم `calculateReturnLineAmounts()` من trait أو انقلها للـ service.

### 3.3 POST create — body جديد (موسّع، backward compatible)

**Endpoint:** `POST /api/v1/tenant/shop/sales-returns`

**Body (invoice mode — يطابق الويب):**
```json
{
  "returnMode": "invoice",
  "invoiceNo": "INV-2024-001",
  "notes": "اختياري",
  "paymentTerms": "cash",
  "pricesIncludesTaxes": true,
  "details": [
    { "invoiceItemId": 15, "qty": 2 }
  ],
  "creditPayment": {
    "accountCode": "120100001",
    "amount": 500.00,
    "date": "2026-08-04",
    "statement": "استرداد جزئي"
  }
}
```

| Field | Rules |
|-------|-------|
| `returnMode` | `invoice` (default) \| `customer` |
| `invoiceNo` | required if `invoice`; exists; status=`confirmed`; not temp |
| `customerId` | required if `customer` |
| `notes` | optional (API القديم required — **خفّف** للتوافق مع الويب) |
| `paymentTerms` | `cash` \| `credit` (default from invoice) |
| `details[].invoiceItemId` | required in invoice mode; must belong to invoice |
| `details[].productLineKey` | required in customer mode (string key من الويب) |
| `details[].qty` | required, > 0, ≤ available |
| `creditPayment.*` | required partially if `paymentTerms=credit`; amount ≤ return total |

**Body (customer mode):**
```json
{
  "returnMode": "customer",
  "customerId": 12,
  "paymentTerms": "credit",
  "details": [
    { "productLineKey": "product_5_unit_1", "qty": 1 }
  ]
}
```

**Backward compatibility:** إذا أُرسل `items` + `invoice_no` (الشكل القديم) — حوّله داخلياً إلى `returnMode=invoice` **لكن** مرّر عبر `SalesReturnService` الكامل (pricing + accounting).

### 3.4 GET helpers — تعديل

#### `GET sales-returns-get-available-invoices`

**الحالي:** فواتير بدون أي مرتجع + `temp=0`  
**المطلوب (مثل الويب):**
- `status = confirmed`
- `temp = false`
- **اسمح** بفواتير لها مرتجعات سابقة (partial returns)
- أرجع أيضاً: `paidAmount`, `returnableAmount`, `customerId`, `customerName`, `paymentTerms`

#### `GET sales-returns-list-invoice-items-for-create/{no}`

**أضف لكل سطر:**
- `returnableQty` (من `getReturnableProductQty` / accessor الويب)
- `unitPrice`, `tax`, `discount` (للعرض)
- `invoiceItemId`

#### `GET sales-returns` (index)

**Bug:** يفلتر على عمود `date` غير موجود — **استبدل** بـ `created_at`  
**Query params:** `invoiceNo`, `customerId`, `fromDate`, `toDate` (format `d-m-Y`)

### 3.5 Response — SalesReturnsResource

**أضف:**
```json
{
  "id": 1,
  "returnMode": "invoice",
  "invoiceNo": "INV-001",
  "invoiceId": 10,
  "customerId": 5,
  "paymentTerms": "cash",
  "refundAcc4Code": null,
  "notes": "...",
  "totalExTax": 100.0,
  "totalTax": 15.0,
  "totalIncTax": 115.0,
  "createdAt": "...",
  "items": [
    {
      "id": 1,
      "invoiceItemId": 15,
      "qty": 2,
      "price": 50.0,
      "tax": 7.5,
      "discount": 0,
      "total": 57.5,
      "transactionCompleted": true,
      "name": "..."
    }
  ]
}
```

**Fix:** لا تفترض `$this->invoice` دائماً — customer returns: `invoiceNo` = null.

### 3.6 PATCH update

**مثل الويب:** تحديث `notes` فقط — **منع** استبدال `items` في API الجديد (أو deprecated مع warning).

### 3.7 DELETE

اتركه كما هو (API فقط) أو أضف flag `canDelete` في response.

### 3.8 GET show

**Route مسجّل بدون method** — أضف `show($id)` يرجع `SalesReturnsResource`.

---

## 4) Subscriptions API — مواصفة جديدة (لا يوجد حالياً)

**مرجع الويب:** صفحة `/subscription` + Livewire `ManageSubscription`

**Services:**
- `SubscriptionPricingService::quote($plan, $billingPeriod)`
- `SubscriptionCouponService::findUsable()`, `applyToQuote()`, `hasActiveCoupons()`
- `Subscription::subscribe($plan, $client, $billingPeriod, $coupon?)`

**Config:** `config/subscription.php` — VAT 15%, yearly = 10 months paid / 2 free

### 4.1 Middleware

**الويب:** `EnsureClientSubscriptionActive` (tenant middleware) — يوجّه لصفحة الاشتراك إذا انتهت التجربة.  
**API المطلوب:** middleware على routes العميل (`ROLE_CLIENT`) يرجع:

```json
HTTP 403
{
  "code": "subscription_trial_expired",
  "message": "...",
  "redirectTo": "subscription"
}
```

**استثناءات:** login, me, plans, subscription/* endpoints.

### 4.2 Endpoints (prefix: `/api/v1/client` — **بدون** tenant header)

#### `GET /api/v1/client/subscription`

```json
{
  "subscription": {
    "planId": 2,
    "billingPeriod": "monthly",
    "startDate": "...",
    "price": 86.25,
    "priceExTax": 75.0,
    "taxAmount": 11.25,
    "taxPercent": 15,
    "couponCode": "SAVE10",
    "discountAmount": 7.5
  },
  "plan": { "code": "business", "name": "...", "enableStore": true, ... },
  "trial": {
    "daysRemaining": 5,
    "expiresAt": "...",
    "expired": false,
    "accountRestricted": false
  },
  "nextBillingDate": "..."
}
```

**Helpers:** `subscription_trial_days_remaining()`, `subscription_account_restricted()`, `ManageSubscription::nextBillingDate()` logic.

#### `GET /api/v1/client/plans`

- `Plan::where('active')->orderBy('sort_order')`
- لكل plan: limits (`max_allowed_*`), `enable_store`, `enable_roles`, `is_featured`, `restrict_account_after_days`
- `quotes.monthly` + `quotes.yearly` من `SubscriptionPricingService::quote()`

#### `POST /api/v1/client/subscription/quote`

**Body:** `{ "planId": 2, "billingPeriod": "yearly", "couponCode": "SAVE10" }`  
**Response:** quote object كامل (+ coupon fields if valid)

#### `POST /api/v1/client/subscription/coupon/validate`

**Body:** `{ "couponCode": "SAVE10", "planId": 2, "billingPeriod": "monthly" }`  
**Errors:** نفس رسائل `SubscriptionCouponService` (invalid, expired, already used)

#### `POST /api/v1/client/subscription/subscribe`

**Body:** `{ "planId": 2, "billingPeriod": "yearly", "couponCode": null }`  
**Action:** `Subscription::subscribe()` — **نفس** `ManageSubscription::updateSubscription()`

#### `GET /api/v1/client/subscription/usage`

```json
{
  "limits": [
    { "type": "sales_invoices", "used": 3, "max": 50, "isMaxed": false },
    { "type": "orders", "used": 10, "max": -1, "isMaxed": false }
  ]
}
```

**Helpers:** `subscription_resource_count()`, `subscription_plan_limit()`, `subscription_resource_maxed_out()`

#### `GET /api/v1/client/subscription/coupons/available`

```json
{ "hasActiveCoupons": true }
```

**Logic:** `SubscriptionCouponService::hasActiveCoupons()` — التطبيق **لا يعرض** حقل الكوبون إلا إذا `true` (مثل الويب).

### 4.3 توسيع `/me` و `/login`

**أضف** (optional block, لا تكسر clients القدامى):

```json
"subscriptionSummary": {
  "planCode": "free",
  "billingPeriod": "monthly",
  "trialExpired": false,
  "trialDaysRemaining": 12
}
```

---

## 5) Platform Coupons (Admin — للعلم فقط)

- Admin CRUD: `/admin/subscription-coupons`
- Model: `PlatformCoupon` — `type`: `percent` | `fixed`, once per client
- **لا تخلط** مع `Coupon` (كوبونات متجر tenant)

---

## 6) خطة تنفيذ مقترحة لـ Cursor (ترتيب العمل)

### المرحلة A — Services (بدون breaking)
- [x] `SalesReturnService` — استخراج من Filament traits
- [x] `Acc4OtherPartyService` أو methods على `Acc4` للـ API responses

### المرحلة B — Accounts (أقل مخاطرة)
- [x] Endpoint `other-parties` + create/update/delete مع scopes
- [x] Utils accounts منفصلة للسندات
- [x] **لا تغيّر** `GET /acc4` — أضف query `?scope=other_parties` أو path جديد

### المرحلة C — Sales Returns
- [x] Wire `POST sales-returns` → `SalesReturnService`
- [x] Fix date filter + available invoices + response fields
- [x] Add `show()`
- [x] Deprecate full `update` items

### المرحلة D — Subscriptions
- [x] Client routes group + resources
- [x] Trial middleware for API
- [x] Extend `me` / `login`

### المرحلة E — Tests
- [ ] Feature test: create sales return with accounting entries
- [ ] Feature test: acc4 other-party create auto-code
- [ ] Feature test: subscribe with platform coupon

---

## 7) Prompt جاهز لـ Cursor (انسخه)

```
Implement mobile API parity per docs/mobile-api-parity-spec.md:

1. Accounts: align list/create/utils with Acc4::userCreatedOtherPartyAccounts() 
   (same as web level-four). Do NOT break existing GET /acc4 — add new endpoints or optional scope.

2. Sales returns: extract SalesReturnService from Filament (InteractsWithInvoiceReturnLineItems, 
   CreateSalesReturns). POST must match web: pricing, payment_terms, accounting, transaction_completed.
   Fix date filter (created_at). Allow partial/multi returns per invoice.

3. Subscriptions: new /api/v1/client/* endpoints mirroring ManageSubscription + 
   SubscriptionPricingService + SubscriptionCouponService. Add API trial middleware.

Rules: reuse existing services/scopes; backward compatible JSON; no unrelated refactors.
```

---

## 8) ملاحظات QA سريعة

| # | سيناريو | النتيجة المتوقعة |
|---|---------|------------------|
| 1 | إنشاء مرتجع مبيعات API (invoice, cash) | `sales_returns_details` فيها price/tax/total ≠ 0 + قيد محاسبي |
| 2 | مرتجع ثانٍ لنفس الفاتورة | ينجح (API لا يرفض) |
| 3 | `GET acc4/other-parties` | نفس عدد/أسماء level-four في الويب |
| 4 | إنشاء حساب API بـ `name` فقط | code يبدأ 1217... |
| 5 | subscribe yearly + coupon 10% | `subscriptions.discount_amount` + redemption مسجّل |
| 6 | trial منتهٍ + API tenant route | 403 subscription_trial_expired |

---

*آخر تحديث: 2026-08-04 — مبني على فرع `local-dev-updates` / الويب الحالي.*
