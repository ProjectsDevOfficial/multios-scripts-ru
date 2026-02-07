<?php 
return [
    'meta'     => [
        'name'    => 'InternetX',
        'version' => '1.0',
        'logo'    => 'logo.png',
    ],
    'settings' => [
	    'whois-types'      => true,
        'dns-record-types' => ['A','AAAA','MX','CNAME','SPF','SRV','CAA','ALIAS'],
        'serverHost'       => 'https://gateway.autodns.com/',
        'serverUsername'   => '',
        'serverPassword'   => '',
        'serverContext'    => '4',
        'nameServers'      => '',
        'domainMX'         => '',
        'domainIP'         => '',
        'adminContact'     => false,
        'test-mode'        => 0,
        'whidden-amount'   => 0,
        'whidden-currency' => 4,
        'cost-currency'    => 4,
        'adp'              => false,
    ],
];
