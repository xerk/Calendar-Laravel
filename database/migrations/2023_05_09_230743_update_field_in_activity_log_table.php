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

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('subject_id');
            $table->dropColumn('subject_type');
            $table->dropColumn('causer_id');
            $table->dropColumn('causer_type');
        });

        Schema::table('activity_log', function (Blueprint $table) {

            $table->nullableUuidMorphs('subject', 'subject');
            $table->nullableUuidMorphs('causer', 'causer');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activity_log', function (Blueprint $table) {
                $table->dropColumn('subject_id');
                $table->dropColumn('subject_type');
                $table->dropColumn('causer_id');
                $table->dropColumn('causer_type');
        });
        Schema::table('activity_log', function (Blueprint $table) {
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
        });
    }
};
