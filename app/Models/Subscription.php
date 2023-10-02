<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'user_id',
                'plan_id',
                'expires_at',
                'failed_payment_count',
                'original_expires_at',
                'cancelled_at',
                'status',
                'paygate_token',
                'paygate_first_trans_ref',
                'plan_data',
                'period',
                'is_yearly_auto_renew',
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
        'plan_id',
        'expires_at',
        'original_expires_at',
        'cancelled_at',
        'paygate_token',
        'period',
        'plan_data',
        'is_yearly_auto_renew',
        'current_subscription_usage_id',
        'next_plan_id',
        'next_period',
        'paygate_first_trans_ref'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'date',
        'original_expires_at' => 'date',
        'cancelled_at' => 'datetime',
        'plan_data' => 'array',
        'is_yearly_auto_renew' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function getSubscriptionNumber()
    {
        return config('saas.subscription_prefix').'-'.str_pad($this->id,9, '0', STR_PAD_LEFT);
    }

    public function lastTransaction()
    {
        return $this->hasOne(Transaction::class)->where('status', '!=', 'pending')->latest();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->where('status', '!=', 'pending');
    }

    public function currentSubscriptionUsage() {
        return $this->belongsTo(SubscriptionUsage::class, 'current_subscription_usage_id');
    }

    public function subscriptionUsages() {
        return $this->hasMany(SubscriptionUsage::class);
    }

    public function nextPlan() {
        return $this->belongsTo(Plan::class, 'next_plan_id');
    }
}
