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
        Schema::create('addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('zipcode')->nullable();
            $table->string('state')->nullable();
            $table->string('country');
            $table->string('phone');
            $table->string('longitude');
            $table->string('latitude');
            $table->string('addressable_type');
            $table->unsignedBigInteger('addressable_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->string('addressable_slug')->nullable()->index();
            $table->string('company_name')->nullable();
            $table->boolean('is_billing')->default(false);
            $table->boolean('is_pickup')->default(false);

            $table->index(['addressable_type', 'addressable_id']);
            $table->index(['country', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('addresses');
    }
};
