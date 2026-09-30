<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('reference_sequences', function (Blueprint $table): void {
            $table->date('date')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });

        // Locking this persistent row serializes assignment decisions.
        // It also works when there are currently no eligible agents.
        Schema::create('assignment_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });

        DB::table('assignment_locks')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_locks');
        Schema::dropIfExists('reference_sequences');
        Schema::dropIfExists('settings');
    }
};
