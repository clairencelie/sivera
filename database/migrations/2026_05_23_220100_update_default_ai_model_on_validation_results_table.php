<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE validation_results MODIFY ai_model_used VARCHAR(255) NOT NULL DEFAULT 'gemini-2.5-flash'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE validation_results MODIFY ai_model_used VARCHAR(255) NOT NULL DEFAULT 'gemini-1.5-flash'");
    }
};
