<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('books', function (Blueprint $table) {
            // Remove this line since cover_image already exists:
            // $table->string('cover_image')->nullable();

            // Just add the new column
            $table->string('cover_public_id')->nullable()->after('cover_image');
        });
    }

    public function down()
    {
        Schema::table('books', function (Blueprint $table) {
            // Only drop the new column
            $table->dropColumn('cover_public_id');
        });
    }
};
