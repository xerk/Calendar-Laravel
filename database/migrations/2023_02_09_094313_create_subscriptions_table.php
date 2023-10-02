<?php

use App\Models\Plan;
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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained('users')->onDelete('cascade');
            $table->foreignIdFor(Plan::class)->constrained('plans')->onDelete('cascade');
            $table->date('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('paygate_token')->nullable();
            $table->string('paygate_first_trans_ref')->nullable();
            $table->unsignedTinyInteger('period')->default(1)->comment('1: Monthly, 2: Yearly');
            $table->json('plan_data')->nullable();
            $table->boolean('is_yearly_auto_renew')->default(true);
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
        Schema::dropIfExists('subscriptions');
    }
};
