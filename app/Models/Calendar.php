<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Calendar extends Model
{
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly([
            'user_id',
            'is_show_on_booking_page',
            'is_on',
            'name',
            'slug',
            'welcome_message',
            'locations',
            'availability_id',
            'disable_guests',
            'requires_confirmation',
            'redirect_on_booking',
            'invitees_emails',
            'enable_signup_form_after_booking',
            'color',
            'cover_url',
            'time_slots_intervals',
            'duration',
            'invitees_can_schedule',
            'buffer_time',
            'additional_questions',
            'is_isolate',
            'is_exceed_limit'
        ]);
        // Chain fluent methods for configuration options
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    protected $fillable = ['user_id',
    'is_show_on_booking_page',
    'is_on',
    'name',
    'slug',
    'welcome_message',
    'locations',
    'availability_id',
    'disable_guests',
    'requires_confirmation',
    'redirect_on_booking',
    'invitees_emails',
    'enable_signup_form_after_booking',
    'color',
    'cover_url',
    'time_slots_intervals',
    'duration',
    'invitees_can_schedule',
    'buffer_time',
    'additional_questions',
    'is_isolate',
    'is_exceed_limit'
];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_on' => 'boolean',
        'is_isolate' => 'boolean',
        'is_show_on_booking_page' => 'boolean',
        'locations' => 'array',
        'disable_guests' => 'boolean',
        'requires_confirmation' => 'boolean',
        'invitees_emails' => 'array',
        'enable_signup_form_after_booking' => 'boolean',
        'invitees_can_schedule' => 'array',
        'buffer_time' => 'array',
        'additional_questions' => 'array',
        'is_exceed_limit' => 'boolean',
        'custom_select_intervals' => 'array',
        'custom_select_duration' => 'array',
    ];

    public function availability()
    {
        return $this->belongsTo(Availability::class)->select(['id', 'name', 'user_id', 'data', 'timezone']);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
