<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HmrcVatReturn extends Model
{
    protected $table = 'hmrc_vat_returns';

    protected $fillable = [
        'user_id',
        'instructor_id',
        'vrn',
        'period_key',
        'vat_due_sales_pence',
        'vat_due_acquisitions_pence',
        'total_vat_due_pence',
        'vat_reclaimed_curr_period_pence',
        'net_vat_due_pence',
        'total_value_sales_ex_vat_pence',
        'total_value_purchases_ex_vat_pence',
        'total_value_goods_supplied_ex_vat_pence',
        'total_acquisitions_ex_vat_pence',
        'finalised',
        'submitted_at',
        'processing_date',
        'form_bundle_number',
        'charge_ref_number',
        'payment_indicator',
        'correlation_id',
        'request_payload',
        'response_payload',
        'digital_records_attested_at',
        'digital_records_attested_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'vat_due_sales_pence' => 'integer',
            'vat_due_acquisitions_pence' => 'integer',
            'total_vat_due_pence' => 'integer',
            'vat_reclaimed_curr_period_pence' => 'integer',
            'net_vat_due_pence' => 'integer',
            'total_value_sales_ex_vat_pence' => 'integer',
            'total_value_purchases_ex_vat_pence' => 'integer',
            'total_value_goods_supplied_ex_vat_pence' => 'integer',
            'total_acquisitions_ex_vat_pence' => 'integer',
            'finalised' => 'boolean',
            'submitted_at' => 'datetime',
            'processing_date' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'digital_records_attested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function attestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digital_records_attested_by_user_id');
    }
}
