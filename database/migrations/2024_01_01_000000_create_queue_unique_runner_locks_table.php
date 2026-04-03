<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('queue-unique-runner.table', 'queue_unique_runner_locks'), function (Blueprint $table) {
            $table->id();
            $table->string('job_key')->unique();
            $table->string('server_id');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('heartbeat_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('queue-unique-runner.table', 'queue_unique_runner_locks'));
    }
};
