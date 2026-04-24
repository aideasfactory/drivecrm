<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Finance Section — Categories, Payment Methods, Receipt Constraints
|--------------------------------------------------------------------------
|
| Single source of truth for the admin finance section (and, later, the
| mobile API). Slugs are stored in the database; labels are rendered in
| the UI. Add or remove entries here without a migration — validation at
| FormRequest / inline controller level reads from this config.
|
*/

return [
    'expense_categories' => [
        'none' => 'None',
        'our_account' => 'Our Account',
        'advertising' => 'Advertising',
        'association' => 'Association',
        'bank_charges' => 'Bank Charges',
        'computer_dvsa_fees' => 'Computer DVSA Fees',
        'equipment' => 'Equipment',
        'food_drink' => 'Food/Drink',
        'fuel' => 'Fuel',
        'insurance' => 'Insurance',
        'internet' => 'Internet',
        'mot' => 'MOT',
    ],

    'payment_categories' => [
        'none' => 'None',
        'franchise_payout' => 'Franchise Payout',
        'hmrc_tax' => 'HMRC Tax',
        'insurance' => 'Insurance',
        'referral' => 'Referral',
        'pupil_transfer_referral' => 'Pupil Transfer Referral',
    ],

    'payment_methods' => [
        'bank_transfer' => 'Bank Transfer',
        'card' => 'Card',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'direct_debit' => 'Direct Debit',
        'paypal' => 'PayPal',
        'standing_order' => 'Standing Order',
    ],

    'mileage_types' => [
        'business' => 'Business',
        'personal' => 'Personal',
    ],

    'receipt' => [
        // Kilobytes — Laravel's `max` rule on uploaded files is in KB.
        'max_size_kb' => 10240,
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'signed_url_ttl_minutes' => 20,
    ],
];
