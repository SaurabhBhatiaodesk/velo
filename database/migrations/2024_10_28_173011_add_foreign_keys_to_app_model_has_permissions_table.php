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
        Schema::table('app_model_has_permissions', function (Blueprint $table) {
            $table->foreign(['permission_id'])->references(['id'])->on('app_permissions')->onUpdate('NO ACTION')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('app_model_has_permissions', function (Blueprint $table) {
            $table->dropForeign('app_model_has_permissions_permission_id_foreign');
        });
    }
};
