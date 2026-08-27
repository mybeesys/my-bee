# مواصفة API التقارير (Reports) — مواءمة الموبايل مع الويب

> **الغرض:** مرجع حصرّي لشاشات التقارير في لوحة المستأجر — لبناء تطبيق الموبايل بنفس فلاتر وسلوك الويب.  
> **الحالة:** ✅ منفّذ على Laravel — يعيد استخدام نفس خدمات الويب (`IncomeStatementService`, `SalesStatementService`, `InventoryReportService`, `ProductsMovementService`, …).  
> **مرجع عام:** [`docs/mobile-api-parity-spec.md`](mobile-api-parity-spec.md)

---

## 1) المصادقة والـ Headers

```
Authorization: Bearer {token}
Tenant-Id: {tenant_id}
Accept: application/json
Content-Language: ar|en   (اختياري)
```

**Base path:** `/api/v1/tenant/reports/`

**Envelope:**
```json
{
  "statusCode": 200,
  "statusText": "Success",
  "message": "...",
  "data": {},
  "filters": {},
  "errors": [],
  "locale": "ar"
}
```

### صيغة التواريخ

| الاستخدام | الصيغة | مثال |
|-----------|--------|------|
| **الافتراضي للتقارير** | `d-m-Y` عبر `from_date` / `to_date` | `01-01-2026` |
| **Alias مثل DatePicker الويب** | `Y-m-d` عبر `from` / `to` | `2026-01-01` |

الـ API يقبل الاثنين للتقارير المحسوبة (قائمة الدخل / المبيعات / المخزون).  
تقارير `CashDet` تستخدم `from_date` / `to_date` بصيغة `d-m-Y` فقط.

**الافتراضي (مثل الويب):** من بداية السنة الحالية → اليوم.

---

## 2) فهرس التقارير (Catalog)

```
GET /api/v1/tenant/reports/catalog
```

يرجع قائمة التقارير مع `endpoint` و `filtersEndpoint` لكل تقرير، بالإضافة للـ defaults.

**استخدم هذا الـ endpoint لبناء قائمة شاشات التقارير في الموبايل.**

---

## 3) خريطة الويب ↔ API

| # | الويب (Filament) | التسمية AR | API Data | API Filters Meta |
|---|------------------|------------|----------|------------------|
| 1 | `AccountStatementResource` | كشف حساب | `GET .../account/statement/all` | `GET .../filters/account-statement` |
| 2 | `TreasuryAccountReportResource` | الصندوق | `GET .../account/statement/treasury` | `GET .../filters/treasury` |
| 3 | `BankAccountReportResource` | البنك | `GET .../account/statement/bank` | `GET .../filters/bank` |
| 4 | `TaxReportResource` | ضريبة القيمة المضافة | `GET .../account/statement/tax` | `GET .../filters/tax` |
| 5 | `IncomeStatement` page | قائمة الدخل | `GET .../income-statement` | `GET .../filters/income-statement` |
| 6 | `ProductsMovementResource` | حركة المنتجات | `GET .../account/statement/products-movements` | `GET .../filters/products-movements` |
| 7 | `SalesStatementReport` page | تقرير المبيعات | `GET .../sales-statement` | `GET .../filters/sales-statement` |
| 8 | `InventoryDetailReport` page | سجل صنف المخزون | `GET .../inventory/detail` | `GET .../filters/inventory-detail` |
| 9 | `InventorySummaryReport` page | ملخص المخزون | `GET .../inventory/summary` | `GET .../filters/inventory-summary` |

**نمط الموبايل المقترح لكل شاشة:**
1. استدعِ `filters/...` لملء الـ dropdowns.
2. طبّق نفس الحقول الإلزامية/الاختيارية كما في الجدول أدناه.
3. استدعِ endpoint البيانات.

---

## 4) تفاصيل كل تقرير والفلاتر

### 4.1 كشف حساب — Account statement

**ويب:** `app/Filament/Tenant/Resources/AccountStatementResource.php`  
**نطاق الحسابات:** `Acc4::ledgerAccountOptions()` / `scopeLedgerAccounts()`  
يشمل: جهات أخرى (`1217`) + عملاء (`1203`) + موردين (`1214`) + الصندوق (`120100001`) + بنوك (`1227`).

| الفلتر | النوع | إلزامي؟ | ملاحظات |
|--------|-------|---------|---------|
| `account_code` | string (select) | ✅ نعم | بدون اختيار حساب → `data: []` مثل الويب |
| `op_id` | integer | لا | رقم قيد العملية (voucher) |
| `from_date` | `d-m-Y` | لا | يفلتر على `created_at` مثل الويب |
| `to_date` | `d-m-Y` | لا | |

```
GET /api/v1/tenant/reports/account/statement/all?account_code=120300001&from_date=01-01-2026&to_date=26-08-2026
```

**Options:**
```
GET /api/v1/tenant/reports/filters/account-statement
GET /api/v1/tenant/settings/utils/accounts/statement   ← نفس قائمة الحسابات
```

**Response `data`:** مصفوفة أسطر `CashDet`  
**Response `filters.totals`:** `{ debit, credit, count, currency }`

حقول السطر المهمة:
```json
{
  "id": 1,
  "accountCode": "120300001",
  "accountName": "...",
  "voucherNo": "RV-001",
  "opId": 12,
  "date": "2026-08-01",
  "statement": "...",
  "amountIn": "100.00",
  "amountOut": "0.00",
  "debit": 100,
  "credit": 0,
  "inFrom": "الصندوق",
  "outTo": "",
  "balance": "150.00",
  "balanceNumeric": 150,
  "currency": "SAR"
}
```

---

### 4.2 الصندوق — Treasury

**ويب:** `TreasuryAccountReportResource` — scope: `acc3_code = 1201` و `item_type IS NULL`

| الفلتر | النوع | إلزامي؟ |
|--------|-------|---------|
| `account_code` | string | لا |
| `from_date` / `to_date` | `d-m-Y` | لا |

```
GET /api/v1/tenant/reports/account/statement/treasury?from_date=01-01-2026&to_date=26-08-2026
```

---

### 4.3 البنك — Bank

**ويب:** `BankAccountReportResource` — scope: `acc3_code = 1227` و `item_type IS NULL`

| الفلتر | النوع | إلزامي؟ |
|--------|-------|---------|
| `account_code` | select (حسابات البنك) | لا |
| `op_id` | integer | لا |
| `from_date` / `to_date` | `d-m-Y` | لا |

```
GET /api/v1/tenant/reports/account/statement/bank?account_code=122700001&op_id=5
```

---

### 4.4 ضريبة القيمة المضافة — Tax

**ويب:** `TaxReportResource` — scope: `acc3_code = 1228`  
⚠️ تم إصلاح API السابق الذي كان يفلتر خطأً على `1201` (الصندوق).

| الفلتر | النوع | إلزامي؟ |
|--------|-------|---------|
| `account_code` | string | لا |
| `op_id` | integer | لا |
| `transaction_id` | string | لا |
| `from_date` / `to_date` | `d-m-Y` | لا |

```
GET /api/v1/tenant/reports/account/statement/tax?from_date=01-01-2026&to_date=26-08-2026&transaction_id=TX-...
```

---

### 4.5 قائمة الدخل — Income statement

**ويب:** `app/Filament/Tenant/Pages/IncomeStatement.php`  
**خدمة:** `IncomeStatementService::build($from, $to)`  
أساس تشغيلي: فواتير مبيعات/مشتريات مؤكدة − مردودات − مصروفات.

| الفلتر | النوع | إلزامي؟ | Alias |
|--------|-------|---------|-------|
| `from_date` | `d-m-Y` | لا (افتراضي بداية السنة) | `from` = `Y-m-d` |
| `to_date` | `d-m-Y` | لا (افتراضي اليوم) | `to` = `Y-m-d` |

```
GET /api/v1/tenant/reports/income-statement?from_date=01-01-2026&to_date=26-08-2026
GET /api/v1/tenant/reports/income-statement?from=2026-01-01&to=2026-08-26
```

**Response (ملخص):**
```json
{
  "from": "2026-01-01",
  "to": "2026-08-26",
  "currency": "SAR",
  "salesTotal": 10000,
  "purchasesTotal": 4000,
  "expensesTotal": 1500,
  "netIncome": 4500,
  "sections": {
    "sales": { "label": "المبيعات", "total": 10000, "lines": [...] },
    "purchases": { "label": "المشتريات", "total": 4000, "lines": [...] },
    "expenses": { "label": "المصروفات", "total": 1500, "lines": [...] }
  },
  "summary": {
    "netIncomeLabel": "صافي الربح / الخسارة (...)",
    "netIncome": 4500
  }
}
```

---

### 4.6 حركة المنتجات — Products movement

**ويب:** `ProductsMovementResource`  
**خدمة:** `ProductsMovementService` + `ProductMovementBalanceService`

| الفلتر | النوع | القيم / ملاحظات |
|--------|-------|-----------------|
| `type` | select | `purchases` \| `sales` \| `sales_return` \| `purchase_return` |
| `customers` | multi int | IDs العملاء |
| `suppliers` | multi int | IDs الموردين |
| `products` | multi int | IDs المنتجات |
| `invoices` | multi int | IDs الفواتير (مثل الويب) |
| `invoice_no` | string | بديل: رقم فاتورة واحد يُحوَّل إلى IDs |
| `from_date` / `to_date` | `d-m-Y` | على تاريخ الحركة |

```
GET /api/v1/tenant/reports/account/statement/products-movements?type=sales&customers[]=1&products[]=3&from_date=01-01-2026&to_date=26-08-2026
```

**Meta options:** `GET .../filters/products-movements` → `types`, `customers`, `suppliers`, `products`

---

### 4.7 تقرير المبيعات — Sales statement

**ويب:** `SalesStatementReport`  
**خدمة:** `SalesStatementService::build()`

| الفلتر | النوع | إلزامي؟ | القيم |
|--------|-------|---------|-------|
| `from_date` / `to_date` (أو `from`/`to`) | date | ✅ عملياً (افتراضي YTD إن غاب) | |
| `group_by` | select | لا | `product` (افتراضي) \| `invoice` |
| `line_types` | multi | لا | `sale` \| `return` — فارغ = الكل |
| `customer_ids` | multi int | لا | |
| `product_ids` | multi int | لا | |

```
GET /api/v1/tenant/reports/sales-statement?from=2026-01-01&to=2026-08-26&group_by=product&line_types[]=sale&customer_ids[]=2
```

**Response:**
```json
{
  "filters": { "from": "...", "to": "...", "groupBy": "product", ... },
  "stats": {
    "invoicesCount": 10,
    "salesQty": 50,
    "returnsQty": 2,
    "netQty": 48,
    "grossTotal": 10000,
    "returnsTotal": 200,
    "netTotal": 9800,
    "currency": "SAR"
  },
  "lines": [ /* تفصيل منتج أو ملخص فاتورة حسب group_by */ ]
}
```

---

### 4.8 سجل صنف المخزون — Inventory detail

**ويب:** `InventoryDetailReport`  
**خدمة:** `InventoryReportService::buildDetail()`

| الفلتر | النوع | إلزامي؟ |
|--------|-------|---------|
| `product_id` | int (select) | ✅ |
| `warehouse_id` | int (select) | ✅ |
| `from_date` / `to_date` أو `from`/`to` | date | لا (افتراضي YTD) |
| `movement_types` | multi | لا — `purchase`, `sales`, `purchase_return`, `sales_return` |

```
GET /api/v1/tenant/reports/inventory/detail?product_id=5&warehouse_id=1&from=2026-01-01&to=2026-08-26&movement_types[]=sales
```

**Response:** `{ filters, product, warehouse, stats, lines }`  
كل سطر يتضمن كمية الحركة والرصيد بعد الحركة (`balanceAfter`) مثل الويب.

---

### 4.9 ملخص المخزون — Inventory summary

**ويب:** `InventorySummaryReport` (مخفي من الناف لكن موجود)  
**خدمة:** `InventoryReportService::buildSummary()`

| الفلتر | النوع | إلزامي؟ |
|--------|-------|---------|
| `from` / `to` أو `from_date` / `to_date` | date | لا |
| `warehouse_ids` | multi int | لا |
| `product_ids` | multi int | لا |
| `movement_types` | multi | لا — يشمل أيضاً `opening`, `transfer_in`, `transfer_out` |

```
GET /api/v1/tenant/reports/inventory/summary?from=2026-01-01&to=2026-08-26&warehouse_ids[]=1
```

**Response rows (أمثلة حقول):**
`productId`, `warehouseId`, `sku`, `productName`, `warehouseName`, `openingInventory`, `purchasedQuantity`, `salesQuantity`, `purchaseReturns`, `salesReturns`, `transferredQuantity`, `transferredOutQuantity`, `quantityOnInventory`

**تنقل للتفصيل:** من صف الملخص افتح  
`/inventory/detail?product_id={id}&warehouse_id={id}&from=...&to=...`  
(نفس سلوك زر التفاصيل في الويب).

---

## 5) قيم أنواع حركة المخزون (ثابتة)

| value | AR |
|-------|----|
| `opening` | مخزون افتتاحي |
| `purchase` | شراء |
| `sales` | بيع |
| `transfer_in` | تحويل وارد |
| `transfer_out` | تحويل صادر |
| `purchase_return` | مردود مشتريات |
| `sales_return` | مردود مبيعات |

---

## 6) ملفات المصدر (لا تكرّر المنطق)

| الطبقة | المسار |
|--------|--------|
| Controller | `app/Http/Controllers/API/V1/ReportController.php` |
| CashDet query | `app/Services/CashDetReportQueryService.php` |
| Services | `IncomeStatementService`, `SalesStatementService`, `InventoryReportService`, `ProductsMovementService` |
| Requests | `app/Http/Requests/*ReportRequest.php`, `ListCashDetReportRequest` |
| Resource | `app/Http/Resources/CashDetReportResource.php`, `ProductsMovementResource.php` |
| Routes | `routes/api.php` → group `reports` |
| Web sources of truth | `app/Filament/Tenant/Resources/*Report*`, `app/Filament/Tenant/Pages/*Report*.php`, `IncomeStatement.php` |

---

## 7) Checklist مواءمة الموبايل مع الويب

- [ ] قائمة التقارير من `GET .../reports/catalog`
- [ ] كل شاشة تجلب خيارات الفلتر من `GET .../reports/filters/{key}`
- [ ] كشف الحساب لا يعرض بيانات قبل اختيار `account_code`
- [ ] حسابات كشف الحساب = جهات أخرى + عملاء + موردين + صندوق + بنك
- [ ] تقرير الضريبة على حسابات `1228` وليس الصندوق
- [ ] تواريخ CashDet تُرسل `d-m-Y` وتُفلتر مثل الويب
- [ ] قائمة الدخل / المبيعات / المخزون تستخدم نفس خدمات الويب (لا منطق محلي في التطبيق)
- [ ] تقرير المبيعات يدعم `group_by=product|invoice` و `line_types`
- [ ] سجل المخزون يطلب `product_id` + `warehouse_id`
- [ ] من ملخص المخزون يمكن فتح التفصيل بنفس الـ query params

---

## 8) ملاحظات تقنية مهمة

1. **Totals في تقارير CashDet** تظهر داخل مفتاح الاستجابة `filters.totals` (مع `filters.applied`) للحفاظ على أن `data` تبقى مصفوفة الأسطر (توافق خلفي).
2. **لا تُبنَ فلاتر ثابتة في الموبايل** لأن أسماء الحسابات/المنتجات تتغير — استخدم endpoints الـ filters.
3. **كشف حساب عميل/مورد منفرد** موجود مسبقاً خارج مجموعة التقارير:
   - `GET .../shop/clients/{id}/account-statement`
   - `GET .../shop/suppliers/{id}/account-statement`
4. إصلاحات ضمن هذه المواصفة مقابل API القديم:
   - Tax: `acc3_code` من `1201` → `1228`
   - Bank/Treasury: إضافة شرط `item_type IS NULL`
   - Account statement: نطاق `ledgerAccounts` + اشتراط `account_code`
   - Products movements: دعم `invoices[]` مثل الويب
   - إضافة: income / sales / inventory detail & summary + catalog + filters meta

---

*آخر تحديث: 2026-08-26 — مبني على شاشات Filament Tenant لمجموعة «التقارير».*
