<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no', 32)->unique();
            $table->string('name', 100);
            $table->string('email')->index();
            $table->string('phone', 30)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('subject', 150);
            $table->text('message');

            $table->enum('source', [
                'website',
                'campaign',
                'referral',
                'social_media',
                'email',
                'other',
            ])->default('website')->index();

            $table->string('utm_source', 150)->nullable();
            $table->string('utm_medium', 150)->nullable();
            $table->string('utm_campaign', 150)->nullable();

            $table->enum('status', [
                'new',
                'contacted',
                'qualified',
                'pending',
                'won',
                'lost',
            ])->default('new')->index();

            $table->enum('priority', [
                'low',
                'medium',
                'high',
            ])->default('medium')->index();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });

        // SQLite is supported for portable feature tests. Production MySQL
        // retains the required full-text index.
        if (DB::getDriverName() === 'mysql') {
            Schema::table('inquiries', function (Blueprint $table): void {
                $table->fullText(
                    ['name', 'email', 'subject', 'message'],
                    'inquiries_search_fulltext'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
