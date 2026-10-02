<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * A couple of raw-SQL fragments used for month-by-month revenue charts, written once
 * as MySQL-only YEAR()/MONTH() calls and copy-pasted across four dashboards — broke
 * every automated test that touched one of those dashboards, since the test suite
 * runs on sqlite, which doesn't have those functions. Production only ever runs
 * MySQL, so this was never a live bug — but it's a landmine for the next dashboard.
 */
class PortableSql
{
    /** "YEAR($column) as year, MONTH($column) as month" — the sqlite-safe equivalent on other drivers. */
    public static function yearMonth(string $column): string
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return "YEAR({$column}) as year, MONTH({$column}) as month";
        }

        return "CAST(strftime('%Y', {$column}) AS INTEGER) as year, CAST(strftime('%m', {$column}) AS INTEGER) as month";
    }
}
