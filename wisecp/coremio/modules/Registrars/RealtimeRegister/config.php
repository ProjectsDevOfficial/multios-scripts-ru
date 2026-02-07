<?php 
return [
    'meta'     => [
        'name'    => 'RealtimeRegister',
        'version' => '1.0',
        'logo'    => 'logo.svg',
    ],
    'settings' => [
        'whois-types'       => true,
        'doc-fields' => [
            'lk'          => [
                'IDCARD-OR-PASSPORT-NUMBER' => [
                    'type' => 'text',
                    'name' => 'ID card no.',
                ],
            ],
            'jp'          => [
                'COMPANY-NUMBER' => [
                    'type' => 'text',
                    'name' => 'Company reg. number',
                ],
                'FORMATION-DATE' => [
                    'type' => 'text',
                    'name' => 'FORMATION-DATE',
                ],
            ],
            'radio'       => [
                'intendedUse' => [
                    'type' => 'text',
                    'name' => 'Intended Use',
                ],
            ],
            'it'          => [
                'entityType' => [
                    'type'    => 'select',
                    'name'    => 'Entity type',
                    'options' => [
                        1 => '1. Natural persons Italian and EU based',
                        2 => '2. Italian Companies (Italian based)',
                        3 => '3. Freelance workers/professionals/sole proprietorships (Italian based)',
                        4 => '4. Non-profit organizations(Italian based)',
                        5 => '5. Public organizations (Italian based)',
                        6 => '6. Other subjects (Italian based)',
                        7 => '7. Foreign companies/organizations matching 2-6 (EU based)',
                    ],
                ],
                'regCode'    => [
                    'type' => 'text',
                    'name' => 'Identification number',
                ],
            ],
            'hiv'         => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'ie'          => [
                'IE-HOLDER-TYPE' => [
                    'type'    => 'select',
                    'name'    => 'IE-HOLDER-TYPE',
                    'options' => [
                        'CHARITY' => 'Charity',
                        'OTHER'   => 'Natural Person/Other',
                        'COMPANY' => 'Company (Ltd.  PLC  etc.)',
                    ],
                ],
            ],
            'link'        => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'gw'          => [
                'VAT-NUMBER' => [
                    'type' => 'text',
                    'name' => 'VAT number',
                ],
            ],
            'es'          => [
                'identificationNumber' => [
                    'type' => 'text',
                    'name' => 'Unique data set, organization number or NIE or NIF',
                ],
            ],
            'fi'          => [
                'isFinnish' => [
                    'type'    => 'select',
                    'name'    => 'Is Finnish',
                    'options' => [
                        'Y' => 'True',
                        'N' => 'False',
                    ],
                ],
            ],
            'ua'          => [
                'TRADEMARK-NUMBER' => [
                    'type' => 'text',
                    'name' => 'Trademark Number',
                ],
            ],
            'us'          => [
                'AppPurpose'    => [
                    'type'    => 'select',
                    'name'    => 'Application Purpose',
                    'options' => [
                        'P1' => 'P1: Business, profit',
                        'P2' => 'P2: Non-profit, business or otherwise',
                        'P3' => 'P3: Personal',
                        'P4' => 'P4: Educational',
                        'P5' => 'P5: Governmental',
                    ],
                ],
                'NexusCategory' => [
                    'type'    => 'select',
                    'name'    => 'Nexus Category',
                    'options' => [
                        'C31' => 'C31: A foreign organization entitled to register',
                        'C11' => 'C11: A U.S. citizen (a natural person)',
                        'C21' => 'C21: An organization entitled to register',
                        'C32' => 'C32: An organization with office or facility in the U.S.',
                        'C12' => 'C12: A permanent resident of the U.S. (a natural person)',
                    ],
                ],
            ],
            'sexy'        => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'blackfriday' => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'pt'          => [
                'vatno' => [
                    'type' => 'text',
                    'name' => 'Vat number',
                ],
            ],
            'gift'        => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'photo'       => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'property'    => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'cn'          => [
                'id'     => [
                    'type' => 'text',
                    'name' => 'ID',
                ],
                'idType' => [
                    'type'    => 'select',
                    'name'    => 'ID type',
                    'options' => [
                        'WJLSFZ' => 'Foreign Permanent Resident ID Card (Individuals)',
                        'ORG'    => 'Organization Code Certificate (Organizations)',
                        'WGZHWH' => 'Registration Certificate of Foreign Cultural Center in China (Organizations)',
                        'TWJMTX' => 'Travel passes for Taiwan Residents to Enter or Leave the Mainland (Individuals)',
                        'YYZZ'   => 'Business License (Organizations)',
                        'ZJCS'   => 'Religion Activity Site Registration Certificate (Organizations)',
                        'WLCZJG' => 'Resident Representative Office of Tourism Departments of Foreign Government Approval Registration Certificate (Organizations)',
                        'GZJGZY' => 'Notary Organization Practicing License (Organizations)',
                        'HZ'     => 'Chinese Passport (Individuals)',
                        'BDDM'   => 'Military Code Designation (Organizations)',
                        'SHFWJG' => 'Social Service Agency Registration Certificate (Organizations)',
                        'MBXXBX' => 'Private School Permit (Organizations)',
                        'SHTTFR' => 'Social Organization Legal Person Registration Certificate (Organizations)',
                        'JJHFR'  => 'Fund Legal Person Registration Certificate (Organizations)',
                        'QT'     => 'Others',
                        'MBFQY'  => 'Private Non-Enterprise Entity Registration Certificate (Organizations)',
                        'LSZY'   => 'Practicing License of Law Firm (Organizations)',
                        'SFZ'    => 'Chinese ID (Individuals)',
                        'JWJG'   => 'Overseas Organization Certificate (Organizations)',
                        'TYDM'   => 'Certificate for Uniform Social Credit Code (Organizations)',
                        'SFJD'   => 'Judicial Expertise License (Organizations)',
                        'SYDWFR' => 'Public Institution Legal Person Certificate (Organizations)',
                        'TWJZZ'  => 'Residence permit for Taiwan residents (Individuals)',
                        'GAJZZ'  => 'Residence permit for Hong Kong, Macao residents (Individuals)',
                        'WGCZJG' => 'Resident Representative Offices of Foreign Enterprises Registration Form (Organizations)',
                        'JDDWFW' => 'Military Paid External Service License (Organizations)',
                        'JGZ'    => 'Officer.s identity card (Individuals)',
                        'YLJGZY' => 'Medical Institution Practicing License (Organizations)',
                        'GAJMTX' => 'Exit-Entry Permit for Travelling to and from Hong Kong and Macao (Individuals)',
                        'BJWSXX' => 'Beijing School for Children of Foreign Embassy Staff in China Permit (Organizations)',
                    ],
                ],
            ],
            'au'          => [
                'COMPANY-NUMBER'         => [
                    'type' => 'text',
                    'name' => 'Company reg. number',
                ],
                'AU-DOMAIN-RELATION'     => [
                    'type'    => 'select',
                    'name'    => '.AU domain relation',
                    'options' => [
                        1 => "2LD Domain name is an exact match  acronym or abbreviation of the registrant\xe2\x80\x99s company or trading name  organization or association name or trademark.",
                        2 => '2LD Domain Name is closely and substantially connected to the registrant.',
                    ],
                ],
                'AU-DOMAIN-IDTYPE'       => [
                    'type'    => 'select',
                    'name'    => '.AU identification type',
                    'options' => [
                        'OTHER' => 'Other',
                        'ARBN'  => 'Australian Registered Body Number',
                        'ACN'   => 'Australian Company Number',
                        'ABN'   => 'Australian Business Number',
                    ],
                ],
                'AU-DOMAIN-RELATIONTYPE' => [
                    'type'    => 'select',
                    'name'    => '.AU owner type',
                    'options' => [
                        'Company'                   => 'Company',
                        'Trademark Owner'           => 'Trademark Owner',
                        'Citizen/Resident'          => 'Citizen/Resident',
                        'Industry Body'             => 'Industry Body',
                        'Trade Union'               => 'Trade Union',
                        'Incorporated Association'  => 'Incorporated Association',
                        'Partnership'               => 'Partnership',
                        'Club'                      => 'Club',
                        'Pending TM Owner'          => 'Pending TM Owner',
                        'Political Party'           => 'Political Party',
                        'Registered Business'       => 'Registered Business',
                        'Charity'                   => 'Charity',
                        'Commercial Statutory Body' => 'Commercial Statutory Body',
                        'Non-profit Organisation'   => 'Non-profit Organisation',
                        'Sole Trader'               => 'Sole Trader',
                        'Other'                     => 'Other',
                    ],
                ],
            ],
            'juegos'      => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'help'        => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'sport'       => [
                'intendedUse' => [
                    'type' => 'text',
                    'name' => 'Intended Use',
                ],
            ],
            'country'     => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'click'       => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'tattoo'      => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
            'eus'         => [
                'intendedUse' => [
                    'type' => 'text',
                    'name' => 'Intended Use',
                ],
            ],
            'barcelona'   => [
                'intendedUse' => [
                    'type' => 'text',
                    'name' => 'Intended Use',
                ],
            ],
            'doctor'      => [
                'question1' => [
                    'type' => 'text',
                    'name' => 'Security question 1',
                ],
                'answer1'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 1',
                ],
                'question2' => [
                    'type' => 'text',
                    'name' => 'Security question 2',
                ],
                'answer2'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 2',
                ],
                'question3' => [
                    'type' => 'text',
                    'name' => 'Security question 3',
                ],
                'answer3'   => [
                    'type' => 'text',
                    'name' => 'Answer to question 3',
                ],
            ],
        ],
        'dns-record-types' => [
            'A',
            'MX',
            'CNAME',
            'AAAA',
            'URL',
            'MBOXFW',
            'HINFO',
            'NAPTR',
            'NS',
            'SRV',
            'CAA',
            'TLSA',
            'ALIAS',
            'TXT',
            'SOA'
        ],
        'api-key'           => '',
        'test-mode'         => 0,
        'whidden-amount'    => 0,
        'whidden-currency'  => 4,
        'adp'               => false,
        'cost-currency'     => 4,
    ],
];
