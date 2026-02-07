<?php 
return [
    'meta'     => [
        'name'    => 'eNom',
        'version' => '1.0',
        'logo'    => 'logo.png',
    ],
    'settings' => [
        'whois-types'      => true,
        'dns-record-types' => [
            'A',
            'AAAA',
            'URL',
            'MX',
            'MXE',
            'CNAME',
            'TXT',
        ],
        'username'          => '',
        'password'          => '',
        'test-mode'         => 0,
        'whidden-amount'    => 6,
        'whidden-currency'  => 4,
        'adp'               => false,
        'cost-currency'     => 4,
    ],
];
