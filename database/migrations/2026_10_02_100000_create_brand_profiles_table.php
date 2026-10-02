<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('brand_name', 160)->default('');
            $table->json('brand_aliases')->nullable();
            $table->json('brand_keywords')->nullable();
            $table->text('business_scope')->nullable();
            $table->json('industries')->nullable();
            $table->json('official_domains')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_profiles');
    }
};
