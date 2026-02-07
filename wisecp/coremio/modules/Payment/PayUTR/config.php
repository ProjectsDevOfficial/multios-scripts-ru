<?php 
return [
    'meta'     => [
        'name'    => 'PayU',
        'version' => '1.0',
        'logo'    => 'logo.svg',
    ],
    'settings' => [
        'commission_rate'        => 0,
        'force_convert_to'       => 0,
        'accepted_countries'     => [],
        'unaccepted_countries'   => [],
        'merchant_id'            => '125125215215',
        'secret_key'             => 'AGAEGEAR23',
        'installment'            => '1',
        'installment_commission' => "2 : 2.50\n3 : 4.50\n4 : 5.20",
        'max_installment'        => '12',
    ],
];
