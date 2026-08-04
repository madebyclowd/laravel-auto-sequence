<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the database connection for the migration.
     */
    public function getConnection(): ?string
    {
        return config('auto-sequence.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('auto-sequence.table', 'sequences');

        Schema::connection($this->getConnection())->table($tableName, function (Blueprint $table) {
            $table->timestamp('exhausted_notified_at')->nullable()->after('format_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('auto-sequence.table', 'sequences');

        Schema::connection($this->getConnection())->table($tableName, function (Blueprint $table) {
            $table->dropColumn('exhausted_notified_at');
        });
    }
};
