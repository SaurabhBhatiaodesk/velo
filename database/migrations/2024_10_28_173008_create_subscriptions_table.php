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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('store_slug')->index('subscriptions_store_slug_foreign');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->unsignedBigInteger('renewed_from')->nullable();
            $table->string('subscribable_type');
            $table->unsignedBigInteger('subscribable_id');
            $table->timestamps();

            $table->index(['subscribable_type', 'subscribable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
};
