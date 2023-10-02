<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nid',
                'user_id',
                'subscription_id',
                'status',
                'type',
                'amount',
                'paygate',
                'paygate_response',
                'billing_address',
                'billing_city',
                'billing_region',
                'billing_country',
                'billing_zipcode',
                'refund_reason',
                'refunded_by',
                'refunded_at',
                'vat_amount',
                'vat_percentage',
                'total',
                'vat_country'
            ]);
        // Chain fluent methods for configuration options
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subscription_id',
        'status',
        'paygate',
        'paygate_response',
        'amount',
        'type',
        'billing_address',
        'billing_city',
        'billing_region',
        'billing_country',
        'billing_zipcode',
        'refund_reason',
        'refunded_by',
        'refunded_at',
        'vat_amount',
        'vat_percentage',
        'total',
        'vat_country'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'paygate_response' => 'array',
        'refunded_at' => 'timestamp',
    ];

    public function getHid()
    {
        return config('saas.transaction_prefix').'-'.str_pad($this->nid,9, '0', STR_PAD_LEFT);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function getSubscriptionNumber()
    {
        return config('saas.subscription_prefix').'-'.str_pad($this->subscription_id,9, '0', STR_PAD_LEFT);
    }

    public function getPaymentStatusMessage()
    {
        if($this->type == 'recurring' || $this->type == 'refund' || $this->type == 'changed') {
            return isset($this->paygate_response['payment_result']['response_message']) ? $this->paygate_response['payment_result']['response_message'] : 'N/A';
        }
        return isset($this->paygate_response['respMessage']) ? $this->paygate_response['respMessage'] : 'N/A';
    }

    public function getPaymentTransRef()
    {
        if($this->type == 'recurring' || $this->type == 'refund' || $this->type == 'changed') {
            return isset($this->paygate_response['tran_ref']) ? $this->paygate_response['tran_ref'] : 'N/A';
        }
        return isset($this->paygate_response['tranRef']) ? $this->paygate_response['tranRef'] : 'N/A';
    }

    // attribute currency
    public function getCurrencyAttribute()
    {
        return $this->subscription->plan_data['currency'] ?? 'USD';
    }
}
