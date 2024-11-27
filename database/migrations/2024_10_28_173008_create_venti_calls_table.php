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
        Schema::create('venti_calls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nonce');
            $table->unsignedBigInteger('original_order_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('store_slug')->index('venti_calls_store_slug_foreign');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('new_order_id')->nullable();
            $table->json('customer_address')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('expiry')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('social_id')->nullable();
            $table->string('token')->nullable();
            $table->string('invoice_remote_id')->nullable();
            $table->json('transaction_data')->nullable();
            $table->double('total', 8, 2)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->boolean('is_replacement')->default(false);
            $table->double('cost', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venti_calls');
    }
};
