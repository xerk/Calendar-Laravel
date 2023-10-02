<?php

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained('users')->onDelete('cascade');
            $table->foreignUuid('calendar_id');
            $table->date('date');
            $table->string('start');
            $table->string('end');
            $table->string('invitee_name');
            $table->string('invitee_email');
            $table->string('invitee_phone')->nullable();
            $table->text('invitee_notes')->nullable();
            $table->string('timezone');
            $table->json('location')->nullable();
            $table->json('other_invitees')->nullable();
            $table->json('additional_answers')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->boolean('is_confirmed')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};
