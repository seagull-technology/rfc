<?php

return [
    'approval_authority_map' => [
        'public_security' => [
            [
                'entity_code' => 'public-security-directorate',
                'name' => 'Application Public Security -> Public Security Directorate',
                'conditions' => [],
                'priority' => 100,
            ],
        ],
        'digital_economy' => [
            [
                'entity_code' => 'telecommunications-regulatory-commission',
                'name' => 'Application Digital Economy -> Telecommunications Regulatory Commission',
                'conditions' => [],
                'priority' => 100,
            ],
        ],
        'environment' => [
            [
                'entity_code' => 'royal-society-for-the-conservation-of-nature',
                'name' => 'Application Environment -> Royal Society for the Conservation of Nature',
                'conditions' => [],
                'priority' => 100,
            ],
        ],
        'municipalities' => [
            [
                'entity_code' => 'greater-amman-municipality',
                'name' => 'Application Municipalities -> Greater Amman Municipality',
                'conditions' => [],
                'priority' => 100,
            ],
        ],
        'airports' => [
            [
                'entity_code' => 'ministry-of-interior',
                'name' => 'Application Airports -> Ministry of Interior',
                'conditions' => [],
                'priority' => 100,
            ],
        ],
        'drones' => [
            [
                'entity_code' => 'public-security-directorate',
                'name' => 'Application Drones -> Public Security Directorate',
                'conditions' => [],
                'priority' => 100,
            ],
            [
                'entity_code' => 'ministry-of-interior',
                'name' => 'Application Drones -> Ministry of Interior',
                'conditions' => [],
                'priority' => 100,
            ],
        ],
        'heritage' => [
            [
                'entity_code' => 'department-of-antiquities',
                'name' => 'Application Heritage -> Department of Antiquities',
                'conditions' => [],
                'priority' => 100,
            ],
            [
                'entity_code' => 'jordanian-company-for-heritage-revival',
                'name' => 'Application Heritage -> Jordanian Company for Heritage Revival',
                'conditions' => [],
                'priority' => 100,
            ],
        ],
        'customs' => [
            [
                'entity_code' => 'jordan-customs',
                'name' => 'Application Customs Imported Equipment -> Jordan Customs',
                'conditions' => [
                    'annex_flags' => ['imported_equipment'],
                ],
                'priority' => 80,
            ],
        ],
        'military_border' => [
            [
                'entity_code' => 'military-media-directorate',
                'name' => 'Application Military Border Equipment -> Military Media Directorate',
                'conditions' => [
                    'annex_flags' => ['military_border_equipment'],
                ],
                'priority' => 80,
            ],
        ],
    ],
];
