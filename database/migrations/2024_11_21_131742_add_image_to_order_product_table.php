<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageToOrderProductTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('order_product', 'image')) {
            Schema::table('order_product', function (Blueprint $table) {
                $table->string('image')->nullable()->after('product_id');
            });
        }
    }

    public function down()
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
}
