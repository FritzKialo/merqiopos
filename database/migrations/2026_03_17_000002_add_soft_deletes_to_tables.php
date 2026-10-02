<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('products',  fn ($t) => $t->dropSoftDeletes());
        Schema::table('sales',     fn ($t) => $t->dropSoftDeletes());
        Schema::table('customers', fn ($t) => $t->dropSoftDeletes());
        Schema::table('expenses',  fn ($t) => $t->dropSoftDeletes());
    }
};
