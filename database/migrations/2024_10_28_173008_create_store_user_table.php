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
        Schema::create('store_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->timestamp('invited_at')->useCurrent();
            $table->string('token')->nullable();
            $table->string('store_role')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->string('store_slug')->index('store_user_store_slug_foreign');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('store_user');
    }
};
