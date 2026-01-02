<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting || !$setting->value) {
            return $default;
        }

        try {
            return Crypt::decryptString($setting->value);
        } catch (\Exception $e) {
            // If decryption fails, return the raw value (for backwards compatibility)
            return $setting->value;
        }
    }

    public static function set(string $key, ?string $value): void
    {
        $encryptedValue = $value ? Crypt::encryptString($value) : null;
        
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $encryptedValue]
        );
    }

    public static function has(string $key): bool
    {
        $setting = static::where('key', $key)->first();
        return $setting && !empty($setting->value);
    }
}
