<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('athlete_health_clearances');
    }

    public function down(): void
    {
        // Legacy table removal is intentionally not reversed.
    }
};
