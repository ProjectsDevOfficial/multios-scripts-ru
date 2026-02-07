<?php 
return [
    'meta'     => [
        'name'    => 'InternetBS',
        'version' => '1.0',
        'logo'    => 'logo.png',
    ],
    'settings' => [
        'whois-types'       => true,
        'dns-record-types' => [
            'A',
            'AAAA',
            'MX',
            'CNAME',
            'TXT',
            'NS',
        ],
        'api-key'           => '',
        'test-mode'         => 0,
        'whidden-amount'    => 0,
        'whidden-currency'  => 4,
        'adp'               => false,
        'cost-currency'     => 4,
    ],
];
