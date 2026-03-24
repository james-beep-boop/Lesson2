<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Queried on nearly every authenticated page load to determine role
        Schema::table('subject_grades', function (Blueprint $table) {
            $table->index('subject_admin_user_id', 'sg_subject_admin_user_id_idx');
        });

        // Inbox listing (to_user_id + created_at) and unread badge (to_user_id + read_at)
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['to_user_id', 'read_at', 'created_at'], 'messages_inbox_idx');
            $table->index('from_user_id', 'messages_from_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subject_grades', function (Blueprint $table) {
            $table->dropIndex('sg_subject_admin_user_id_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_inbox_idx');
            $table->dropIndex('messages_from_user_id_idx');
        });
    }
};
