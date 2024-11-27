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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('mask');
            $table->string('holder_name');
            $table->string('expiry');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('social_id')->nullable();
            $table->string('token');
            $table->boolean('default')->default(false);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->string('store_slug')->nullable()->index('payment_methods_store_slug_foreign');
            $table->string('card_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
};
