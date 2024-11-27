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
        Schema::create('shipping_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code');
            $table->double('min_weight', 8, 2)->nullable();
            $table->double('max_weight', 8, 2)->nullable();
            $table->double('initial_free_km', 8, 2)->nullable();
            $table->json('min_dimensions')->nullable();
            $table->json('max_dimensions')->nullable();
            $table->double('min_monthly_deliveries', 8, 2)->nullable();
            $table->timestamps();
            $table->boolean('is_same_day')->default(false);
            $table->boolean('is_on_demand')->default(false);
            $table->boolean('is_return')->default(false);
            $table->boolean('is_international')->default(false);
            $table->boolean('is_replacement')->default(false);
            $table->integer('pickup_max_days')->nullable();
            $table->integer('dropoff_max_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shipping_codes');
    }
};
