<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        // add table assignment dari file sql
        $sqlPath = __DIR__ . "/../menu.sql";

        if (File::exists($sqlPath)) {
            $sql = File::get($sqlPath);
            DB::unprepared($sql);
        } else {
            throw new \Exception("SQL file not found: $sqlPath");
        }
    }

    public function down()
    {
        
    }
};
