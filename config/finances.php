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
        'imported' => 'Imported (legacy)',
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
        // Must match the rows in `category_tax_mapping`; the picker reads
        // `category_meta` from that table, so a slug missing here fails validation.
        'servicing' => 'Servicing',
        'repairs' => 'Repairs',
        'road_tax' => 'Road Tax',
        'breakdown_cover' => 'Breakdown Cover',
        'vehicle_insurance' => 'Vehicle Insurance',
        'business_insurance' => 'Business Insurance',
        'phone' => 'Phone',
        'accountant_fees' => 'Accountant Fees',
    ],

    'payment_categories' => [
        'none' => 'None',
        'imported' => 'Imported (legacy)',
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

    'recurrence_frequencies' => [
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Annually',
    ],

    'recurrence' => [
        // Total occurrences in a series, including the first record.
        'max_iterations' => 60,
    ],

    'receipt' => [
        // Kilobytes — Laravel's `max` rule on uploaded files is in KB.
        // Android uploads untouched camera photos (3–8 MB, up to ~20 MB); they're
        // shrunk server-side by ProcessFinanceReceiptImageJob. The advertised limit
        // is also capped by PHP's upload_max_filesize / post_max_size.
        'max_size_kb' => 20480,
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'signed_url_ttl_minutes' => 20,

        // Background shrink of JPEG/PNG receipts (PDFs are stored as uploaded).
        'processing' => [
            // Long edge of the stored receipt — keeps print legible at ~200–500 KB.
            'max_edge_px' => 1600,
            'jpeg_quality' => 75,
            // Long edge of the list thumbnail returned as `receipt.thumbnail_url`.
            'thumbnail_edge_px' => 320,
            'thumbnail_jpeg_quality' => 70,
        ],
    ],
];
