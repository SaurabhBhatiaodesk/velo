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
        Schema::create('bills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('description');
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->double('total', 8, 2);
            $table->unsignedBigInteger('currency_id');
            $table->softDeletes();
            $table->string('billable_slug')->nullable()->index('bills_billable_slug_foreign');
            $table->string('store_slug')->nullable()->index();
            $table->json('taxes')->nullable();
            $table->double('cost', 8, 2)->nullable();

            $table->index(['billable_type', 'billable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bills');
    }
};
