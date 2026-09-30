<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_health_findings', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('key');
            $table->morphs('model');
            $table->json('context');
            $table->nullableMorphs('assignee');
            $table->string('urgency');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_health_findings');
    }
};
