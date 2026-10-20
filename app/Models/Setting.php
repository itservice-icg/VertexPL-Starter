<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'label',
        'group',
        'type',
        'value',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget("setting.{$setting->key}"));
        static::deleted(fn (Setting $setting) => Cache::forget("setting.{$setting->key}"));
    }

    public static function value(string $key, $default = null)
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();

            return $setting ? $setting->resolvedValue() : $default;
        });
    }

    public function resolvedValue()
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => $this->value === '1',
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
