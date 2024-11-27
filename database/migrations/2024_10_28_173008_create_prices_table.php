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
        Schema::create('prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->double('price', 8, 2);
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('currency_id');
            $table->string('priceable_type');
            $table->unsignedBigInteger('priceable_id')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('priceable_slug')->nullable()->index('prices_priceable_slug_foreign');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->json('data')->nullable();

            $table->index(['priceable_type', 'priceable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prices');
    }
};
