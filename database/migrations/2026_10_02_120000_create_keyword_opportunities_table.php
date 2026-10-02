<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('brand_keyword', 160);
            $table->string('keyword', 200);
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('source_domain', 160)->nullable();
            $table->string('status', 20)->default('new');
            $table->json('analysis_json')->nullable();
            $table->json('evidence_json')->nullable();
            $table->foreignId('imported_keyword_id')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('last_mined_at')->nullable();
            $table->timestamps();

            $table->unique(['brand_keyword', 'keyword']);
            $table->index(['status', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_opportunities');
    }
};
