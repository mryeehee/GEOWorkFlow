<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_directions', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->default('queued');
            $table->json('inputs_json')->nullable();
            $table->json('suggestions_json')->nullable();
            $table->text('answer_text')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->references('id')->on('admins')->nullOnDelete();
            $table->foreignId('ai_visibility_run_id')->nullable()->references('id')->on('ai_visibility_runs')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_directions');
    }
};
