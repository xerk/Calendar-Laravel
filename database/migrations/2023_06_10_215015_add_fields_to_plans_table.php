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
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('calendars')->default(0)->after('original_yearly_price');
            $table->integer('bookings')->default(0)->after('calendars');
            $table->integer('teams')->default(0)->after('bookings');
            $table->integer('members')->default(0)->after('teams');
        });

        // Subscription count usage for each plan for each month
        Schema::create('subscription_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->integer('calendars')->default(0);
            $table->integer('bookings')->default(0);
            $table->integer('teams')->default(0);
            $table->integer('members')->default(0);
            $table->date('date')->index()->nullable();
            $table->timestamps();
        });

        // Add current_subscription_usage_id to subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('current_subscription_usage_id')->after('plan_data')->nullable()->constrained('subscription_usages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plans', function (Blueprint $table) {
            $columns = ['calendars', 'bookings', 'teams', 'members'];
            $table->dropColumn($columns);
        });

        Schema::dropIfExists('subscription_usages');

        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['current_subscription_usage_id']);
            $table->dropColumn('current_subscription_usage_id');
        });
    }
};
