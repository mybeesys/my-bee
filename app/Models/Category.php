<?php

    namespace App\Models;

    use App\Traits\HasPrefixedId;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Spatie\Sluggable\HasSlug;
    use Spatie\Sluggable\HasTranslatableSlug;
    use Spatie\Sluggable\SlugOptions;
    use Spatie\Translatable\HasTranslations;

    class Category extends BaseModel
    {
        use HasFactory, HasTranslatableSlug, HasTranslations;

        public $translatable = ['name', 'description', 'slug'];

        protected $guarded = [];

        protected $casts = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];

        public function getSlugOptions(): SlugOptions
        {
            return SlugOptions::create()
                ->generateSlugsFrom('name')
                ->saveSlugsTo('slug');
        }

        public function scopeMain($query): Builder
        {
            return $query->where('parent_id', null)->orderBy('sort');
        }

        public function scopeSlug($query, $slug)
        {
            return $query->where('slug->' . app()->getLocale(), $slug);
        }

        public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            return $this->hasMany(self::class, 'parent_id', 'id');
        }

        public function allChildren()
        {
            return $this->children()->with('allChildren');
        }

        public function path($glue = '')
        {
            if (!$this->relationLoaded('parent'))
                $this->loadMissing('parent');

            $path = [];
            $parent = $this->parent;
            //add home

            array_push($path,
                [
                    'name' => trans('ui.home'),
                    'route' => route('website.marketplace.shop')
                ]
            );

            while ($parent) {
                array_push($path,
                    [
                        'name' => $parent->name,
                        'route' => route('website.marketplace.shop', implode("/", $parent->retreiveSlugsAsArray()))
                    ]
                );

                $parent->loadMissing('parent');
                $parent = $parent->parent;
            }

            //add current
            array_push($path,
                [
                    'name' => $this->name,
                    'route' => route('website.marketplace.shop', implode("/", $this->retreiveSlugsAsArray()))
                ]
            );

            return $glue == '' ? $path : implode($glue, collect($path)->pluck('name')->toArray());
        }

        public function retreiveSlugsAsArray()
        {
            if (!$this->relationLoaded('parent'))
                $this->loadMissing('parent');

            $path = [];
            $parent = $this->parent;
            while ($parent) {
                $path[] = $parent->slug;

                $parent->loadMissing('parent');
                $parent = $parent->parent;
            }

            //add current slug
            $path[] = $this->slug;
            return $path;
        }

        public function parent()
        {
            return $this->belongsTo(self::class, 'parent_id');
        }

        public function scopeCanListProduct(Builder $query)
        {
            return $query->has('children', '=', 0);
        }

        public function scopeCanBecomeParent(Builder $query, $ignore_ids = [])
        {
            return $query->whereNotIn('id', $ignore_ids)->has('products', '=', 0);
        }

        public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            return $this->hasMany(Product::class);
        }

        public function localizedName()
        {
            return $this->getTranslation('name', 'en') . " - " . $this->getTranslation('name', 'ar');
        }
    }
