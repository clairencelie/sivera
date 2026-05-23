<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validation_results', function (Blueprint $table) {
            $table->json('source_urls')->nullable()->after('reference_url');
            $table->json('web_search_queries')->nullable()->after('source_urls');
            $table->json('grounding_metadata')->nullable()->after('web_search_queries');
            $table->unsignedInteger('latency_ms')->nullable()->after('grounding_metadata');
            $table->text('api_error')->nullable()->after('latency_ms');
        });
    }

    public function down(): void
    {
        Schema::table('validation_results', function (Blueprint $table) {
            $table->dropColumn([
                'source_urls',
                'web_search_queries',
                'grounding_metadata',
                'latency_ms',
                'api_error',
            ]);
        });
    }
};
