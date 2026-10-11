<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Justification du score : une ligne par critère, avec l'explication lisible.
        Schema::create('score_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('criterion', 30);
            $table->string('label', 60);
            $table->unsignedTinyInteger('points');
            $table->unsignedTinyInteger('max_points');
            $table->string('explanation', 500);
            $table->timestamp('created_at')->nullable();
            $table->unique(['candidate_id', 'criterion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_details');
    }
};
