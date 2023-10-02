<?php

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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('title')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verify_otp')->nullable();
            $table->string('old_email_verify_otp')->nullable();
            $table->string('password');
            $table->string('display_language')->default('en');
            $table->json('languages')->nullable();
            $table->string('timezone')->default('Asia/Kuwait');
            $table->string('profile_photo_url')->nullable();
            $table->foreignId('plan_id')->default(1);
            $table->foreignUuid('default_availability_id')->nullable();
            $table->foreignId('current_subscription_id')->nullable();
            $table->timestamp('last_online_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->default(1);
            $table->string('suspended_reason')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->default(1);
            $table->string('deactivated_reason')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_region')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_zipcode')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
