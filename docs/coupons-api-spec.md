# مواصفة API كوبونات المتجر (Shop Coupons)

> **الغرض:** مرجع حصرّي لشاشة **كوبونات المتجر الإلكتروني** — مواءمة الموبايل مع الويب 100%، ولـ Cursor.  
> **الحالة:** ✅ منفّذ على Laravel.  
> **مرجع الويب:** [`https://client.mybeesystem.com/{tenant}/shop/coupons`](https://client.mybeesystem.com/karam/shop/coupons)  
> **الطلبات (استخدام الكوبون):** [`docs/orders-api-spec.md`](orders-api-spec.md)  
> **مرجع عام:** [`docs/mobile-api-parity-spec.md`](mobile-api-parity-spec.md)

**مهم:** هذا **كوبون المتجر** (`coupons` table) — **ليس** كوبونات اشتراك المنصة (`/api/v1/client/subscription/coupon/*`).

---

## 1) مرجع الويب (مصدر الحقيقة)

| الملف | الدور |
|-------|--------|
| `app/Filament/Tenant/Resources/CouponResource.php` | قائمة + فورم إنشاء/تعديل |
| `.../Pages/ListCoupons.php` | قائمة + إنشاء |
| `.../Pages/CreateCoupon.php` | إنشاء |
| `.../Pages/EditCoupon.php` | تعديل |
| `CouponService` | منطق CRUD + تحقق checkout |
| `StoreController` | تطبيق الكوبون في السلة (`apply-coupon`) |

**الوصول:** يتطلب `plan_allows_store()` — نفس شرط الويب.

---

## 2) الفرق: API القديم vs الويب + API الحالي

| الموضوع | API القديم | الويب + API الحالي |
|---------|------------|---------------------|
| إدارة الكوبونات | ❌ لا CRUD | **GET/POST/PATCH coupons** |
| checkout | `POST store/apply-coupon` | ما زال يعمل (للعميل) |
| حقول الفورم | — | code, span, type, amount/percent, active, description |
| usages | — | `usagesCount` من الطلبات |
| حذف | — | ❌ لا حذف (403) |

---

## 3) Headers و Base

```
Authorization: Bearer {token}
Tenant-Id: {tenant_id}
Accept: application/json
```

**Base:** `/api/v1/tenant/shop/`

**Body:** snake_case. **JSON:** camelCase.

---

## 4) المسارات

| Method | Path | الوصف |
|--------|------|--------|
| GET | `coupons` | قائمة |
| POST | `coupons` | إنشاء |
| GET | `coupons/{id}` | تفاصيل |
| PATCH | `coupons/{id}` | تعديل |
| DELETE | `coupons/{id}` | دائماً 403 |
| GET | `coupons/prefill` | افتراضيات الفورم |
| GET | `coupons/form-options` | خيارات span/type مع الترجمة |

---

## 5) قائمة `GET coupons`

**Query:** `search`, `active`, `span`, `type`, `include_summaries`, `paginate`, `sort`

**`filters.listSummaries`:** `{ count, activeCount, usagesCount }`

**عنصر:**

```json
{
  "id": 1,
  "code": "SAVE10",
  "span": "specified-time",
  "spanLabel": "صالح لفترة زمنية معينة",
  "type": "percent",
  "typeLabel": "نسبة",
  "value": 10,
  "percent": 10,
  "amount": null,
  "valueFormatted": "10%",
  "active": true,
  "status": "active",
  "validUntil": "2026-09-23",
  "validUntilFormatted": "23-09-2026",
  "usagesCount": 3,
  "description": "<p>خصم ترحيبي</p>",
  "actions": { "canEdit": true, "canDelete": false }
}
```

**`status`:** `active` | `inactive` | `expired` | `used` (one-time + استُخدم)

---

## 6) إنشاء `POST coupons`

```json
{
  "code": "SAVE10",
  "span": "specified-time",
  "valid_until": "2026-09-23",
  "type": "percent",
  "percent": 10,
  "active": true,
  "description": "خصم 10%"
}
```

### قيم `span`

| القيمة | `valid_until` |
|--------|---------------|
| `one-time` | مطلوب |
| `specified-time` | مطلوب |
| `unlimited-time` | **لا ترسل** — يُحفظ `null` |

### قيم `type`

| القيمة | الحقل المطلوب |
|--------|---------------|
| `percent` | `percent` (1–99) |
| `fixed` | `amount` (≥ 1) |

---

## 7) تعديل `PATCH coupons/{id}`

نفس حقول الإنشاء (كلها optional). عند `span=unlimited-time` يُصفّر `validUntil`.

---

## 8) Prefill و Form Options

```http
GET /api/v1/tenant/shop/coupons/prefill
```

```json
{
  "type": "percent",
  "span": "specified-time",
  "active": true,
  "percent": 1,
  "validUntil": "2026-09-23"
}
```

```http
GET /api/v1/tenant/shop/coupons/form-options
```

→ `spans[]`, `types[]` مع `value` + `label` مترجمة.

---

## 9) تطبيق الكوبون في المتجر (checkout — للعميل)

هذه **ليست** شاشة إدارة الكوبونات، لكنها مرتبطة:

```http
POST /api/v1/store/apply-coupon
Header: Store-UUID, Store-Slug
Body: { "coupon": "SAVE10" }
```

```http
POST /api/v1/store/clear-coupon
```

---

## 10) Cursor prompt — شاشة الموبايل

```
Implement Shop Coupons admin screens matching docs/coupons-api-spec.md:

1. Gate screen on plan_allows_store — show upgrade message if store_not_available_on_plan
2. List: GET shop/coupons with search + filters.listSummaries
3. Create/Edit form fields exactly like web CouponResource:
   - code (unique per tenant)
   - span: one-time | specified-time | unlimited-time
   - valid_until (hidden when unlimited-time)
   - type: fixed | percent with amount OR percent field
   - active toggle
   - description (rich text / HTML)
4. Prefill on create: GET shop/coupons/prefill
5. Dropdown labels: GET shop/coupons/form-options
6. Show usagesCount column like web table
7. No delete button (canDelete=false)
8. snake_case body, camelCase JSON, dates Y-m-d
```

---

**آخر تحديث:** 2026-08-23
