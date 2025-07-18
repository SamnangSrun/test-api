<?php

// database/migrations/xxxx_xx_xx_create_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    // database/migrations/xxxx_xx_xx_create_categories_table.php

public function up()
{
    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('parent_id')->nullable()->constrained('categories');
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('categories');
}

}

