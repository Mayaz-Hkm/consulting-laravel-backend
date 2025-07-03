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
        DB::statement("
        ALTER TABLE appointments
        MODIFY COLUMN status
        ENUM('pending', 'accepted', 'rejected', 'confirmed', 'cancelled')
        NOT NULL DEFAULT 'pending'
    ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("
        ALTER TABLE appointments
        MODIFY COLUMN status
        ENUM('pending', 'confirmed', 'cancelled')
        NOT NULL DEFAULT 'pending'
    ");
    }
};
