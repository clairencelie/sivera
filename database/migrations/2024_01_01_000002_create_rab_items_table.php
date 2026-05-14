<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->enum('category', ['Persiapan & Akhir', 'Pekerjaan Utama']);
            $table->string('item_name');
            $table->text('specification')->nullable();
            $table->decimal('volume', 12, 2);
            $table->string('unit', 50); // m, m2, m3, unit, ls, dll.
            $table->decimal('proposed_price', 15, 2); // harga satuan usulan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_items');
    }
};
