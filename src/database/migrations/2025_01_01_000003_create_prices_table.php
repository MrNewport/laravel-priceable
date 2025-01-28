<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->morphs('priceable');

            // Price list association, or null for default
            $table->foreignId('price_list_id')
                ->nullable()
                ->constrained('price_lists')
                ->onDelete('cascade');

            // Tiered quantity
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();

            // Monetary fields
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->string('currency')->default('USD');

            // Validity dates for time-limited pricing
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();

            $table->timestamps();

            $table->index(['priceable_id', 'priceable_type', 'min_quantity', 'max_quantity']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('prices');
    }
};
