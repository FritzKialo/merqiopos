<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields required by the KRA OSCU specification v2.0: the device activation
 * result (communication key etc.), sequential invoice numbers, and the KRA item
 * code each product is registered under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'etims_bhf_id')) {
                $table->string('etims_bhf_id', 2)->default('00');
                $table->text('etims_cmc_key')->nullable();          // encrypted communication key
                $table->string('etims_sdc_id', 30)->nullable();     // control unit id, printed on receipts
                $table->string('etims_mrc_no', 30)->nullable();
                $table->timestamp('etims_initialized_at')->nullable();
                $table->unsignedBigInteger('etims_last_invc_no')->default(0);
                $table->unsignedBigInteger('etims_last_item_no')->default(0);
                $table->string('etims_default_item_cls_cd', 10)->nullable();
                $table->string('etims_generic_item_cd', 20)->nullable();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'etims_item_cd')) {
                $table->string('etims_item_cd', 20)->nullable();
                $table->string('etims_item_cls_cd', 10)->nullable();
                $table->timestamp('etims_registered_at')->nullable();
            }
        });

        foreach (['sales', 'invoices'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'etims_invc_no')) {
                    $table->unsignedBigInteger('etims_invc_no')->nullable();
                }
            });
        }

        if (Schema::hasTable('etims_refunds') && !Schema::hasColumn('etims_refunds', 'invc_no')) {
            Schema::table('etims_refunds', function (Blueprint $table) {
                $table->unsignedBigInteger('invc_no')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'etims_bhf_id', 'etims_cmc_key', 'etims_sdc_id', 'etims_mrc_no', 'etims_initialized_at',
                'etims_last_invc_no', 'etims_last_item_no', 'etims_default_item_cls_cd', 'etims_generic_item_cd',
            ]);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['etims_item_cd', 'etims_item_cls_cd', 'etims_registered_at']);
        });
        foreach (['sales', 'invoices'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('etims_invc_no');
            });
        }
        if (Schema::hasColumn('etims_refunds', 'invc_no')) {
            Schema::table('etims_refunds', function (Blueprint $table) {
                $table->dropColumn('invc_no');
            });
        }
    }
};
