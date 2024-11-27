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
        Schema::table('store_user', function (Blueprint $table) {
            $table->foreign(['store_slug'])->references(['slug'])->on('stores')->onUpdate('NO ACTION')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_user', function (Blueprint $table) {
            $table->dropForeign('store_user_store_slug_foreign');
        });
    }
};
