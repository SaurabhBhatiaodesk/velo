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
        Schema::create('stores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('website')->unique();
            $table->string('timezone')->default('Asia/Jerusalem');
            $table->integer('week_starts_at')->default(1);
            $table->json('weekly_deliveries_schedule');
            $table->unsignedBigInteger('currency_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->boolean('always_show_next_day_options')->default(true);
            $table->boolean('validate_inventory')->default(false);
            $table->boolean('validate_weight')->default(false);
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->boolean('enterprise_billing')->default(false);
            $table->softDeletes();
            $table->json('pricing_settings')->nullable();
            $table->boolean('imperial_units')->default(false);
            $table->string('tax_id')->nullable();
            $table->integer('volume')->default(0);
            $table->double('billing_limit', 8, 2)->nullable();
            $table->boolean('suspended')->default(false);
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable()->index('stores_blocked_by_foreign');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stores');
    }
};
