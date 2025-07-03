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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('expert_id');
            $table->dateTime('from');
            $table->dateTime('to');
            $table->decimal('deposit_amount', 8, 2);
            $table->string('payment_intent_id');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'confirmed', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->boolean('is_completed')->default(false);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('expert_id')->references('id')->on('experts')->onDelete('cascade');
            $table->boolean('is_open')->default(1);
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();


        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointment');
    }
};
