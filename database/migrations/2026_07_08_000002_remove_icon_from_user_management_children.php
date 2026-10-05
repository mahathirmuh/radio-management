<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original hardcoded sidebar never gave "User Registered" / "Role & Permission"
     * an icon (only the "User Management" group and the Approval Management children
     * had one) — restore that so the data-driven sidebar matches the original theme.
     */
    public function up()
    {
        DB::table('menu')->whereIn('id', [98, 99])->update(['icon' => null]);
    }

    public function down()
    {
        DB::table('menu')->where('id', 98)->update(['icon' => 'users']);
        DB::table('menu')->where('id', 99)->update(['icon' => 'shield-lock']);
    }
};
