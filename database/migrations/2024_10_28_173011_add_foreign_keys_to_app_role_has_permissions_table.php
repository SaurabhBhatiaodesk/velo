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
        Schema::table('app_role_has_permissions', function (Blueprint $table) {
            $table->foreign(['permission_id'])->references(['id'])->on('app_permissions')->onUpdate('NO ACTION')->onDelete('CASCADE');
            $table->foreign(['role_id'])->references(['id'])->on('app_roles')->onUpdate('NO ACTION')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('app_role_has_permissions', function (Blueprint $table) {
            $table->dropForeign('app_role_has_permissions_permission_id_foreign');
            $table->dropForeign('app_role_has_permissions_role_id_foreign');
        });
    }
};
