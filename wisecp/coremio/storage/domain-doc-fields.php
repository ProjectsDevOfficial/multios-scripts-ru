<?php
    $fields = [
        // .US TLD
        'us' => [
            'nexus_category' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Nexus Category',
                'description' => 'Select the appropriate nexus category',
                'options' => [
                    'C11' => 'C11 - United States Citizen',
                    'C12' => 'C12 - Permanent Resident of the United States',
                    'C21' => 'C21 - A U.S.-based organization formed within the United States of America',
                    'C31' => 'C31 - A foreign entity or organization that has a bona fide presence in the United States of America',
                    'C32' => 'C32 - An entity or Organisation that has an office or other facility in the United States'
                ],
            ],
            'nexus_country' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Nexus Country',
                'description' => 'Enter the nexus country',
            ],
            'application_purpose' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Application Purpose',
                'description' => 'Select the purpose of the application',
                'options' => [
                    'Business use for profit' => 'Business use for profit',
                    'Non-profit business' => 'Non-profit business',
                    'Club' => 'Club',
                    'Association' => 'Association',
                    'Religious Organization' => 'Religious Organization',
                    'Personal Use' => 'Personal Use',
                    'Educational purposes' => 'Educational purposes',
                    'Government purposes' => 'Government purposes'
                ],
            ],
        ],

        // .UK TLD
        'uk' => [
            'legal_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Legal Type',
                'description' => 'Select the legal type of the registrant',
                'options' => [
                    'UK Limited Company' => 'UK Limited Company',
                    'UK Public Limited Company' => 'UK Public Limited Company',
                    'UK Partnership' => 'UK Partnership',
                    'UK Limited Liability Partnership' => 'UK Limited Liability Partnership',
                    'Sole Trader' => 'UK Sole Trader',
                    'UK Industrial/Provident Registered Company' => 'UK Industrial/Provident Registered Company',
                    'Individual' => 'UK Individual (representing self)',
                    'UK School' => 'UK School',
                    'UK Registered Charity' => 'UK Registered Charity',
                    'UK Government Body' => 'UK Government Body',
                    'UK Corporation by Royal Charter' => 'UK Corporation by Royal Charter',
                    'UK Statutory Body' => 'UK Statutory Body',
                    'UK Entity (other)' => 'UK Entity that does not fit into any of the above',
                    'Non-UK Individual' => 'Non-UK Individual (representing self)',
                    'Foreign Organization' => 'Non-UK Corporation',
                    'Other foreign organizations' => 'Non-UK Entity that does not fit into any of the above',
                ],
            ],
            'company_id_number' => [
                'type' => 'text',
                'required' => ['legal_type' => ['UK Limited Company', 'UK Public Limited Company']],
                'name' => 'Company ID Number',
                'description' => 'Enter the Company ID Number',
            ],
            'registrant_name' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Registrant Name',
                'description' => 'Enter the name of the registrant',
            ],
            'whois_optout' => [
                'type' => 'select',
                'required' => false,
                'name' => 'WHOIS Opt-out',
                'description' => 'Choose to opt-out of WHOIS display',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .CA TLD
        'ca' => [
            'legal_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Legal Type',
                'description' => 'Legal type of registrant contact',
                'options' => [
                    'Corporation' => 'Corporation',
                    'Canadian Citizen' => 'Canadian Citizen',
                    'Permanent Resident of Canada' => 'Permanent Resident of Canada',
                    'Government' => 'Government',
                    'Canadian Educational Institution' => 'Canadian Educational Institution',
                    'Canadian Unincorporated Association' => 'Canadian Unincorporated Association',
                    'Canadian Hospital' => 'Canadian Hospital',
                    'Partnership Registered in Canada' => 'Partnership Registered in Canada',
                    'Trade-mark registered in Canada' => 'Trade-mark registered in Canada',
                    'Canadian Trade Union' => 'Canadian Trade Union',
                    'Canadian Political Party' => 'Canadian Political Party',
                    'Canadian Library Archive or Museum' => 'Canadian Library Archive or Museum',
                    'Trust established in Canada' => 'Trust established in Canada',
                    'Aboriginal Peoples' => 'Aboriginal Peoples',
                    'Legal Representative of a Canadian Citizen' => 'Legal Representative of a Canadian Citizen',
                    'Official mark registered in Canada' => 'Official mark registered in Canada',
                ],
            ],
            'cira_agreement' => [
                'type' => 'select',
                'required' => true,
                'name' => 'CIRA Agreement',
                'description' => 'Confirm you agree to the CIRA Registration Agreement',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'whois_optout' => [
                'type' => 'select',
                'required' => false,
                'name' => 'WHOIS Opt-out',
                'description' => 'Choose to hide your contact information in CIRA WHOIS (only available to individuals)',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .ES TLD
        'es' => [
            'id_form_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'ID Form Type',
                'description' => 'Select the type of identification',
                'options' => [
                    'Other Identification' => 'Other Identification',
                    'Tax Identification Number' => 'Tax Identification Number',
                    'Tax Identification Code' => 'Tax Identification Code',
                    'Foreigner Identification Number' => 'Foreigner Identification Number',
                ],
            ],
            'id_form_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'ID Form Number',
                'description' => 'Enter the identification number',
            ],
            'legal_form' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Legal Form',
                'description' => 'Select the legal form of the entity',
                'options' => [
                    '1' => 'Individual',
                    '39' => 'Economic Interest Grouping',
                    '47' => 'Association',
                    '59' => 'Sports Association',
                    '68' => 'Professional Association',
                    '124' => 'Savings Bank',
                    '150' => 'Community Property',
                    '152' => 'Community of Owners',
                    '164' => 'Order or Religious Institution',
                    '181' => 'Consulate',
                    '197' => 'Public Law Association',
                    '203' => 'Embassy',
                    '229' => 'Local Authority',
                    '269' => 'Sports Federation',
                    '286' => 'Foundation',
                    '365' => 'Mutual Insurance Company',
                    '434' => 'Regional Government Body',
                    '436' => 'Central Government Body',
                    '439' => 'Political Party',
                    '476' => 'Trade Union',
                    '510' => 'Farm Partnership',
                    '524' => 'Public Limited Company',
                    '554' => 'Civil Society',
                    '560' => 'General Partnership',
                    '562' => 'General and Limited Partnership',
                    '566' => 'Cooperative',
                    '608' => 'Worker-owned Company',
                    '612' => 'Limited Company',
                    '713' => 'Spanish Office',
                    '717' => 'Temporary Alliance of Enterprises',
                    '744' => 'Worker-owned Limited Company',
                    '745' => 'Regional Public Entity',
                    '746' => 'National Public Entity',
                    '747' => 'Local Public Entity',
                    '877' => 'Others',
                    '878' => 'Designation of Origin Supervisory Council',
                    '879' => 'Entity Managing Natural Areas',
                ],
            ],
        ],

        // .COM.ES TLD
        'com.es' => [
            'entity_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Entity Type',
                'description' => 'Select the type of entity',
                'options' => [
                    'ALLIANCE_TEMPORARY' => 'Temporary Alliance of Enterprises',
                    'ASSOCIATION' => 'Association',
                    'ASSOCIATION_LAW' => 'Public Law Association',
                    'BANK_SAVINGS' => 'Savings Bank',
                    'CIVIL_SOCIETY' => 'Civil Society',
                    'COMMUNITY_OF_OWNERS' => 'Community of Owners',
                    'COMMUNITY_PROPERTY' => 'Community Property',
                    'COMPANY_LIMITED' => 'Limited Company',
                    'COMPANY_LIMITED_PUBLIC' => 'Public Limited Company',
                    'COMPANY_LIMITED_SPORTS' => 'Sports Public Limited Company',
                    'COMPANY_LIMITED_WORKER_OWNED' => 'Worker-owned Limited Company',
                    'COMPANY_WORKER_OWNED' => 'Worker-owned Company',
                    'CONSULATE' => 'Consulate',
                    'COOPERATIVE' => 'Cooperative',
                    'COUNCIL_SUPERVISORY' => 'Designation of Origin Supervisory Council',
                    'ECONOMIC_INTEREST_GROUP' => 'Economic Interest Group',
                    'EMBASSY' => 'Embassy',
                    'ENTITY_LOCAL' => 'Local Public Entity',
                    'ENTITY_MANAGING_AREAS' => 'Entity Managing Natural Areas',
                    'ENTITY_NATIONAL' => 'National Public Entity',
                    'ENTITY_REGIONAL' => 'Regional Public Entity',
                    'FEDERATION_SPORT' => 'Sports Federation',
                    'FOUNDATION' => 'Foundation',
                    'GOVERNMENT_CENTRAL' => 'Central Government Body',
                    'GOVERNMENT_REGIONAL' => 'Regional Government Body',
                    'INDIVIDUAL' => 'Individual',
                    'INSTITUTION_RELIGIOUS' => 'Order or Religious Institution',
                    'INSURANCE' => 'Mutual Insurance Company',
                    'LOCAL_AUTHORITY' => 'Local Authority',
                    'OTHERS' => 'Others (only for contacts outside of Spain)',
                    'PARTNERSHIP_FARM' => 'Farm Partnership',
                    'PARTNERSHIP_GENERAL' => 'General Partnership',
                    'PARTNERSHIP_GENERAL_LIMITED' => 'General and Limited Partnership',
                    'POLITICAL_PARTY' => 'Political Party',
                    'PROFESSIONAL' => 'Professional Association',
                    'SPANISH_OFFICE' => 'Spanish Office',
                    'SPORTS' => 'Sports Association',
                    'UNION_TRADE' => 'Trade Union',
                ],
            ],
            'id_form_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'ID Form Type',
                'description' => 'Select the type of identification',
                'options' => [
                    'CITIZEN' => 'NIF (Spanish citizen)',
                    'COMPANY' => 'CIF (Spanish Company)',
                    'OTHER' => 'Other form of ID (Those outside of Spain)',
                    'RESIDENT' => 'NIE (Legal residents in Spain)',
                ],
            ],
            'id_form_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'ID Form Number',
                'description' => 'Enter the identification number',
            ],
        ],

        // .SG TLD
        'sg' => [
            'rcb_singapore_id' => [
                'type' => 'text',
                'required' => true,
                'name' => 'RCB/Singapore ID',
                'description' => 'Enter the RCB/Singapore ID',
            ],
            'registrant_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Registrant Type',
                'description' => 'Select the type of registrant',
                'options' => [
                    'Individual' => 'Individual',
                    'Organisation' => 'Organisation',
                ],
            ],
            'admin_personal_id' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Admin Personal ID',
                'description' => 'This is the personal ID of the administrative contact for this domain',
            ],
        ],

        // .TEL TLD
        'tel' => [
            'legal_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Legal Type',
                'description' => 'Select the legal type',
                'options' => [
                    'Natural Person' => 'Natural Person',
                    'Legal Person' => 'Legal Person',
                ],
            ],
            'whois_optout' => [
                'type' => 'select',
                'required' => false,
                'name' => 'WHOIS Opt-out',
                'description' => 'Choose to opt-out of WHOIS display',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .IT TLD
        'it' => [
            'legal_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Legal Type',
                'description' => 'Legal type of registrant',
                'options' => [
                    'Italian and foreign natural persons' => 'Italian and foreign natural persons',
                    'Companies/one man companies' => 'Companies/one man companies',
                    'Freelance workers/professionals' => 'Freelance workers/professionals',
                    'non-profit organizations' => 'non-profit organizations',
                    'public organizations' => 'public organizations',
                    'other subjects' => 'other subjects',
                    'non natural foreigners' => 'non natural foreigners',
                ],
            ],
            'tax_id' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Tax ID',
                'description' => 'Enter the Tax ID',
            ],
            'publish_personal_data' => [
                'type' => 'select',
                'required' => false,
                'name' => 'Publish Personal Data',
                'description' => 'Choose to publish personal data',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'accept_liability' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Accept Section 3 of .IT registrar contract',
                'description' => 'Choose to accept Section 3 of .IT registrar contract',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'accept_registration_fee' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Accept Section 5 of .IT registrar contract',
                'description' => 'Choose to accept Section 5 of .IT registrar contract',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'accept_duties' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Accept Section 6 of .IT registrar contract',
                'description' => 'Choose to accept Section 6 of .IT registrar contract',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'accept_explicit_consent' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Accept Section 7 of .IT registrar contract',
                'description' => 'Choose to accept Section 7 of .IT registrar contract',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .DE TLD
        'de' => [
            'tax_id' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Tax ID',
                'description' => 'Enter the Tax ID',
            ],
            'address_confirmation' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Address Confirmation',
                'description' => 'Confirm you have a valid German address',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'agree_to_terms' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Agree to DE Terms',
                'description' => 'Choose to agree to the DE Terms',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .AU TLD
        'au' => [
            'registrant_name' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Registrant Name',
                'description' => 'Enter the registrant name',
            ],
            'registrant_id' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Registrant ID',
                'description' => 'Enter the registrant ID',
            ],
            'registrant_id_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Registrant ID Type',
                'description' => 'Select the type of registrant ID',
                'options' => [
                    'ABN' => 'ABN',
                    'ACN' => 'ACN',
                    'Business Registration Number' => 'Business Registration Number',
                ],
            ],
            'eligibility_name' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Eligibility Name',
                'description' => 'Enter the Eligibility Name',
            ],
            'eligibility_id' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Eligibility ID',
                'description' => 'Enter the Eligibility ID',
            ],
            'eligibility_id_type' => [
                'type' => 'select',
                'required' => false,
                'name' => 'Eligibility ID Type',
                'description' => 'Select the type of Eligibility ID',
                'options' => [
                    'Australian Company Number (ACN)' => 'Australian Company Number (ACN)',
                    'ACT Business Number' => 'ACT Business Number',
                    'NSW Business Number' => 'NSW Business Number',
                    'NT Business Number' => 'NT Business Number',
                    'QLD Business Number' => 'QLD Business Number',
                    'SA Business Number' => 'SA Business Number',
                    'TAS Business Number' => 'TAS Business Number',
                    'VIC Business Number' => 'VIC Business Number',
                    'WA Business Number' => 'WA Business Number',
                    'Trademark (TM)' => 'Trademark (TM)',
                    'Other - Used to record an Incorporated Association number' => 'Other - Used to record an Incorporated Association number',
                    'Australian Business Number (ABN)' => 'Australian Business Number (ABN)',
                ],
            ],
            'eligibility_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Eligibility Type',
                'description' => 'Select the eligibility type',
                'options' => [
                    'Charity' => 'Charity',
                    'Citizen/Resident' => 'Citizen/Resident',
                    'Club' => 'Club',
                    'Commercial Statutory Body' => 'Commercial Statutory Body',
                    'Company' => 'Company',
                    'Incorporated Association' => 'Incorporated Association',
                    'Industry Body' => 'Industry Body',
                    'Non-profit Organisation' => 'Non-profit Organisation',
                    'Other' => 'Other',
                    'Partnership' => 'Partnership',
                    'Pending TM Owner' => 'Pending TM Owner',
                    'Political Party' => 'Political Party',
                    'Registered Business' => 'Registered Business',
                    'Religious/Church Group' => 'Religious/Church Group',
                    'Sole Trader' => 'Sole Trader',
                    'Trade Union' => 'Trade Union',
                    'Trademark Owner' => 'Trademark Owner',
                    'Child Care Centre' => 'Child Care Centre',
                    'Government School' => 'Government School',
                    'Higher Education Institution' => 'Higher Education Institution',
                    'National Body' => 'National Body',
                    'Non-Government School' => 'Non-Government School',
                    'Pre-school' => 'Pre-school',
                    'Research Organisation' => 'Research Organisation',
                    'Training Organisation' => 'Training Organisation',
                ],
            ],
            'eligibility_reason' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Eligibility Reason',
                'description' => 'Select the eligibility reason',
                'options' => [
                    'Domain name is an Exact Match Abbreviation or Acronym of your Entity or Trading Name.' => 'Domain name is an Exact Match Abbreviation or Acronym of your Entity or Trading Name.',
                    'Close and substantial connection between the domain name and the operations of your Entity.' => 'Close and substantial connection between the domain name and the operations of your Entity.',
                ],
            ],
        ],

        // .ASIA TLD
        'asia' => [
            'legal_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Legal Type',
                'description' => 'Select the legal type',
                'options' => [
                    'naturalPerson' => 'Natural Person',
                    'corporation' => 'Corporation',
                    'cooperative' => 'Cooperative',
                    'partnership' => 'Partnership',
                    'government' => 'Government',
                    'politicalParty' => 'Political Party',
                    'society' => 'Society',
                    'institution' => 'Institution',
                ],
            ],
            'identity_form' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Identity Form',
                'description' => 'Select the identity form',
                'options' => [
                    'passport' => 'Passport',
                    'certificate' => 'Certificate',
                    'legislation' => 'Legislation',
                    'societyRegistry' => 'Society Registry',
                    'politicalPartyRegistry' => 'Political Party Registry',
                ],
            ],
            'identity_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Identity Number',
                'description' => 'Enter the identity number',
            ],
        ],

        // .COOP TLD
        'coop' => [
            'contact_name' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Contact Name',
                'description' => 'A sponsor is required to register .coop domains. Please enter the contact name here',
            ],
            'contact_company' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Contact Company',
                'description' => 'Enter the contact company',
            ],
            'contact_email' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Contact Email',
                'description' => 'Enter the contact email',
            ],
            'address_1' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Address 1',
                'description' => 'Enter the first line of the address',
            ],
            'address_2' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Address 2',
                'description' => 'Enter the second line of the address (if applicable)',
            ],
            'city' => [
                'type' => 'text',
                'required' => true,
                'name' => 'City',
                'description' => 'Enter the city',
            ],
            'state' => [
                'type' => 'text',
                'required' => false,
                'name' => 'State',
                'description' => 'Enter the state (if applicable)',
            ],
            'zip_code' => [
                'type' => 'text',
                'required' => true,
                'name' => 'ZIP Code',
                'description' => 'Enter the ZIP code',
            ],
            'country' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Country',
                'description' => 'Please enter your country code (eg. FR, IT, etc...)',
            ],
            'phone_cc' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Phone CC',
                'description' => 'Phone Country Code eg 1 for US & Canada, 44 for UK',
            ],
            'phone' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Phone',
                'description' => 'Enter the phone number',
            ],
        ],

        // .CN TLD
        'cn' => [
            'cnhosting' => [
                'type' => 'select',
                'required' => false,
                'name' => 'Hosted in China?',
                'description' => 'Select whether the domain is hosted in China',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'cnhregisterclause' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Agree to the .CN Register Agreement',
                'description' => 'Agree to the .CN <a href="http://www1.cnnic.cn/PublicS/fwzxxgzcfg/201208/t20120830_35735.htm" target="_blank">Register Agreement</a>',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'owner_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Owner Type',
                'description' => 'Select the type of owner',
                'options' => [
                    'Individual' => 'Individual',
                    'Enterprise' => 'Enterprise',
                ],
            ],
            'id_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'ID Type',
                'description' => 'Select the type of ID',
                'options' => [
                    'Beijing School for Children of Foreign Embassy Staff in China Permit' => 'Beijing School for Children of Foreign Embassy Staff in China Permit',
                    'Business License' => 'Business License',
                    'Certificate for Uniform Social Credit Code' => 'Certificate for Uniform Social Credit Code',
                    'Exit-Entry Permit for Traveling to and from Hong Kong and Macao' => 'Exit-Entry Permit for Traveling to and from Hong Kong and Macao',
                    'Foreign Permanent Resident ID Card' => 'Foreign Permanent Resident ID Card',
                    'Fund Legal Person Registration Certificate' => 'Fund Legal Person Registration Certificate',
                    'Judicial Expertise License' => 'Judicial Expertise License',
                    'Medical Institution Practicing License' => 'Medical Institution Practicing License',
                    'Military Code Designation' => 'Military Code Designation',
                    'Military Paid External Service License' => 'Military Paid External Service License',
                    'Notary Organization Practicing License' => 'Notary Organization Practicing License',
                    'Officer\'s identity card' => 'Officer\'s identity card',
                    'Organization Code Certificate' => 'Organization Code Certificate',
                    'Others' => 'Others',
                    'Others-Certificate for Uniform Social Credit Code' => 'Others-Certificate for Uniform Social Credit Code',
                    'Overseas Organization Certificate' => 'Overseas Organization Certificate',
                    'Practicing License of Law Firm' => 'Practicing License of Law Firm',
                    'Private Non-Enterprise Entity Registration Certificate' => 'Private Non-Enterprise Entity Registration Certificate',
                    'Private School Permit' => 'Private School Permit',
                    'Public Institution Legal Person Certificate' => 'Public Institution Legal Person Certificate',
                    'Registration Certificate of Foreign Cultural Center in China' => 'Registration Certificate of Foreign Cultural Center in China',
                    'Religion Activity Site Registration Certificate' => 'Religion Activity Site Registration Certificate',
                    'Residence permit for Hong Kong and Macao residents' => 'Residence permit for Hong Kong and Macao residents',
                    'Residence permit for Taiwan residents' => 'Residence permit for Taiwan residents',
                    'Resident Representative Office of Tourism Departments of Foreign Government Approval Registration Certificate' => 'Resident Representative Office of Tourism Departments of Foreign Government Approval Registration Certificate',
                    'Resident Representative Offices of Foreign Enterprises Registration Form' => 'Resident Representative Offices of Foreign Enterprises Registration Form',
                    'Social Organization Legal Person Registration Certificate' => 'Social Organization Legal Person Registration Certificate',
                    'Social Service Agency Registration Certificate' => 'Social Service Agency Registration Certificate',
                    'Travel passes for Taiwan Residents to Enter or Leave the Mainland' => 'Travel passes for Taiwan Residents to Enter or Leave the Mainland',
                    'Passport' => 'Passport',
                ],
            ],
            'id_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'ID Number',
                'description' => 'Enter the ID number',
            ],
        ],

        // .FR TLD
        'fr' => [
            'legal_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Legal Type',
                'description' => 'Select the legal type',
                'options' => [
                    'Individual' => 'Individual',
                    'Company' => 'Company',
                ],
            ],
            'birthdate' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Birthdate',
                'description' => 'Enter birthdate (YYYY-MM-DD)',
            ],
            'birthplace_city' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Birthplace City',
                'description' => 'Enter birthplace city',
            ],
            'birthplace_country' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Birthplace Country',
                'description' => 'Enter the two-letter country code of birthplace (e.g., FR for France, DE for Germany)',
            ],
            'birthplace_postcode' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Birthplace Postcode',
                'description' => 'Enter birthplace postcode',
            ],
            'siret_number' => [
                'type' => 'text',
                'required' => false,
                'name' => 'SIRET Number',
                'description' => 'Enter SIRET Number for companies',
            ],
            'duns_number' => [
                'type' => 'text',
                'required' => false,
                'name' => 'DUNS Number',
                'description' => 'Enter DUNS Number',
            ],
            'vat_number' => [
                'type' => 'text',
                'required' => false,
                'name' => 'VAT Number',
                'description' => 'Enter VAT Number',
            ],
            'trademark_number' => [
                'type' => 'text',
                'required' => false,
                'name' => 'Trademark Number',
                'description' => 'Enter Trademark Number',
            ],
        ],

        // .NU TLD
        'nu' => [
            'identification_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Identification Number',
                'description' => 'Personal Identification Number (or Organization number), if you are an individual registrant (or organization) in Sweden',
            ],
            'vat_number' => [
                'type' => 'text',
                'required' => false,
                'name' => 'VAT Number',
                'description' => 'Optional VAT Number (for Swedish Organization)',
            ],
        ],

        // .QUEBEC TLD
        'quebec' => [
            'intended_use' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Intended Use',
                'description' => 'Describe the intended use of the domain',
            ],
        ],

        // .SCOT TLD
        'scot' => [
            'intended_use' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Intended Use',
                'description' => 'Describe the intended use of the domain',
            ],
            'terms_and_conditions' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Terms & Conditions',
                'description' => 'Do you agree to the Terms & Conditions?',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .JOBS TLD
        'jobs' => [
            'website' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Website',
                'description' => 'Enter the website associated with this .jobs domain',
            ],
        ],

        // .TRAVEL TLD
        'travel' => [
            'uin_code' => [
                'type' => 'text',
                'required' => false,
                'name' => '.TRAVEL UIN Code',
                'description' => 'Enter your Travel UIN Code obtained from https://www.authentication.travel/',
            ],
            'usage_agreement' => [
                'type' => 'select',
                'required' => true,
                'name' => '.TRAVEL Usage Agreement',
                'description' => 'I agree that .travel domains are restricted to those who are primarily active in the travel industry.',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .RU TLD
        'ru' => [
            'registrant_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Registrant Type',
                'description' => 'Select the type of registrant',
                'options' => [
                    'ORG' => 'Organization',
                    'IND' => 'Individual',
                ],
            ],
            'individuals_birthday' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['IND']],
                'name' => 'Individuals: Birthday (YYYY-MM-DD)',
                'description' => 'Enter the birthday for individual registrants',
            ],
            'individuals_passport_number' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['IND']],
                'name' => 'Individuals: Passport Number',
                'description' => 'Enter the passport number for individual registrants',
            ],
            'individuals_passport_issuer' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['IND']],
                'name' => 'Individuals: Passport Issuer',
                'description' => 'Enter the passport issuer for individual registrants',
            ],
            'individuals_passport_issue_date' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['IND']],
                'name' => 'Individuals: Passport Issue Date (YYYY-MM-DD)',
                'description' => 'Enter the passport issue date for individual registrants',
            ],
            'individuals_whois_privacy' => [
                'type' => 'select',
                'required' => ['registrant_type' => ['IND']],
                'name' => 'Individuals: Whois Privacy',
                'description' => 'Select whois privacy option for individual registrants',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'russian_organizations_taxpayer_number_1' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['ORG']],
                'name' => 'Russian Organizations: Taxpayer Number (ИНН)',
                'description' => 'Enter the taxpayer number for Russian organizations',
            ],
            'russian_organizations_territory_linked_taxpayer_number_2' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['ORG']],
                'name' => 'Russian Organizations: Territory-Linked Taxpayer Number (КПП)',
                'description' => 'Enter the territory-linked taxpayer number for Russian organizations',
            ],
        ],

        // .RO TLD
        'ro' => [
            'cnp_fiscal_code' => [
                'type' => 'text',
                'required' => true,
                'name' => 'CNP/Fiscal Code',
                'description' => 'Enter your CNP (for individuals) or Fiscal Code (for companies)',
            ],
            'registration_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Registration Number',
                'description' => 'Enter the registration number (for companies)',
            ],
            'registrant_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Registrant Type',
                'description' => 'Select the type of registrant',
                'options' => [
                    'p' => 'Private Person',
                    'ap' => 'Authorized Person',
                    'nc' => 'Non-Commercial Organization',
                    'c' => 'Commercial',
                    'gi' => 'Government Institute',
                    'pi' => 'Public Institute',
                    'o' => 'Other Juridicial',
                ],
            ],
        ],

        // .HK TLD
        'hk' => [
            'registrant_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Registrant Type',
                'description' => 'Select the type of registrant',
                'options' => [
                    'ind' => 'Individual',
                    'org' => 'Organization',
                ],
            ],
            'organization_chinese_name' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['org']],
                'name' => 'Organizations: Name in Chinese',
                'description' => 'Enter the organization name in Chinese (if applicable)',
            ],
            'organization_document_type' => [
                'type' => 'select',
                'required' => ['registrant_type' => ['org']],
                'name' => 'Organizations: Supporting Documentation',
                'description' => 'Select the type of supporting document for the organization',
                'options' => [
                    'BR' => 'Business Registration Certificate',
                    'CI' => 'Certificate of Incorporation',
                    'CRS' => 'Certificate of Registration of a School',
                    'HKSARG' => 'Hong Kong Special Administrative Region Gov\'t Dept.',
                    'HKORDINANCE' => 'Ordinance of Hong Kong',
                ],
            ],
            'organization_document_number' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['org']],
                'name' => 'Organizations: Document Number',
                'description' => 'Enter the document number for the selected supporting documentation',
            ],
            'organization_country' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['org']],
                'name' => 'Organizations: Issuing Country',
                'description' => 'Enter the two-letter country code (e.g., HK for Hong Kong, US for United States) that issued the supporting document',
            ],
            'organization_industry_type' => [
                'type' => 'select',
                'required' => ['registrant_type' => ['org']],
                'name' => 'Organizations: Industry Type',
                'description' => 'Select the industry type of the organization',
                'options' => [
                    '010100' => 'Plastics / Petro-Chemicals / Chemicals - Plastics & Plastic Products',
                    '010200' => 'Plastics / Petro-Chemicals / Chemicals - Rubber & Rubber Products',
                    '010300' => 'Plastics / Petro-Chemicals / Chemicals - Fibre Materials & Products',
                    '010400' => 'Plastics / Petro-Chemicals / Chemicals - Petroleum / Coal & Other Fuels',
                    '010500' => 'Plastics / Petro-Chemicals / Chemicals - Chemicals & Chemical Products',
                    '020100' => 'Metals / Machinery / Equipment - Metal Materials & Treatment',
                    '020200' => 'Metals / Machinery / Equipment - Metal Products',
                    '020300' => 'Metals / Machinery / Equipment - Industrial Machinery & Supplies',
                    '020400' => 'Metals / Machinery / Equipment - Precision & Optical Equipment',
                    '020500' => 'Metals / Machinery / Equipment - Moulds & Dies',
                    '030100' => 'Printing / Paper / Publishing - Printing / Photocopying / Publishing',
                    '030200' => 'Printing / Paper / Publishing - Paper / Paper Products',
                    '040100' => 'Construction / Decoration / Environmental Engineering - Construction Contractors',
                    '040200' => 'Construction / Decoration / Environmental Engineering - Construction Materials',
                    '040300' => 'Construction / Decoration / Environmental Engineering - Decoration Materials',
                    '040400' => 'Construction / Decoration / Environmental Engineering - Construction / Safety Equipment & Supplies',
                    '040500' => 'Construction / Decoration / Environmental Engineering - Decoration / Locksmiths / Plumbing & Electrical Works',
                    '040600' => 'Construction / Decoration / Environmental Engineering - Fire Protection Equipment & Services',
                    '040700' => 'Construction / Decoration / Environmental Engineering - Environmental Engineering / Waste Reduction',
                    '050100' => 'Textiles / Clothing & Accessories - Textiles / Fabric',
                    '050200' => 'Textiles / Clothing & Accessories - Clothing',
                    '050300' => 'Textiles / Clothing & Accessories - Uniforms / Special Clothing',
                    '050400' => 'Textiles / Clothing & Accessories - Clothing Manufacturing Accessories',
                    '050500' => 'Textiles / Clothing & Accessories - Clothing Processing & Equipment',
                    '050600' => 'Textiles / Clothing & Accessories - Fur / Leather & Leather Goods',
                    '050700' => 'Textiles / Clothing & Accessories - Handbags / Footwear / Optical Goods / Personal Accessories',
                    '060100' => 'Electronics / Electrical Appliances - Electronic Equipment & Supplies',
                    '060200' => 'Electronics / Electrical Appliances - Electronic Parts & Components',
                    '060300' => 'Electronics / Electrical Appliances - Electrical Appliances / Audio-Visual Equipment',
                    '070100' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Kitchenware / Tableware',
                    '070200' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Bedding',
                    '070300' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Bathroom / Cleaning Accessories',
                    '070400' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Household Goods',
                    '070500' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Wooden / Bamboo & Rattan Goods',
                    '070600' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Home Furnishings / Arts & Crafts',
                    '070700' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Watches / Clocks',
                    '070800' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Jewellery Accessories',
                    '070900' => 'Houseware / Watches / Clocks / Jewellery / Toys / Gifts - Toys / Games / Gifts',
                    '080100' => 'Business & Professional Services / Finance - Accounting / Legal Services',
                    '080200' => 'Business & Professional Services / Finance - Advertising / Promotion Services',
                    '080300' => 'Business & Professional Services / Finance - Consultancy Services',
                    '080400' => 'Business & Professional Services / Finance - Translation / Design Services',
                    '080500' => 'Business & Professional Services / Finance - Cleaning / Pest Control Services',
                    '080600' => 'Business & Professional Services / Finance - Security Services',
                    '080700' => 'Business & Professional Services / Finance - Trading / Business Services',
                    '080800' => 'Business & Professional Services / Finance - Employment Services',
                    '080900' => 'Business & Professional Services / Finance - Banking / Finance / Investment',
                    '081000' => 'Business & Professional Services / Finance - Insurance',
                    '081100' => 'Business & Professional Services / Finance - Property / Real Estate',
                    '090100' => 'Transportation / Logistics - Land Transport / Motorcars',
                    '090200' => 'Transportation / Logistics - Sea Transport / Boats',
                    '090300' => 'Transportation / Logistics - Air Transport',
                    '090400' => 'Transportation / Logistics - Moving / Warehousing / Courier & Logistics Services',
                    '090500' => 'Transportation / Logistics - Freight Forwarding',
                    '100100' => 'Office Equipment / Furniture / Stationery / Information Technology - Office / Commercial Equipment & Supplies',
                    '100200' => 'Office Equipment / Furniture / Stationery / Information Technology - Office & Home Furniture',
                    '100300' => 'Office Equipment / Furniture / Stationery / Information Technology - Stationery & Educational Supplies',
                    '100400' => 'Office Equipment / Furniture / Stationery / Information Technology - Telecommunication Equipment & Services',
                    '100500' => 'Office Equipment / Furniture / Stationery / Information Technology - Computers / Information Technology',
                    '110100' => 'Food / Flowers / Fishing & Agriculture - Food Products & Supplies',
                    '110200' => 'Food / Flowers / Fishing & Agriculture - Beverages / Tobacco',
                    '110300' => 'Food / Flowers / Fishing & Agriculture - Restaurant Equipment & Supplies',
                    '110400' => 'Food / Flowers / Fishing & Agriculture - Flowers / Artificial Flowers / Plants',
                    '110500' => 'Food / Flowers / Fishing & Agriculture - Fishing',
                    '110600' => 'Food / Flowers / Fishing & Agriculture - Agriculture',
                    '120100' => 'Medical Services / Beauty / Social Services - Medicine & Herbal Products',
                    '120200' => 'Medical Services / Beauty / Social Services - Medical & Therapeutic Services',
                    '120300' => 'Medical Services / Beauty / Social Services - Medical Equipment & Supplies',
                    '120400' => 'Medical Services / Beauty / Social Services - Beauty / Health',
                    '120500' => 'Medical Services / Beauty / Social Services - Personal Services',
                    '120600' => 'Medical Services / Beauty / Social Services - Organizations / Associations',
                    '120700' => 'Medical Services / Beauty / Social Services - Information / Media',
                    '120800' => 'Medical Services / Beauty / Social Services - Public Utilities',
                    '120900' => 'Medical Services / Beauty / Social Services - Religion / Astrology / Funeral Services',
                    '130100' => 'Culture / Education - Music / Arts',
                    '130200' => 'Culture / Education - Learning Instruction & Training',
                    '130300' => 'Culture / Education - Elementary Education',
                    '130400' => 'Culture / Education - Tertiary Education / Other Education Services',
                    '130500' => 'Culture / Education - Sporting Goods',
                    '130600' => 'Culture / Education - Sporting / Recreational Facilities & Venues',
                    '130700' => 'Culture / Education - Hobbies / Recreational Activities',
                    '130800' => 'Culture / Education - Pets / Pets Services & Supplies',
                    '140101' => 'Dining / Entertainment / Shopping / Travel - Restaurant Guide - Chinese',
                    '140102' => 'Dining / Entertainment / Shopping / Travel - Restaurant Guide - Asian',
                    '140103' => 'Dining / Entertainment / Shopping / Travel - Restaurant Guide - Western',
                    '140200' => 'Dining / Entertainment / Shopping / Travel - Catering Services / Eateries',
                    '140300' => 'Dining / Entertainment / Shopping / Travel - Entertainment Venues',
                    '140400' => 'Dining / Entertainment / Shopping / Travel - Entertainment Production & Services',
                    '140500' => 'Dining / Entertainment / Shopping / Travel - Entertainment Equipment & Facilities',
                    '140600' => 'Dining / Entertainment / Shopping / Travel - Shopping Venues',
                    '140700' => 'Dining / Entertainment / Shopping / Travel - Travel / Hotels & Accommodation',
                ],
            ],
            'individual_document_type' => [
                'type' => 'select',
                'required' => ['registrant_type' => ['ind']],
                'name' => 'Individuals: Supporting Documentation',
                'description' => 'Select the type of supporting document for the individual',
                'options' => [
                    'HKID' => 'Hong Kong Identity Number',
                    'OTHID' => 'Other Country Identity Number',
                    'PASSNO' => 'Passport No.',
                    'BIRTHCERT' => 'Birth Certificate',
                ],
            ],
            'individual_document_number' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['ind']],
                'name' => 'Individuals: Document Number',
                'description' => 'Enter the document number for the selected supporting documentation',
            ],
            'individual_country' => [
                'type' => 'text',
                'required' => ['registrant_type' => ['ind']],
                'name' => 'Individuals: Issuing Country',
                'description' => 'Enter the two-letter country code (e.g., HK for Hong Kong, US for United States) that issued the supporting document',
            ],
            'individual_under_18' => [
                'type' => 'select',
                'required' => ['registrant_type' => ['ind']],
                'name' => 'Individuals: Under 18 Years old?',
                'description' => 'Is the registrant under 18 years old?',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .AERO TLD
        'aero' => [
            'aero_id' => [
                'type' => 'text',
                'required' => true,
                'name' => '.AERO ID',
                'description' => 'Enter your .AERO ID obtained from http://www.information.aero/',
            ],
            'aero_key' => [
                'type' => 'text',
                'required' => false,
                'name' => '.AERO Key',
                'description' => 'Enter your .AERO Key (if applicable)',
            ],
        ],

        // .PL TLD
        'pl' => [
            'publish_contact' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Publish Contact in .PL WHOIS',
                'description' => 'Choose whether to publish your contact information in the .PL WHOIS database',
                'options' => [
                    'yes' => 'Yes',
                    'no' => 'No',
                ],
            ],
        ],

        // .SE TLD
        'se' => [
            'identification_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Identification Number',
                'description' => 'For Swedish Residents: Personal or Organization Number; For residents of other countries: Civic Registration Number, Company Registration Number or Passport Number',
            ],
            'vat' => [
                'type' => 'text',
                'required' => false,
                'name' => 'VAT',
                'description' => 'VAT number (Required for EU companies not located in Sweden)',
            ],
        ],

        // .VOTE TLD
        'vote' => [
            'agreement' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Agreement',
                'description' => 'I confirm a bona fide intention to use the domain name, during the current/relevant election cycle, in connection with a clearly identified political/democratic process at the time of registration.',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .SWISS TLD
        'swiss' => [
            'core_intended_use' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Core Intended Use',
                'description' => 'Describe the intended use of the domain',
            ],
            'registrant_enterprise_id' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Registrant Enterprise ID',
                'description' => 'Enter the Swiss Registrant Enterprise ID',
            ],
        ],

        // .EU TLD
        'eu' => [
            'entity_type' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Entity Type',
                'description' => 'EURid Geographical Restrictions. In order to register a .EU domain name, you must meet certain eligibility requirements.',
                'options' => [
                    'COMPANY' => 'Company - Undertakings having their registered office or central administration and/or principal place of business within the European Community',
                    'INDIVIDUAL' => 'Individual - Natural persons resident within the European Community',
                    'ORGANIZATION' => 'Organization - Organizations established within the European Community without prejudice to the application of national law',
                ],
            ],
            'eu_country_of_citizenship' => [
                'type' => 'select',
                'required' => true,
                'name' => 'EU Country of Citizenship',
                'description' => 'Select your country of citizenship within the EU',
                'options' => [
                    'AT' => 'Austria', 
                    'BE' => 'Belgium', 
                    'BG' => 'Bulgaria', 
                    'CY' => 'Cyprus',
                    'CZ' => 'Czech Republic', 
                    'DE' => 'Germany', 
                    'DK' => 'Denmark', 
                    'EE' => 'Estonia',
                    'EL' => 'Greece', 
                    'ES' => 'Spain', 
                    'FI' => 'Finland', 
                    'FR' => 'France',
                    'HR' => 'Croatia', 
                    'HU' => 'Hungary', 
                    'IE' => 'Ireland', 
                    'IT' => 'Italy',
                    'LT' => 'Lithuania', 
                    'LU' => 'Luxembourg', 
                    'LV' => 'Latvia', 
                    'MT' => 'Malta',
                    'NL' => 'Netherlands', 
                    'PL' => 'Poland', 
                    'PT' => 'Portugal', 
                    'RO' => 'Romania',
                    'SE' => 'Sweden', 
                    'SI' => 'Slovenia', 
                    'SK' => 'Slovakia',
                    'AX' => 'Åland Islands', 
                    'GF' => 'French Guiana', 
                    'GP' => 'Guadeloupe',
                    'MQ' => 'Martinique', 
                    'RE' => 'Réunion',
                ],
            ],
        ],

        // .DEV TLD
        'dev' => [
            'dev_agree' => [
                'type' => 'select',
                'required' => true,
                'name' => '.DEV SSL Agreement',
                'description' => '.Dev is a more secure domain, meaning that HTTPS is required for all .dev websites. You can buy your .Dev domain name now, but in order for it to work properly in browsers you must first configure HTTPS/SSL to serve the domain. I, the Registrant, understand and agree to the .dev Registration Policy.',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .APP TLD
        'app' => [
            'app_agree' => [
                'type' => 'select',
                'required' => true,
                'name' => '.APP SSL Agreement',
                'description' => '.app is a more secure domain, meaning that HTTPS is required for all .app websites. You can buy your .app domain name now, but in order for it to work properly in browsers you must first configure HTTPS/SSL to serve the domain. I, the Registrant, understand and agree to the .app Registration Policy.',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .BIO TLD
        'bio' => [
            'bio_agree' => [
                'type' => 'select',
                'required' => true,
                'name' => '.BIO SSL Agreement',
                'description' => '.bio is a more secure domain, meaning that HTTPS is required for all .bio websites. You can buy your .bio domain name now, but in order for it to work properly in browsers you must first configure HTTPS/SSL to serve the domain. I, the Registrant, understand and agree to the .bio Registration Policy.',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
        ],

        // .BR TLD
        'br' => [
            'br_register_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Register Number',
                'description' => 'The CPF is the financial identity number provided by the Brazilian Government for every Brazilian citizen in order to charge taxes and financial matters. The CNPJ is the same as the CPF but it works for companies. CPF must be given in the following format: NNN.NNN.NNN-NN. CNPJ must be given in the following format: NN.NNN.NNN/NNNN-NN',
            ],
        ],

        // DENTIST TLD
        'dentist' => [
            'regulatory_data' => [
                'type' => 'text',
                'required' => true,
                'name' => 'Regulatory Data',
                'description' => 'Enter your professional license number or other regulatory data',
            ],
        ],

        // .HU TLD
        'hu' => [
            'trustee_service' => [
                'type' => 'select',
                'required' => false,
                'name' => 'Accept Trustee Service',
                'description' => 'Choose whether to accept the trustee service',
                'options' => [
                    'Yes' => 'Yes',
                    'No' => 'No',
                ],
            ],
            'id_number' => [
                'type' => 'text',
                'required' => true,
                'name' => 'ID Card or Passport Number',
                'description' => 'Enter your ID card or passport number',
            ],
            'vat_number' => [
                'type' => 'text',
                'required' => false,
                'name' => 'VAT Number',
                'description' => 'VAT number (required for organisations)',
            ],
        ],

        // .NYC TLD
        'nyc' => [
            'nexusCategory' => [
                'type' => 'select',
                'required' => true,
                'name' => 'Nexus Category',
                'description' => 'The .NYC top-level domain is available only to New York City businesses and organizations with a NYC address or individuals with a primary residence in NYC. Registrants must be either individuals whose primary place of domicile is a valid physical address in the City of New York ("Nexus Category 1 - Individual") or entities, organizations, or companies that have a physical street address in the City of New York ("Nexus Category 2 - Organization").',
                'options' => [
                    'NEXUS_CATEGORY_2' => 'Individual',
                    'NEXUS_CATEGORY_1' => 'Organization',
                ],
            ],
        ],
    ];

    $uk              = $fields["uk"];
    $sg              = $fields["sg"];
    $au              = $fields["au"];
    $cn              = $fields["cn"];
    $fr              = $fields["fr"];
    $ru              = $fields["ru"];
    $ro              = $fields["ro"];
    $hk              = $fields["hk"];
    $pl              = $fields["pl"];
    $se              = $fields["se"];
    $br              = $fields["br"];
    $com_es          = $fields["com.es"];
    $quebec          = $fields["quebec"];
    $dentist         = $fields["dentist"];


    $fields["co.uk"] = $uk;
    $fields["me.uk"] = $uk;
    unset($uk["whois_optout"]);
    $fields["net.uk"] = $uk;
    $fields["org.uk"] = $uk;
    $fields["plc.uk"] = $uk;
    $fields["ltd.uk"] = $uk;

    $fields["com.sg"] = $sg;
    $fields["edu.sg"] = $sg;
    $fields["net.sg"] = $sg;
    $fields["org.sg"] = $sg;
    $fields["per.sg"] = $sg;

    $fields["com.au"]   = $au;
    $fields["net.au"]   = $au;
    $fields["org.au"]   = $au;
    $fields["asn.au"]   = $au;
    $fields["id.au"]    = $au;

    $fields["com.cn"]    = $cn;
    $fields["net.cn"]    = $cn;
    $fields["org.cn"]    = $cn;

    $fields["re"]    = $fr;
    $fields["pm"]    = $fr;
    $fields["tf"]    = $fr;
    $fields["wf"]    = $fr;
    $fields["yt"]    = $fr;

    $fields["xn--p1ai"] = $ru;

    $fields["arts.ro"] = $ro;
    $fields["co.ro"] = $ro;
    $fields["com.ro"] = $ro;
    $fields["firm.ro"] = $ro;
    $fields["info.ro"] = $ro;
    $fields["nom.ro"] = $ro;
    $fields["nt.ro"] = $ro;
    $fields["org.ro"] = $ro;
    $fields["rec.ro"] = $ro;
    $fields["ro.ro"] = $ro;
    $fields["store.ro"] = $ro;
    $fields["tm.ro"] = $ro;
    $fields["www.ro"] = $ro;

    $fields["com.hk"]   = $hk;
    $fields["edu.hk"]   = $hk;
    $fields["gov.hk"]   = $hk;
    $fields["idv.hk"]   = $hk;
    $fields["net.hk"]   = $hk;
    $fields["org.hk"]   = $hk;

    $fields['pc.pl'] = $pl;
    $fields['miasta.pl'] = $pl;
    $fields['atm.pl'] = $pl;
    $fields['rel.pl'] = $pl;
    $fields['gmina.pl'] = $pl;
    $fields['szkola.pl'] = $pl;
    $fields['sos.pl'] = $pl;
    $fields['media.pl'] = $pl;
    $fields['edu.pl'] = $pl;
    $fields['auto.pl'] = $pl;
    $fields['agro.pl'] = $pl;
    $fields['turystyka.pl'] = $pl;
    $fields['gov.pl'] = $pl;
    $fields['aid.pl'] = $pl;
    $fields['nieruchomosci.pl'] = $pl;
    $fields['com.pl'] = $pl;
    $fields['priv.pl'] = $pl;
    $fields['tm.pl'] = $pl;
    $fields['travel.pl'] = $pl;
    $fields['info.pl'] = $pl;
    $fields['org.pl'] = $pl;
    $fields['net.pl'] = $pl;
    $fields['sex.pl'] = $pl;
    $fields['sklep.pl'] = $pl;
    $fields['powiat.pl'] = $pl;
    $fields['mail.pl'] = $pl;
    $fields['realestate.pl'] = $pl;
    $fields['shop.pl'] = $pl;
    $fields['mil.pl'] = $pl;
    $fields['nom.pl'] = $pl;
    $fields['gsm.pl'] = $pl;
    $fields['tourism.pl'] = $pl;
    $fields['targi.pl'] = $pl;
    $fields['biz.pl'] = $pl;

    $fields['tm.se'] = $se;
    $fields['org.se'] = $se;
    $fields['pp.se'] = $se;
    $fields['parti.se'] = $se;
    $fields['presse.se'] = $se;

    $fields["voto"] = $fields["vote"];

    $fields["nom.es"] = $com_es;
    $fields["org.es"] = $com_es;

    $fields['com.br'] = $br;
    $fields['abc.br'] = $br;
    $fields['belem.br'] = $br;
    $fields['blog.br'] = $br;
    $fields['emp.br'] = $br;
    $fields['esp.br'] = $br;
    $fields['far.br'] = $br;
    $fields['floripa.br'] = $br;
    $fields['ind.br'] = $br;
    $fields['jampa.br'] = $br;
    $fields['macapa.br'] = $br;
    $fields['net.br'] = $br;
    $fields['org.br'] = $br;
    $fields['poa.br'] = $br;
    $fields['recife.br'] = $br;
    $fields['rio.br'] = $br;
    $fields['sjc.br'] = $br;
    $fields['tur.br'] = $br;
    $fields['tv.br'] = $br;
    $fields['vix.br'] = $br;

    $fields['attorney'] = $dentist;
    $fields['lawyer'] = $dentist;

    $fields["co.hu"] = $fields["hu"];

    return $fields;