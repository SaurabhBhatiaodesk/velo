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
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('description');
            $table->json('transaction_data');
            $table->double('total', 8, 2);
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('store_slug')->nullable()->index();
            $table->string('invoice_remote_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
