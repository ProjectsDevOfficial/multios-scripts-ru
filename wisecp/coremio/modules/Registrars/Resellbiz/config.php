<?php 
return [
    'meta'     => [
        'name'    => 'Resellbiz',
        'version' => '1.0',
        'logo'    => 'logo.png',
    ],
    'settings' => [
        'whois-types' => true,
        'doc-fields'        => [
            'biz' => [
                'idnLanguageCode' => [
                    'type' => 'select',
                    'name' => 'Domain Name Language',
                    'options' => [
                        'zh' => 'Chinese',
                        'da' => 'Danish',
                        'fi' => 'Finnish',
                        'de' => 'German',
                        'hu' => 'Hungarian',
                        'is' => 'Icelandic',
                        'jp' => 'Japanese',
                        'ko' => 'Korean',
                        'lt' => 'Lithuanian',
                        'lv' => 'Latvian',
                        'no' => 'Norwegian',
                        'pl' => 'Polish',
                        'pt' => 'Portuguese',
                        'es' => 'Spanish',
                        'sv' => 'Swedish',
                    ],
                ],
            ],
            'co' => [
                'idnLanguageCode' => [
                    'type' => 'select',
                    'name' => 'Domain Name Language',
                    'options' => [
                        'zh' => 'Chinese',
                        'da' => 'Danish',
                        'fi' => 'Finnish',
                        'is' => 'Icelandic',
                        'jp' => 'Japanese',
                        'ko' => 'Korean',
                        'no' => 'Norwegian',
                        'es' => 'Spanish',
                        'sv' => 'Swedish',
                    ],
                ],
            ],
            'in.net' => [
                'idnLanguageCode' => [
                    'type' => 'select',
                    'name' => 'Domain Name Language',
                    'options' => [
                        'ara' => 'Arabic',
                        'chi' => 'Chinese',
                        'cyr' => 'Cyrillic',
                        'gre' => 'Greek',
                        'heb' => 'Hebrew ',
                        'jpn' => 'Japanese',
                        'kor' => 'Korean',
                        'lao' => 'Lao',
                        'lat' => 'Latin',
                        'tha' => 'Thai',
                    ],
                ],
            ],
            'asia' => [
                'cedcontactid' => [
                    'type' => 'text',
                    'name' => 'Charter Eligibility Declaration Contact ID',
                ],
            ],
            'au'   => [
                'id-type' => [
                    'type' => "select",
                    'name' => 'Eligibility ID Type',
                    'options' => [
                        'ACN' => "Australian Company Number",
                        'ABN' => "Australian Business Number",
                        'VIC BN' => "Victoria Business Number",
                        'NSW BN' => "New South Wales Business Number",
                        'SA BN' => "South Australia Business Number",
                        'NT BN' => "Northern Territory Business Number",
                        'WA BN' => "Australia Business Number",
                        'TAS BN' => "Tasmania Business Number",
                        'ACT BN' => "Australian Capital Territory Business Number",
                        'ACT BN' => "Australian Capital Territory Business Number",
                        'QLD BN' => "Queensland Business Number",
                        'TM' => "Trademark number",
                        'ARBN' => "Australian Registered Body Number (ARBN)",
                        'Other' => "Other",
                    ],
                ],
                'id' => [
                    'type'          => "text",
                    'name'          => "Eligibility ID Value",
                ],
                'policyReason'          => [
                    'type' => "select",
                    'name'          => "Eligibility Reason",
                    'options'       => [
                        '1'             => "Option 1",
                        '2'             => "Option 2",
                    ],
                ],
                'eligibilityType'       => [
                    'type' => 'select',
                    'name' => 'Eligibility Type',
                    'options' => [
                        'Trademark'     => "Trademark Owner or Pending TM Owner",
                        'Other'         => "Other",
                    ],
                ],
                'registrantName'        => [
                    'type'          => "text",
                    'name'          => "Registrant Name",
                ],
            ],
            'br'   => [
                'organisationId'    => [
                    'type'      => "text",
                    'name'      => "Organisation ID",
                ],
                'cnhosting'     => [
                    'type'      => "select",
                    'name'      => "Will the hosting be hosted in China?",
                    'options'   => [
                        'true'          => "Yes",
                        'false'         => "No",
                    ],
                ],
                'cnhostingclause'     => [
                    'type'      => "select",
                    'name'      => "China Hosting Clause",
                    'options'   => [
                        'yes'       => "Yes",
                        'no'        => "No",
                    ],
                ],
            ],
            'fr'   => [
                'tnc' => [
                    'type' => "select",
                    'name' => 'TNC',
                    'options' => [
                        'Y' => "Accepted",
                        'N' => "Not Accepted",
                    ],
                ],
            ],
            'quebec' => [
                'intended-use' => [
                    'type' => "text",
                    'name' => "Intended Use",
                ],
            ],
            'scot'   => [
                'intended-use' => [
                    'type' => "text",
                    'name' => "Intended Use",
                ],
                'tnc' => [
                    'type' => "select",
                    'name' => 'TNC',
                    'options' => [
                        'Y' => "Accepted",
                        'N' => "Not Accepted",
                    ],
                ],
            ],
            'tel'    => [
                'whois-type' => [
                    'type' => "select",
                    'name' => "Whois Type",
                    'options' => [
                        'Natural'   => 'Individual',
                        'Legal'     => 'Oragnization',
                    ],
                ],
                'publish' => [
                    'type' => "select",
                    'name' => "The Contact Details Publish",
                    'options' => [
                        'Y'   => 'Accepted',
                        'N'   => 'Not Accepted',
                    ],
                ],
            ],
        ],
        'auth-userid'      => '',
        'api-key'          => '',
        'test-mode'        => 0,
        'whidden-amount'   => 3,
        'whidden-currency' => 4,
        'cost-currency'    => 4,
        'adp'              => false,
    ],
];
