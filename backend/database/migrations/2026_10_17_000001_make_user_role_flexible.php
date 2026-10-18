<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use a string column so additional account roles can be added without enum restrictions.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE users RENAME COLUMN role TO role_legacy');
            DB::statement('ALTER TABLE users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT "faculty"');
            DB::statement('UPDATE users SET role = role_legacy');
            DB::statement('ALTER TABLE users DROP COLUMN role_legacy');
        }
    }

    public function down(): void
    {
        // Keep the column flexible on rollback; restricting it to two roles would break staff accounts.
    }
};
