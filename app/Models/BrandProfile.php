<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BrandProfile extends Model
{
    protected $fillable = [
        'brand_name',
        'brand_aliases',
        'brand_keywords',
        'business_scope',
        'industries',
        'official_domains',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'brand_aliases' => 'array',
        'brand_keywords' => 'array',
        'industries' => 'array',
        'official_domains' => 'array',
    ];

    public const CACHE_KEY = 'geoflow:brand_profile:singleton_id';

    protected static function booted(): void
    {
        static::saved(static function (self $profile): void {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public static function current(): self
    {
        $id = Cache::get(self::CACHE_KEY);
        if ($id !== null) {
            $cached = static::query()->find($id);
            if ($cached instanceof self) {
                return $cached;
            }
        }

        $profile = static::query()->orderBy('id')->first();
        if ($profile instanceof self) {
            Cache::put(self::CACHE_KEY, $profile->id, now()->addMinutes(10));
        }

        return $profile ?? new self;
    }
}
