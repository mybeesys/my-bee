<?php

namespace App\Traits;

use App\Models\Batch;
use App\Models\Bid;
use App\Models\BidResult;
use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InsurancePolicy;
use App\Models\Invoice;
use App\Models\ItemStock;
use App\Models\Order;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Models\User;
use Illuminate\Support\Str;

trait HasPrefixedId
{

    protected static $prefix_map = [
        User::class => [
            'attribute' => 'uuid',
            'prefix' => 'U',
            'type' => 'numbers',
            'length' => 6,
        ],
        Customer::class => [
            'attribute' => 'no',
            'prefix' => '',
            'type' => 'numbers',
            'length' => 6,
        ],

        Order::class => [
            'attribute' => 'no',
            'prefix' => '',
            'type' => 'numbers',
            'length' => 8,
        ],

        ItemStock::class => [
            'attribute' => 'no',
            'prefix' => '',
            'type' => 'numbers',
            'length' => 8,
        ],

        PriceOffer::class => [
            'attribute' => 'no',
            'prefix' => '',
            'type' => 'numbers',
            'length' => 8,
        ],
        SupplyOrder::class => [
            'attribute' => 'no',
            'prefix' => '',
            'type' => 'numbers',
            'length' => 8,
        ],

        Invoice::class => [
            'attribute' => 'uid',
            'prefix' => '',
            'type' => 'letters',
            'length' => 12,
        ],
    ];


    public static function bootHasPrefixedId()
    {
        static::creating(function ($model) {
            $attributeName = $model->getAttributeName();
            $model->{$attributeName} = $model->generatePrefixedId();
        });
    }

    protected function generatePrefixedId(): string
    {

        $uid = null;
        $prefix = $this->getPrefix();

        if ($prefix) {
            $uid = $prefix . "-" . $this->getUniquePartForPrefixId();
        } else {
            $uid = $this->getUniquePartForPrefixId();
        }
        $exists = get_class($this)::firstWhere($this->getAttributeName(), $uid);

        while ($exists) {
            if ($prefix) {
                $uid = $prefix . "-" . $this->getUniquePartForPrefixId();
            } else {
                $uid = $this->getUniquePartForPrefixId();
            }

            $exists = get_class($this)::firstWhere($this->getAttributeName(), $uid);
        }

        return $uid;
    }

    protected function getUniquePartForPrefixId(): string
    {
        $uid = null;
        $length = $this->getPrefixLength();

        if ($this->getPrefixType() == "numbers") {
            $uid = $this->randDigits($length);
        } else {
            $uid = Str::upper(Str::random($length));
        }

        return $uid;
    }

    protected function getAttributeName()
    {
        return self::$prefix_map[\get_class($this)]['attribute'] ?? null;
    }

    protected function getPrefix()
    {
        return self::$prefix_map[\get_class($this)]['prefix'] ?? null;
    }

    protected function getPrefixType()
    {
        return self::$prefix_map[\get_class($this)]['type'] ?? 'letters';
    }

    protected function getPrefixLength()
    {
        return self::$prefix_map[\get_class($this)]['length'] ?? 8;
    }


    function randDigits(int $length = 9): int
    {
        return rand(pow(10, $length - 1), pow(10, $length) - 1);
    }
}
