<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY_PREFIX = 'settings:';

    public function get(string $key, ?string $default = null): ?string
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $key;
        return Cache::remember($cacheKey, 300, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            return $setting?->value ?? $default;
        });
    }

    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY_PREFIX . $key);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key);
        if ($val === null) return $default;
        return filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}


