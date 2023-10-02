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
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id', 100);
            $table->string('provider')->nullable();
            $table->string('name');
            $table->string('email')->index();
            $table->string('picture');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('scopes')->nullable();
            $table->string('meeting_type')->nullable();
            $table->text('sync_token')->nullable();
            $table->dateTime('expires_at')->nullable();
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
        Schema::dropIfExists('provider_accounts');
    }
};
