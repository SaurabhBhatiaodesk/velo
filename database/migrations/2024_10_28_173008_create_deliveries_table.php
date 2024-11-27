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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('courier_responses')->nullable();
            $table->string('remote_id')->nullable();
            $table->unsignedBigInteger('order_id')->index();
            $table->timestamps();
            $table->unsignedBigInteger('polygon_id');
            $table->json('pickup_address');
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->unsignedBigInteger('ready_by')->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->unsignedBigInteger('pickup_by')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('delivered_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->boolean('is_return')->default(false);
            $table->boolean('is_replacement')->default(false);
            $table->string('store_slug')->nullable()->index();
            $table->json('dimensions')->nullable();
            $table->double('weight', 8, 2)->nullable();
            $table->string('barcode')->nullable();
            $table->string('courier_status')->nullable();
            $table->string('line_number')->nullable();
            $table->string('external_service_id')->nullable();
            $table->string('external_service_name')->nullable();
            $table->string('external_courier_name')->nullable();
            $table->timestamp('scheduled_pickup_starts_at')->nullable();
            $table->timestamp('scheduled_pickup_ends_at')->nullable();
            $table->timestamp('commercial_invoice_transmitted_at')->nullable();
            $table->enum('status', ['placed', 'updated', 'accept_failed', 'pending_accept', 'accepted', 'pending_pickup', 'transit', 'transit_to_destination', 'transit_to_warehouse', 'transit_to_sender', 'in_warehouse', 'pending_cancel', 'service_cancel', 'data_problem', 'cancelled', 'delivered', 'rejected', 'refunded', 'failed'])->default('placed');
            $table->boolean('has_push')->default(false);
            $table->timestamp('estimated_dropoff_starts_at')->nullable();
            $table->timestamp('estimated_dropoff_ends_at')->nullable();

            $table->index(['barcode', 'remote_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
};
