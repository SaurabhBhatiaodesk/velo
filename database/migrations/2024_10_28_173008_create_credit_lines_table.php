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
        Schema::create('credit_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('description');
            $table->double('total', 8, 2);
            $table->unsignedBigInteger('currency_id');
            $table->string('creditable_type')->nullable();
            $table->unsignedBigInteger('creditable_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('store_slug')->index('credit_lines_store_slug_foreign');
            $table->timestamps();

            $table->index(['creditable_type', 'creditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credit_lines');
    }
};
