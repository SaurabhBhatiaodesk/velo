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
        Schema::table('polygon_connections', function (Blueprint $table) {
            $table->foreign(['polygon_connectable_slug'])->references(['slug'])->on('stores')->onUpdate('NO ACTION')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('polygon_connections', function (Blueprint $table) {
            $table->dropForeign('polygon_connections_polygon_connectable_slug_foreign');
        });
    }
};
