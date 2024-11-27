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
        Schema::create('polygon_connections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('polygon_id');
            $table->string('polygon_connectable_type')->nullable();
            $table->unsignedBigInteger('polygon_connectable_id')->nullable();
            $table->string('polygon_connectable_slug')->nullable()->index('polygon_connections_polygon_connectable_slug_foreign');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['polygon_connectable_type', 'polygon_connectable_id'], 'p_con_morph_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('polygon_connections');
    }
};
