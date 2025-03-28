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
        Schema::create('experts', function (Blueprint $table) {

                $table->id();
                $table->string('userName');
                $table->string('mobile' , 10);
                $table->string('imagePath')->nullable();
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('section_id');
                $table->text('experience');
                $table->float('rate')->default(0);
                $table->string('email')->unique();
                $table->string('timezone');
               // $table->time('start_time');
                //$table->time('end_time');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');

            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');

                $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('experts');
    }
};
