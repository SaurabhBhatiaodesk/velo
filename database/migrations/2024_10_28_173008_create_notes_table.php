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
        Schema::create('notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('note');
            $table->unsignedBigInteger('user_id');
            $table->string('notable_type');
            $table->unsignedBigInteger('notable_id')->nullable();
            $table->timestamps();
            $table->string('notable_slug')->nullable()->index('notes_notable_slug_foreign');

            $table->index(['notable_type', 'notable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notes');
    }
};
