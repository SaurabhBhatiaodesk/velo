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
        Schema::create('addresses_he', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('street');
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('country');
            $table->unsignedBigInteger('address_id');
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
        Schema::dropIfExists('addresses_he');
    }
};
