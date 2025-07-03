<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('categoryName');
            $table->string('imagePath')->nullable();
            $table->timestamps();
        });

        // إضافة قيم افتراضية بعد إنشاء الجدول
        \App\Models\Category::create(['categoryName' => 'Programming']);
        \App\Models\Category::create(['categoryName' => 'Health']);
        \App\Models\Category::create(['categoryName' => 'Engineering']);
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
};
