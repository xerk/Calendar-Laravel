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
            // Vat amount
            $table->decimal('vat_amount', 9, 3)->default(0)->after('amount');
            // Vat percentage
            $table->decimal('vat_percentage', 9, 3)->default(0)->after('vat_amount');
            // Vat total
            $table->decimal('total', 9, 3)->default(0)->after('vat_percentage');
            // vat country
            $table->string('vat_country')->nullable()->after('total');
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
                $table->dropColumn('vat_amount');
                $table->dropColumn('vat_percentage');
                $table->dropColumn('total');
                $table->dropColumn('vat_country');
        });
    }
};
