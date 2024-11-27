<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->timestamp('commercial_invoice_uploaded_at')->nullable();
            $table->string('courier_name')->nullable();
            $table->string('courier_phone')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('external_tracking_url')->nullable();
            $table->json('pickup_images')->nullable();
            $table->json('dropoff_images')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('commercial_invoice_uploaded_at');
            $table->dropColumn('receiver_name');
            $table->dropColumn('external_tracking_url');
            $table->dropColumn('dropoff_images');
        });
    }
};
