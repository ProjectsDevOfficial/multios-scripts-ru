<?php 
return [
    'meta'     => [
        'name'    => 'Dynadot',
        'version' => '1.0',
        'logo'    => 'logo.png',
    ],
    'settings' => [
        'whois-types'      => true,
        'dns-record-types' => ['A', 'AAAA','CNAME','FORWARD','TXT','MX','STEALTH','EMAIL'],
        'key'              => '',
        'test-mode'        => 0,
        'whidden-amount'   => 0,
        'whidden-currency' => 4,
        'adp'              => false,
        'cost-currency'    => 4,
    ],
];
