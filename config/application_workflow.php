<?php

return [
    'approval_authority_map' => [
        'public_security' => [
            [
                'entity_code' => 'public-security-directorate',
                'name' => 'Application Public Security -> Public Security Directorate',
                'conditions' => [
                    'annex_flags' => [
                        'special_requirement_road_closures',
                        'special_requirement_police_presence',
                        'special_requirement_special_effects',
                        'special_requirement_weapons',
                        'public_security_support',
                    ],
                ],
                'priority' => 80,
            ],
        ],
        'digital_economy' => [
            [
                'entity_code' => 'telecommunications-regulatory-commission',
                'name' => 'Application Digital Economy -> Telecommunications Regulatory Commission',
                'conditions' => [],
                'priority' => 120,
            ],
        ],
        'environment' => [
            [
                'entity_code' => 'royal-society-for-the-conservation-of-nature',
                'name' => 'Application Environment -> Royal Society for the Conservation of Nature',
                'conditions' => [
                    'annex_flags' => ['location_type_reserves'],
                ],
                'priority' => 80,
            ],
        ],
        'municipalities' => [
            [
                'entity_code' => 'greater-amman-municipality',
                'name' => 'Application Municipalities -> Greater Amman Municipality',
                'conditions' => [
                    'annex_flags' => [
                        'special_requirement_road_closures',
                        'special_requirement_construction_work',
                    ],
                ],
                'priority' => 90,
            ],
        ],
        'airports' => [
            [
                'entity_code' => 'ministry-of-interior',
                'name' => 'Application Airports -> Ministry of Interior',
                'conditions' => [
                    'annex_flags' => [
                        'airport_filming',
                        'special_requirement_regular_aerial_filming',
                    ],
                ],
                'priority' => 80,
            ],
        ],
        'drones' => [
            [
                'entity_code' => 'public-security-directorate',
                'name' => 'Application Drones -> Public Security Directorate',
                'conditions' => [
                    'annex_flags' => ['special_requirement_drone_filming'],
                ],
                'priority' => 80,
            ],
            [
                'entity_code' => 'ministry-of-interior',
                'name' => 'Application Drones -> Ministry of Interior',
                'conditions' => [
                    'annex_flags' => ['special_requirement_drone_filming'],
                ],
                'priority' => 80,
            ],
        ],
        'heritage' => [
            [
                'entity_code' => 'department-of-antiquities',
                'name' => 'Application Heritage -> Department of Antiquities',
                'conditions' => [
                    'annex_flags' => [
                        'location_type_archaeological_sites',
                        'location_type_petra',
                        'location_type_museums',
                        'location_type_religious_sites',
                    ],
                ],
                'priority' => 80,
            ],
            [
                'entity_code' => 'jordanian-company-for-heritage-revival',
                'name' => 'Application Heritage -> Jordanian Company for Heritage Revival',
                'conditions' => [
                    'annex_flags' => [
                        'location_type_archaeological_sites',
                        'location_type_petra',
                        'location_type_museums',
                        'location_type_religious_sites',
                    ],
                ],
                'priority' => 90,
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
                'name' => 'Application Military Support -> Military Media Directorate',
                'conditions' => [
                    'annex_flags' => [
                        'military_support',
                        'location_type_border_areas',
                        'special_requirement_armed_forces',
                    ],
                ],
                'priority' => 80,
            ],
        ],
    ],
];
