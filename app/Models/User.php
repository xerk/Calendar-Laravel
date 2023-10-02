<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use App\Http\Resources\PlanResource;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'email',
                'username',
                'title',
                'description',
                'email_verified_at',
                'email_verify_otp',
                'old_email_verify_otp',
                'display_language',
                'languages',
                'timezone',
                'profile_photo_url',
                'plan_id',
                'default_availability_id',
                'current_subscription_id',
                'last_online_at',
                'suspended_at',
                'suspended_by',
                'suspended_reason',
                'deactivated_at',
                'deactivated_by',
                'deactivated_reason',
                'billing_address',
                'billing_city',
                'billing_region',
                'billing_country',
                'billing_zipcode',
                'default_payment_method_id',
            ]);
        // Chain fluent methods for configuration options
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'languages',
        'email_verify_otp',
        'last_online_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // append
    protected $appends = [
        'current_plan',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_online_at' => 'datetime',
        'suspended_at' => 'datetime',
        'languages' => 'array',
    ];

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function currentSubscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function defaultAvailability()
    {
        return $this->belongsTo(Availability::class, 'default_availability_id');
    }

    public function getCurrentPlanAttribute()
    {
        $freePlan = Plan::find(1);
        $currentPlan = new PlanResource($freePlan);
        if ($this->currentSubscription) {
            $currentPlan = $this->currentSubscription->plan_data;
            $currentPlan['description'] = $currentPlan['description'] ? explode("\n", $currentPlan['description']) : [];
        }
        return $currentPlan;
    }

    public function calendars()
    {
        return $this->hasMany(Calendar::class);
    }

    public function providers() {
        return $this->hasMany(ProviderAccount::class);
    }

    public function googleProvider()
    {
        return $this->hasOne(ProviderAccount::class)->where('provider', 'google');
    }
    public function zoomProvider()
    {
        return $this->hasOne(ProviderAccount::class)->where('provider', 'zoom');
    }
}
