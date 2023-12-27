<?php


namespace App\Services;


use App\Helpers\CacheManager;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingService
{

    public Collection $settings;
    protected $result = null;
    public $tenant_id = null;

    public function __construct($tenant_id)
    {
        $this->tenant_id = $tenant_id;
    }

    public static function instance($tenant_id): SettingService
    {
        return new self($tenant_id);
    }

    public function cached(): bool
    {
        $tenant = $this->tenant_id ? "@$this->tenant_id" : "";
        return Cache::has("settings$tenant");
    }

    public function count(): int
    {
        $tenant = $this->tenant_id ? "@$this->tenant_id" : "";

        $settings = Cache::get("settings$tenant");
        return $settings === null ? 0 : $settings->count();
    }

    public function refresh()
    {
        $tenant = $this->tenant_id ? "@$this->tenant_id" : "";

        $this->settings = Cache::rememberForever("settings$tenant", function () {
            return $this->all();
        });
    }

    public function createOrUpdate(string $key, array $display_name, $value, string $type = 'text',
                                   bool   $deletable = false, array $rules = [], $group = null, array $options = [],
                                   bool   $is_password = false, $tab = null, $placeholder = null, $helper_text = null,
                                          $sort = 1, $tab_sort = 1, $visible_in_user_friendly_settings = true, $getOptionsFromCacheKey = null)
    {
//        if ($type != "bool" and ($value == "" or $value == null))
//            $value = "default";

        $validator = \Illuminate\Support\Facades\Validator::make(
            [
                'key' => $key,
                'value' => $value,
                'display_name' => $display_name,
                'type' => $type,
                'deletable' => $deletable,
                'rules' => $rules["value"] ?? null,
                'group' => $group,
                'options' => $options,
                'def_val' => $value,
                'sort' => $sort,
                'tab_sort' => $tab_sort,
                'tab' => $tab,
                'placeholder' => $placeholder,
                'helper_text' => $helper_text,
                'visible_in_user_friendly_settings' => $visible_in_user_friendly_settings,
                'options_cache_key' => $getOptionsFromCacheKey,
            ],
            array_merge($rules, ['type' => 'in:text,rich-text,text-area,bool,file,options,products-discount'])
        );

        if ($validator->passes()) {
            $setting = Setting::updateOrCreate(
                [
                    'key' => $key,
                    'tenant_id' => $this->tenant_id,
                ],
                [
                    'tenant_id' => $this->tenant_id,
                    'key' => $key,
                    'value' => $value,
                    'display_name' => $display_name,
                    'type' => $type,
                    'rules' => $rules["value"] ?? null,
                    'group' => $group,
                    'options' => $options,
                    'deletable' => $deletable,
                    'is_password' => $is_password,
                    'def_val' => $value,
                    'tab' => $tab,
                    'placeholder' => $placeholder,
                    'helper_text' => $helper_text,
                    'visible_in_user_friendly_settings' => $visible_in_user_friendly_settings,
                    'options_cache_key' => $getOptionsFromCacheKey,
                ]);
            return $setting;
        } else {
//            dd($key, $rules);
            throw new \Exception($validator->errors()->first() ." - ". $key);
        }
    }

    public function findByKey($key)
    {
        $this->result = $this->settings->firstWhere('key', $key);
        return $this;
    }

    public function findByGroup(array $groups)
    {
        $this->result = settings_by_group($groups);
//        $this->refresh();
//        $this->result = Setting::whereIn('group', $groups)->get();
        return $this;
    }

    public function asOptions()
    {
        return $this->result->options ?? [];
    }

    public function selectedOption($value = false, $default = "")
    {
        if ($value) {
            return $this->result->options[$this->result->value] ?? $default;
        }
        return $this->result->value ?? $default;
    }

    public function get($default = "")
    {
        return $this->result ? $this->result : $default;
    }

    public function rules()
    {
        return [
            'required',
            'string',
            'integer',
            'boolean',
            'numeric',
            'email',
            'digits',
            'file',
            'sometimes',
            'date',
        ];
    }

    public function rulesForBoolean($required = true)
    {
        if ($required)
            return [
                "value" => ["required", "boolean"]
            ];

        return [
            "value" => ["nullable", "boolean"]
        ];
    }

    public function rulesForString($required = true, $maxLength = 255, $endsWith = null)
    {
        $rules = ["string"];

        if ($required)
            $rules[] = "required";
        else
            $rules[] = "nullable";

        if ($maxLength)
            $rules[] = "max:$maxLength";

        if ($endsWith)
            $rules[] = "ends_with:$endsWith";

        return [
            "value" => $rules
        ];
    }

    public function rulesForURL($required = true)
    {
        if ($required)
            return [
                "value" => ["required", "url:http,https", "max:255"]
            ];
        return [
            "value" => ["nullable", "url:http,https", "max:255"]
        ];
    }

    public function rulesForText($required = true)
    {
        if ($required)
            return [
                "value" => ["required", "string", "max:60000"]
            ];

        return [
            "value" => ["nullable", "string", "max:60000"]
        ];
    }

    public function rulesForEmail($required = true)
    {
        if ($required)
            return [
                "value" => ["required", "email"]
            ];

        return [
            "value" => ["nullable", "email"]
        ];
    }


    public function rulesForInternationalPhone($required = true)
    {
        if ($required)
            return [
                "value" => ["required", "phone:INTERNATIONAL"]
            ];

        return [
            "value" => ["nullable", "phone:INTERNATIONAL"]
        ];
    }

    public function rulesForNumber($required = true, $min = 1, $max = 500000)
    {
        if ($required)
            return [
                "value" => ["required", "numeric", "min:$min", "max:$max"]
            ];

        return [
            "value" => ["nullable", "numeric", "min:$min", "max:$max"]
        ];
    }

    public function rulesForDayOfTheMonth($required = true)
    {
        if ($required)
            return [
                "value" => ["required", "numeric", "min:1", "max:28"]
            ];

        return [
            "value" => ["nullable", "numeric", "min:1", "max:28"]
        ];
    }

    public function rulesForPercent($required = true)
    {
        if ($required)
            return [
                "value" => ["required", "numeric", "min:1", "max:100"]
            ];

        return [
            "value" => ["nullable", "numeric", "min:1", "max:100"]
        ];
    }

    public function rulesForDate()
    {
        return [
            "value" => ["required", Rule::in([
                'Mon, 25 Sep',
                'Mon, 25 Sep, 2022',
                'Monday, 25 September',
                '25, September, 2022',
                '25, 06, 2022',
                'Mon, 25 Sep, 3:38 PM',
                'Mon, 25 Sep, 2022, 3:38 PM',
                'Monday, 25 September, 3:38 PM',
                '25, September, 2022, 3:38 PM',
                '25, 06, 2022, 3:38 PM',
            ])]
        ];
    }

    public function dateFormats($asSelect = true): array
    {
        // l jS \of F Y h:i:s A      Prints something like: Monday 8th of August 2005 03:12:46 PM
        // F j, Y, g:i a             March 10, 2001, 5:16 pm

        if ($asSelect)
            return [
                'M j, Y g:i:s a' => 'October 7, 2005 5:16:22 pm',
                'M j, Y g:i a' => 'October 7, 2005 5:16 pm',
                'F j, Y, g:i:s a' => 'August 10, 2005, 5:16:22 pm',
                'F j, Y, g:i a' => 'August 10, 2005, 5:16 pm',
                'l jS \of F Y h:i:s A' => 'Monday 8th of August 2005 5:16:22 PM',
                'l jS \of F Y h:i A' => 'Monday 8th of August 2005 5:16 PM',
                'd/m/Y' => '17/05/2005',
                'd/m/Y g:i:s a' => '17/05/2005 5:16:22 pm',
                'd/m/Y g:i a' => '17/05/2005 5:16 pm',
                'd-m-Y' => '17-05-2005',
                'd-m-Y g:i:s a' => '17-05-2005 5:16:22 pm',
                'd-m-Y g:i a' => '17-05-2005 5:16 pm',
            ];

        return [
            'M j, Y g:i:s a',
            'M j, Y g:i a',
            'F j, Y, g:i:s a',
            'F j, Y, g:i a',
            'l jS \of F Y h:i:s A',
            'l jS \of F Y h:i A',
            'd/m/Y',
            'd/m/Y g:i:s a',
            'd/m/Y g:i a',
            'd-m-Y',
            'd-m-Y g:i:s a',
            'd-m-Y g:i a',
        ];
    }
}
