<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase rolls back the DB between tests, but the array cache driver
        // is NOT reset the same way, and sqlite's autoincrement counter also resets
        // with the rollback — so an id (e.g. organization #1) reused by the next test
        // can pick up a stale cache entry keyed by that same id from an earlier test.
        // DemoGuard's Cache::remember('demo-org:'.$id, ...) is exactly this shape: one
        // test's demo org #1 poisoned a later, unrelated test's real org #1 into being
        // treated as a demo. Starting every test with a clean cache avoids the whole class of bug.
        Cache::flush();
    }
}
