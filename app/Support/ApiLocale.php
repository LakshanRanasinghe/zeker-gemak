<?php

namespace App\Support;

use Illuminate\Http\Request;

class ApiLocale
{
    public static function resolve(Request $request): string
    {
        foreach ([
            static::pathLocale($request),
            $request->query('lang'),
            $request->input('lang'),
        ] as $candidate) {
            $locale = static::normalize($candidate);

            if ($locale !== null) {
                return $locale;
            }
        }

        return static::main();
    }

    public static function current(): string
    {
        return static::normalize(app()->getLocale()) ?? static::main();
    }

    public static function main(): string
    {
        return (string) config('app.main_locale', config('app.locale'));
    }

    public static function supported(): array
    {
        $locales = array_keys(config('app.available_locales', []));

        return $locales !== [] ? $locales : [static::main()];
    }

    public static function normalize(mixed $locale): ?string
    {
        if (! is_scalar($locale)) {
            return null;
        }

        $normalized = strtolower(trim((string) $locale));

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace('_', '-', $normalized);
        $normalized = explode('-', $normalized)[0];

        return in_array($normalized, static::supported(), true) ? $normalized : null;
    }

    protected static function pathLocale(Request $request): ?string
    {
        $segments = $request->segments();

        foreach ([0, 1] as $index) {
            $locale = static::normalize($segments[$index] ?? null);

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }
}
