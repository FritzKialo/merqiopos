<?php

/**
 * Merqio POS — Subscription Plan Definitions
 *
 * Single source of truth for plan pricing, limits, and feature flags.
 * Use Business::hasFeature('feature_key') anywhere in the app.
 *
 * Collapsed from 5 org tiers (Free/Solo/Growth/Scale/Enterprise) and 4
 * store tiers (Free/Starter/Business/Enterprise) down to exactly 3 of
 * each — no free tier, no fallback. Solo/Growth/Enterprise, mapped 1:1
 * to Starter/Business/Enterprise store-level plans. Pricing: 999 / 2999
 * / 5999. Only Solo (the entry tier) carries a trial — see
 * RegisterController and CheckSubscription.
 */

return [

    'starter' => [
        'name'     => 'Starter',
        'price'    => 999,
        'color'    => '#6b7280',
        'limits'   => [
            'products' => 200,
            'users'    => 3,
        ],
        'features' => [
            'dashboard'           => true,
            'inventory'           => true,
            'sales'               => true,
            // Customers and M-Pesa are core to running any shop in Kenya —
            // with no free tier left to soften the entry point, the cheapest
            // paid plan shouldn't lock out the two things a real shop needs
            // on day one.
            'customers'           => true,
            'expenses'            => false,   // Business+
            'mpesa_sales'         => true,
            'pdf_invoices'        => false,   // Business+
            'reports_basic'       => true,
            'reports_advanced'    => false,   // Business+
            'team_management'     => false,   // Business+
            'unlimited_products'  => false,   // Business+
            'data_export'         => false,   // Business+
            'unlimited_users'     => false,   // Enterprise
            'quotes'              => false,   // Business+
            'suppliers'           => false,   // Business+
            'recurring_invoices'  => false,   // Business+
            'sms_notifications'   => false,   // Business+
            'stock_adjustments'   => true,    // All plans
            'barcode'             => false,   // Business+
            'api_access'          => false,   // Enterprise
        ],
    ],

    'business' => [
        'name'     => 'Business',
        'price'    => 2999,
        'color'    => '#4f46e5',
        'limits'   => [
            'products' => PHP_INT_MAX,
            'users'    => 15,
        ],
        'features' => [
            'dashboard'           => true,
            'inventory'           => true,
            'sales'               => true,
            'customers'           => true,
            'expenses'            => true,
            'mpesa_sales'         => true,
            'pdf_invoices'        => true,
            'reports_basic'       => true,
            'reports_advanced'    => true,
            'team_management'     => true,
            'unlimited_products'  => true,
            'data_export'         => true,
            'unlimited_users'     => false,   // Enterprise
            'quotes'              => true,
            'suppliers'           => true,
            'recurring_invoices'  => true,
            'sms_notifications'   => true,
            'stock_adjustments'   => true,
            'barcode'             => true,
            'api_access'          => false,   // Enterprise
        ],
    ],

    'enterprise' => [
        'name'     => 'Enterprise',
        'price'    => 5999,
        'color'    => '#8b5cf6',
        'limits'   => [
            'products' => PHP_INT_MAX,
            'users'    => PHP_INT_MAX,
        ],
        'features' => [
            'dashboard'           => true,
            'inventory'           => true,
            'sales'               => true,
            'customers'           => true,
            'expenses'            => true,
            'mpesa_sales'         => true,
            'pdf_invoices'        => true,
            'reports_basic'       => true,
            'reports_advanced'    => true,
            'team_management'     => true,
            'unlimited_products'  => true,
            'data_export'         => true,
            'unlimited_users'     => true,
            'quotes'              => true,
            'suppliers'           => true,
            'recurring_invoices'  => true,
            'sms_notifications'   => true,
            'stock_adjustments'   => true,
            'barcode'             => true,
            'api_access'          => true,
        ],
    ],

    // ── Organization-level plans (tiered by store count) ─────────────────────
    // These govern what an organization (owner) can do across all their stores.
    // Store-level features (inventory, sales, etc.) are still governed by the
    // per-store plan inherited from the org plan.

    'org' => [

        'solo' => [
            'name'   => 'Solo',
            'price'  => 999,
            'color'  => '#6b7280',
            'limits' => [
                'stores' => 1,
                'users'  => 3,      // across all stores
            ],
            'features' => [
                'payroll'              => true,
                'payroll_statutory'    => false,  // Growth+
                'p9_forms'             => false,  // Growth+
                'cross_store_reports'  => false,  // Growth+
                'api_access'           => false,  // Enterprise
            ],
            // Which store-level plan features are available on this org plan
            'store_plan' => 'starter',
        ],

        'growth' => [
            'name'   => 'Growth',
            'price'  => 2999,
            'color'  => '#4f46e5',
            'limits' => [
                'stores' => 3,
                'users'  => 15,
            ],
            'features' => [
                'payroll'              => true,
                'payroll_statutory'    => true,
                'p9_forms'             => true,
                'cross_store_reports'  => true,
                'api_access'           => false,  // Enterprise
            ],
            'store_plan' => 'business',
        ],

        'enterprise' => [
            'name'   => 'Enterprise',
            'price'  => 5999,
            'color'  => '#8b5cf6',
            'limits' => [
                'stores' => -1,  // unlimited
                'users'  => PHP_INT_MAX,
            ],
            'features' => [
                'payroll'              => true,
                'payroll_statutory'    => true,
                'p9_forms'             => true,
                'cross_store_reports'  => true,
                'api_access'           => true,
            ],
            'store_plan' => 'enterprise',
        ],

    ],

];
