<?php 
return [
    'meta'     => [
        'name'    => 'OpenProvider',
        'version' => '1.0',
        'logo'    => 'logo.webp',
    ],
    'settings' => [
        'whois-types'       => true,
        'dns-record-types' => [
            'A',
            'AAAA',
            'CAA',
            'CNAME',
            'MX',
            'SPF',
            'SRV',
            'TXT',
            'NS',
            'TLSA',
            'SSHFP',
            'SOA',
        ],
        'username'           => '',
        'password'           => '',
        'test-mode'         => 0,
        'whidden-amount'    => 4,
        'whidden-currency'  => 4,
        'adp'               => false,
        'cost-currency'     => 4,
    ],
];
