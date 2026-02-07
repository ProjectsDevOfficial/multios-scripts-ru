<?php 
    return [
        'meta'     => [
            'name'    => 'Paystack',
            'version' => '1.0',
            'logo'    => 'logo.svg',
        ],
        'settings' => [
            'liveSecretKey' => '',
            'livePublicKey' => '',
            'testSecretKey' => '',
            'testPublicKey' => '',
            'testMode' => 0,
            'commission_rate'  => 0,
            'force_convert_to' => 0,
            'accepted_countries'    => [],
            'unaccepted_countries'  => [],
        ],
    ];