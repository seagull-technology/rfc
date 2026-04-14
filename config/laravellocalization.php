<?php

return [
    'supportedLocales' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'script' => 'Latn',
            'regional' => 'en_GB',
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'script' => 'Arab',
            'regional' => 'ar_JO',
        ],
    ],

    'useAcceptLanguageHeader' => false,
    'hideDefaultLocaleInURL' => false,
    'localesOrder' => ['en', 'ar'],
    'localesMapping' => [],
    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),
    'urlsIgnored' => [],
];
