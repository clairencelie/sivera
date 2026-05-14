<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rab_item_id')->constrained('rab_items')->cascadeOnDelete();
            $table->string('ai_model_used')->default('gemini-1.5-flash');
            $table->string('found_item_name')->nullable(); // nama item yg ditemukan AI
            $table->boolean('is_equivalent')->default(false); // apakah item adalah substitusi
            $table->decimal('price_min', 15, 2)->nullable();
            $table->decimal('price_max', 15, 2)->nullable();
            $table->text('reference_url')->nullable(); // URL bukti harga dari AI
            $table->enum('status', ['Wajar', 'Overprice', 'Underprice', 'Tidak Ditemukan'])->default('Tidak Ditemukan');
            $table->text('reasoning')->nullable(); // penjelasan singkat AI
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_results');
    }
};
