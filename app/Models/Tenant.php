<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Models\Role;

class Tenant extends BaseModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'store_social_media_links' => 'array',
        'store_theme' => 'array',
        'store_hide_out_of_stock_products' => 'boolean',
        'store_enable_orders_tracking' => 'boolean',
        'store_enable_stock_tracking' => 'boolean',
    ];

    protected $with = ['media'];

    public const TYPE_INDIVIDUAL = "individual";
    public const TYPE_COMPANY = "company";

    public function getStoreTitleAttribute()
    {
        return app()->getLocale() == "ar" ? ($this->store_title_ar ?? $this->name) : ($this->store_title_en ?? $this->name);
    }

    public function getStoreBioAttribute()
    {
        return app()->getLocale() == "ar" ? $this->store_bio_ar : $this->store_bio_en;
    }

    public function getStoreAddressAttribute()
    {
        return app()->getLocale() == "ar" ? $this->store_address_ar : $this->store_address_en;
    }

    public function getStoreWorkingHoursAttribute()
    {
        return app()->getLocale() == "ar" ? $this->store_working_hours_ar : $this->store_working_hours_en;
    }

    public function setPhoneAttribute($value)
    {
        return $this->attributes['phone'] = str($value)->remove('+')->value();
    }

    public function setMobileAttribute($value)
    {
        return $this->attributes['mobile'] = str($value)->remove('+')->value();
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id');
    }


//tenant data here

    public function settings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function acc1s(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Acc1::class);
    }

    public function acc2s(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Acc2::class);
    }

    public function acc3s(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Acc3::class);
    }

    public function acc4s(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Acc4::class);
    }


    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function units(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function suppliers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function warehouses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function taxProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaxProfile::class);
    }

    public function expenseCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function customers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function paymentVouchers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentVoucher::class);
    }

    public function receiptVouchers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReceiptVoucher::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function coupons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function priceOffers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceOffer::class);
    }

    public function supplyOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupplyOrder::class);
    }

    public function purchasesReturns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasesReturns::class);
    }

    public function salesReturns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesReturns::class);
    }

    public function variantLibraries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VariantLibrary::class);
    }

    public function workflows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function getCoverAttribute()
    {
        return $this->getFirstMedia('covers')?->getUrl() ?? config('app.url') . "mybee_transparent.png";
    }
}
