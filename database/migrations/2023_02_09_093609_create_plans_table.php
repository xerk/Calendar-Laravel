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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('name');
            $table->string('currency')->default('USD');
            $table->string('original_monthly_price')->nullable()->comment('Null if not available');
            $table->string('monthly_price')->nullable()->comment('Null if not available');
            $table->string('yearly_price')->nullable()->comment('Null if not available');
            $table->string('original_yearly_price')->nullable()->comment('Null if not available');
            $table->text('description')->nullable();
            $table->unsignedInteger('auto_refund_before_hours')->default(48);
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
        Schema::dropIfExists('plans');
    }
};
