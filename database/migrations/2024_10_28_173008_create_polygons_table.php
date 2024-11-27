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
        Schema::create('polygons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('active');
            $table->unsignedBigInteger('shipping_code_id');
            $table->unsignedBigInteger('courier_id');
            $table->timestamps();
            $table->double('max_range', 8, 2)->nullable();
            $table->double('min_range', 8, 2)->nullable();
            $table->json('pickup_polygon')->nullable();
            $table->longText('pickup_country')->nullable();
            $table->longText('pickup_state')->nullable();
            $table->longText('pickup_city')->nullable();
            $table->longText('pickup_zipcode')->nullable();
            $table->json('dropoff_polygon')->nullable();
            $table->longText('dropoff_country')->nullable();
            $table->longText('dropoff_state')->nullable();
            $table->longText('dropoff_city')->nullable();
            $table->longText('dropoff_zipcode')->nullable();
            $table->integer('pickup_max_days')->nullable();
            $table->integer('dropoff_max_days')->nullable();
            $table->string('store_slug')->nullable()->index('polygons_store_slug_foreign');
            $table->double('min_weight', 8, 2)->nullable();
            $table->double('max_weight', 8, 2)->nullable();
            $table->double('initial_free_km', 8, 2)->nullable();
            $table->json('min_dimensions')->nullable();
            $table->json('max_dimensions')->nullable();
            $table->double('min_monthly_deliveries', 8, 2)->nullable();
            $table->boolean('scheduled_pickup')->default(false);
            $table->string('timezone')->nullable();
            $table->string('cutoff')->nullable();
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->json('fields')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->boolean('tax_included')->default(false);
            $table->json('required_connections')->nullable();
            $table->boolean('external_pricing')->default(false);
            $table->boolean('is_collection')->default(false);
            $table->integer('min_pickups')->nullable();
            $table->boolean('has_push')->default(false);
            $table->boolean('external_availability_check')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('polygons');
    }
};
