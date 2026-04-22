<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Progress Tracker — Default Framework
|--------------------------------------------------------------------------
|
| Seeded into every new instructor's personal framework on creation. Each
| instructor gets their own editable copy, so changes here only affect
| instructors created after the change (and anyone re-seeded via
| `php artisan progress-tracker:backfill --reset`).
|
| Structure: ordered list of categories, each with an ordered list of
| subcategory names. Scoring (1–5) happens on subcategories.
|
*/

return [
    'default_framework' => [
        [
            'name' => 'Preparation',
            'subcategories' => [
                'Cockpit Checks',
                'Safety Checks',
                'Vehicle Controls',
                'Seat Positioning',
                'Mirrors',
            ],
        ],
        [
            'name' => 'Traffic',
            'subcategories' => [
                'Signals',
                'Anticipation',
                'Use of Speed',
                'Meeting Traffic',
                'Crossing Traffic',
                'Overtaking',
            ],
        ],
        [
            'name' => 'Junctions',
            'subcategories' => [
                'Left Turn',
                'Right Turn',
                'Emerging',
            ],
        ],
        [
            'name' => 'Traffic Management',
            'subcategories' => [
                'Roundabouts',
                'Mini Roundabouts',
                'Pedestrian Crossing',
                'Dual Carriageways',
            ],
        ],
        [
            'name' => 'Manoeuvres',
            'subcategories' => [
                'Straight Reverse',
                'Left Reverse',
                'Right Reverse',
                'Parking in a Bay',
                'Parallel Parking',
                'Park on the Right-hand Side of Road',
                'Turning In-road',
            ],
        ],
        [
            'name' => 'Situations',
            'subcategories' => [
                'Emergency Stop',
                'Daytime Driving',
                'Nighttime Driving',
                'Dry Roads',
                'Wet Roads',
                'Country Roads',
                'Town and City Roads',
                'Sat Nav Driving',
                'Following Road Signs',
            ],
        ],
    ],

    'score_labels' => [
        1 => 'Introduced',
        2 => 'Instructed',
        3 => 'Prompted',
        4 => 'Seldom prompted',
        5 => 'Independent',
    ],
];
