<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('database_name')->nullable()->after('schedule_id');
        });

        // Populate database_name from the connection's db field for existing records
        DB::table('backups')
            ->whereNull('database_name')
            ->whereNotNull('connection_id')
            ->lazyById()
            ->each(function (object $backup): void {
                $db = DB::table('connections')->where('id', $backup->connection_id)->value('db');
                DB::table('backups')->where('id', $backup->id)->update(['database_name' => $db]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('database_name');
        });
    }
};
