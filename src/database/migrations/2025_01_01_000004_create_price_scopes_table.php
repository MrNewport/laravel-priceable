<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('price_scopes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('price_id')
                ->constrained('prices')
                ->onDelete('cascade');

            // For region, channel, or any other scope
            $table->string('scope_type');
            $table->string('scope_value');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_scopes');
    }
};
