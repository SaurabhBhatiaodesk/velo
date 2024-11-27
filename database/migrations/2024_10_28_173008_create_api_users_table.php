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
        Schema::create('api_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key');
            $table->string('secret');
            $table->string('nonce')->nullable();
            $table->timestamps();
            $table->string('slug')->default('enterprise');
            $table->boolean('active')->default(false);
            $table->string('store_slug')->nullable()->index('api_users_store_slug_foreign');
            $table->json('settings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_users');
    }
};
