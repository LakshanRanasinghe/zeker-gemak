<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Storefront (Frontend) URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the Next.js storefront. Used to build canonical product
    | page links — e.g. the "Printer URL" that connects a product finder
    | entry to its actual product page.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'https://businesslabels.nl'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'admin_emails' => explode(',', env('ADMIN_EMAILS', 'info@dayzsolutions.com')),

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'currency' => 'EUR',
    'currency_symbol' => '€',
    'main_locale' => env('APP_LOCALE', 'nl'),
    'available_locales' => [
        'en' => 'English',
        'nl' => 'Dutch',
    ],

    // Material Configs
    'material_brands' => [
        'CREATIVE' => 'Creative',
        'DIAMOND' => 'Diamondlabels',
        'EPSON' => 'Epson',
        'EXPOBADGE' => 'ExpoBadge',
        'PURELABELS' => 'Pure Labels',
        'SEIKO' => 'Seiko',
        'ZEBRA' => 'Zebra',
    ],
    'material_print_method' => [
        'thermal_direct' => 'Thermal Direct',
        'thermal_transfer' => 'Thermal Transfer',
        'water_based_inkjet' => 'Water based Inkjet',
    ],
    'material_base_material' => [
        'PE_150' => '150 mu PE (polyethylene)',
        'PP_155' => '155 mu PP (polypropylene)',
        'PP_350' => '350 mu PP (polypropylene)',
        'GRASS' => 'Grass paper',
        'HDPE' => 'HDPE',
        'PAPER' => 'Paper',
        'PE' => 'Polyethylene',
        'PE_DEST' => 'PE Destructible',
        'PET' => 'PET',
        'PO' => 'PO(Polyolefin)',
        'PP' => 'PP (Polypropylene)',
        'PP_PAPER' => 'PP front, paper back',
        'SANDWICH' => 'Sandwich, paper on laminate',
        'TEAR_PROOF' => 'Tear-proof paper',
        'VINYL' => 'Vinyl',
        'VOID' => 'VOID',
    ],
    'material_finish' => [
        'BLUE' => 'Blue',
        'ECO_COATED' => 'Eco Coated',
        'GLOSS' => 'Glossy',
        'GLOSS_SILVER' => 'Glossy Silver',
        'GLOSS_TOPCOAT' => 'Glossy, topcoated',
        'INKJET_COATED' => 'Inkjet coated',
        'MACHINE_COATED' => 'Machine coated',
        'MAT' => 'Matte',
        'MAT_SILVER' => 'Matte silver',
        'NONE' => 'None',
        'ORANGE' => 'Orange',
        'RED' => 'Red',
        'SATIN_GLOSS' => 'Satin gloss',
        'SILICONE_COATED' => 'Silicone coated',
        'STRUCT_MAT' => 'Structured matte',
        'TOP_COATED' => 'Top coated',
        'TRANSPARENT' => 'Transparent',
        'UNCOATED' => 'Uncoated',
        'WOOD_MAT' => 'Wood structure matte',
        'YELLOW' => 'Yellow',
    ],
    'material_adhesive' => [
        'DEEPFREEZE' => 'Deep freeze',
        'EXTRA_PERM' => 'Extra permanent',
        'HIGH_TACK' => 'High tack',
        'HOTMELT' => 'Hotmelt',
        'LINERLESS_HOT' => 'Linerless, hotmelt',
        'LINERLESS_PERM' => 'Linerless, permanent',
        'NONE_TICKER' => 'None (ticket)',
        'OPAQUE_PERM' => 'Opaque, permanent',
        'PERMANENT' => 'Permanent',
        'REMOVABLE' => 'Removable',
        'TAMPER_PROOF' => 'Tamper proof',
        'ULTRA_REMOV' => 'Ultra removable',
        'WASHABLE' => 'Washable',
    ],
    'material_suppliers' => [
        'FREELABELPILOT' => '#FREELABELPILOT',
        'ETIKETENKONING' => 'etikettenkoning.nl',
        'INGRAM_MICRO' => 'Ingram Micro',
        'JARLTECH' => 'Jarltech',
        'KOLIBRI' => 'Kolibri',
        'NAKAGAWA' => 'Nakagawa',
        'POLCOAT' => 'Polcoat',
        'PROFILABEL' => 'Profilabel',
        'SCANSOURCE' => 'ScanSource',
        'SEIKO_GMBH' => 'Seiko Instruments GMBH',
        'SUPPLY_SERVICE' => 'Supply Service',
        'WR_ETIKETTEN' => 'W&R Etiketten BV',
        'ZOLEMBA' => 'Zolemba',
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Details
    |--------------------------------------------------------------------------
    |
    | Used on generated documents such as the material spec-sheet PDF footer.
    |
    */
    'company' => [
        'name' => env('COMPANY_NAME', 'BusinessLabels'),
        'phone' => env('COMPANY_PHONE', '+31 (0)318 590 465'),
        'email' => env('COMPANY_EMAIL', 'verkoop@businesslabels.nl'),
        'address' => env('COMPANY_ADDRESS', 'Edisonstraat 86, 3899 AR Zeewolde, Netherlands'),
        'website' => env('COMPANY_WEBSITE', 'www.businesslabels.nl'),
        'google_review_url' => env('COMPANY_GOOGLE_REVIEW_URL', ''),
    ],
];
