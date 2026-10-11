<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();

            // Informations personnelles
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 190)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('country', 80);
            $table->string('linkedin_url', 300)->nullable();
            $table->string('github_url', 300)->nullable();
            $table->string('portfolio_url', 300)->nullable();

            // Formation
            $table->string('education_level', 20);
            $table->string('education_field', 30);
            $table->string('institution', 150)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();

            // Expérience
            $table->decimal('experience_years', 4, 1);
            $table->string('experience_type', 20);
            $table->text('projects_description')->nullable();
            $table->text('motivation')->nullable();

            // Disponibilité
            $table->string('availability', 20);
            $table->date('available_from')->nullable();

            // Résultat du scoring (le détail est dans score_details)
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('category', 20)->index();
            $table->unsignedTinyInteger('projects_points')->default(0); // critère de départage du classement
            $table->json('flags')->nullable();
            $table->string('scoring_version', 20);

            // Revue humaine
            $table->string('review_status', 20)->default('nouveau');
            $table->text('reviewer_note')->nullable();

            $table->timestamps();
            $table->index(['score', 'projects_points', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
