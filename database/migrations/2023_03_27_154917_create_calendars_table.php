<?php

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
        Schema::create('calendars', function (Blueprint $table) {
            $table->uuid('id')->unique()->primary();
            $table->foreignIdFor(User::class)->constrained('users')->onDelete('cascade');
            $table->boolean('is_show_on_booking_page')->default(true);
            $table->boolean('is_on')->default(true);
            $table->string('name');
            $table->string('slug')->index();
            $table->text('welcome_message')->nullable();
            $table->json('locations')->nullable();
            $table->foreignUuid('availability_id');
            $table->boolean('disable_guests')->default(false);
            $table->boolean('requires_confirmation')->default(false);
            $table->string('redirect_on_booking', 1000)->nullable();
            $table->json('invitees_emails')->nullable();
            $table->boolean('enable_signup_form_after_booking')->default(false);
            $table->string('color')->nullable();
            $table->string('cover_url', 1000)->nullable();
            $table->string('time_slots_intervals')->nullable();
            $table->string('duration')->nullable();
            $table->json('invitees_can_schedule')->nullable();
            $table->json('buffer_time')->nullable();
            $table->json('additional_questions')->nullable();
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
        Schema::dropIfExists('calendars');
    }
};
