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
        Schema::create('shopify_shops', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain')->unique();
            $table->string('shopify_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->string('store_slug')->nullable()->index('shopify_shops_store_slug_foreign');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shopify_shops');
    }
};
