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
        Schema::table('bookings', function (Blueprint $table) {
            $table
                ->uuid('reschedule_uuid')
                ->nullable()
                ->after('rescheduled_at')
                ->unique();
            $table->timestamp('reschedule_token_expires_at')->nullable();
            $table->enum('created_by', ['host', 'invitee'])->default('invitee');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {

            $table->dropColumn('reschedule_uuid');
            $table->dropColumn('reschedule_token_expires_at');
            $table->dropColumn('created_by');
        });
    }
};
