<?php

// $table->id();
//             $table->foreignId('booking_id')->nullable()->constrained()->onDelete('cascade');
//             $table->foreignId('provider_account_id')->nullable()->constrained()->onDelete('cascade');
//             $table->string('event_id');
//             $table->string('provider_type');
//             $table->json('response')->nullable();
//             $table->string('status')->default('pending');
//             $table->string('error')->nullable();

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'provider_account_id',
        'event_id',
        'provider_type',
        'response',
        'status',
        'error'
    ];

    protected $casts = [
        'response' => 'array'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function providerAccount()
    {
        return $this->belongsTo(ProviderAccount::class);
    }
}
