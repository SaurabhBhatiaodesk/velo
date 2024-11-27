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
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('source');
            $table->text('note')->nullable();
            $table->double('total', 8, 2);
            $table->unsignedBigInteger('currency_id');
            $table->unsignedBigInteger('pickup_address_id')->nullable();
            $table->unsignedBigInteger('shipping_address_id')->nullable();
            $table->unsignedBigInteger('billing_address_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->string('shopify_id')->nullable();
            $table->string('store_slug')->nullable()->index();
            $table->string('external_id')->nullable();
            $table->double('declared_value', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
