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
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('billing_zipcode');
            $table->foreignId('refunded_by')->nullable()->after('billing_zipcode');
            $table->string('refund_reason')->nullable()->after('billing_zipcode');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('refunded_at');
            $table->dropColumn('refunded_by');
            $table->dropColumn('refund_reason');
        });
    }
};
