<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAclTables extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('policy_json'); // JSON field to store the policy
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_scopeable')->default(false);
            $table->foreignId('resource_id')->constrained('resources')->onDelete('cascade');
            $table->unique(['name', 'resource_id']);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_policy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('policy_id')->constrained('policies')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('team_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('team_policy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('policy_id')->constrained('policies')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('model_has_policy', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->foreignId('policy_id')->constrained('policies')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('model_has_role', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('model_has_team', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_team');
        Schema::dropIfExists('model_has_role');
        Schema::dropIfExists('model_has_policy');
        Schema::dropIfExists('team_policy');
        Schema::dropIfExists('team_role');
        Schema::dropIfExists('role_policy');
        Schema::dropIfExists('actions');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('policies');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('roles');
    }
}
