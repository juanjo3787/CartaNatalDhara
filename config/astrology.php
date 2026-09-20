<?php

return [
    'zodiac' => env('ASTROLOGY_ZODIAC', 'tropical'),
    'reference' => env('ASTROLOGY_REFERENCE', 'geocentric'),
    'houses' => env('ASTROLOGY_HOUSES', 'placidus'),
    'node' => env('ASTROLOGY_NODE', 'true'),
    'lilith' => env('ASTROLOGY_LILITH', 'mean'),
    'ephemeris_path' => env('ASTROLOGY_EPHEMERIS_PATH', storage_path('ephemeris')),
    'swetest_bin' => env('ASTROLOGY_SWETEST_BIN', 'swetest'),
    'aspects' => [
        'conjunction' => 8.0,
        'opposition' => 8.0,
        'trine' => 7.0,
        'square' => 7.0,
        'sextile' => 5.0,
        'quincunx' => 3.0,
        'semisquare' => 2.0,
        'sesquiquadrate' => 2.0,
    ],
];
