<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'order',
                'level',
                'is_active',
                'name',
                'currency',
                'original_monthly_price',
                'monthly_price',
                'yearly_price',
                'original_yearly_price',
                'description',
                'auto_refund_before_hours',
            ]);
        // Chain fluent methods for configuration options
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order',
        'level',
        'name',
        'currency',
        'original_monthly_price',
        'monthly_price',
        'original_yearly_price',
        'yearly_price',
        'description',
        'calendars',
        'bookings',
        'teams',
        'members',
        'country'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Return monthly/yearly price with vat if user country is in SA
     */
    // public function getMonthlyPriceAttribute($value)
    // {
    //     return $value + ($this->getTaxRate() / 100 * $value);
    // }

    /**
     * Return monthly/yearly price with vat if user country is in SA
     */
    // public function getYearlyPriceAttribute($value) {
    //     return $value + ($this->getTaxRate() / 100 * $value);
    // }

    /**
     * Return Currency Symbol based on user country
     */
    public function getCurrencySymbolAttribute() {
        return 'SAR';
    }



    /**
     * Get currency by user
     */
    public function getCurrency($user) {
        return 'SAR';
    }

    public function getTaxRate() {
        $taxRate = \DB::table('nova_settings')->where('key', 'tax')->first();

        if (!$taxRate) {
            return 15;
        }
        return $taxRate->value;
    }
}
