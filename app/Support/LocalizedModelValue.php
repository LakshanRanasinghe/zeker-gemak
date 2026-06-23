<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class LocalizedModelValue
{
    public static function get(Model $model, string $field, mixed $fallback = null, ?string $locale = null): mixed
    {
        $locale ??= ApiLocale::current();
        $fallback ??= static::modelFallback($model, $field);

        if ($locale === ApiLocale::main()) {
            return static::normalize($fallback);
        }

        $translated = static::translated($model, $field, $locale);

        return static::hasValue($translated) ? static::normalize($translated) : static::normalize($fallback);
    }

    public static function string(Model $model, string $field, ?string $fallback = null, ?string $locale = null): ?string
    {
        $value = static::get($model, $field, $fallback, $locale);

        return $value !== null ? (string) $value : null;
    }

    public static function hasValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }

    protected static function translated(Model $model, string $field, string $locale): mixed
    {
        if (method_exists($model, 'getTranslationIn')) {
            $translation = $model->getTranslationIn($locale);

            if ($translation) {
                return $translation->getTranslatedField($field);
            }
        }

        return _mt($model, $field, $locale);
    }

    protected static function modelFallback(Model $model, string $field): mixed
    {
        if (method_exists($model, 'getRawOriginal')) {
            return $model->getRawOriginal($field);
        }

        return $model->getAttribute($field);
    }

    protected static function normalize(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
