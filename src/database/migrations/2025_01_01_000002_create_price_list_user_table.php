<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('price_list_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('price_list_id')
                ->constrained('price_lists')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->unique(['price_list_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_list_user');
    }
};
