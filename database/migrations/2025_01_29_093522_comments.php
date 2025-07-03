<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

public function up()
{
    Schema::create('comments', function (Blueprint $table) {
        $table->id();

        // السماح بأن يكون userable_id و userable_type NULL
        $table->nullableMorphs('userable');

        $table->foreignId('post_id')->constrained()->onDelete('cascade');
        $table->text('body');
        $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');

        $table->timestamps();
    });
}

public function down()
{
Schema::dropIfExists('comments');
}
};
