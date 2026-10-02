<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * No sitemap existed anywhere in the app before this — every public shop
 * page and product was only discoverable by a search engine actually
 * following links from elsewhere, with no submitted URL list to speed
 * that up or guarantee full coverage. Rebuilt from all public businesses'
 * live product catalogs each time the cache expires, so a new product
 * appears here within an hour of being added rather than needing a
 * manual resubmission.
 */
class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            return $this->build();
        });

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    private function build(): string
    {
        $urls = [];

        // Marketing site — static pages
        foreach (['home', 'pricing', 'guide', 'contact'] as $routeName) {
            $urls[] = ['loc' => route($routeName), 'changefreq' => 'weekly', 'priority' => $routeName === 'home' ? '1.0' : '0.7'];
        }

        // Every public online shop + its visible, active products
        Business::where('store_public', true)
            ->whereNotNull('store_slug')
            ->select('id', 'store_slug', 'updated_at')
            ->chunk(50, function ($businesses) use (&$urls) {
                foreach ($businesses as $business) {
                    $urls[] = [
                        'loc'        => route('shop.index', $business->store_slug),
                        'lastmod'    => $business->updated_at->toAtomString(),
                        'changefreq' => 'daily',
                        'priority'   => '0.8',
                    ];

                    Product::where('business_id', $business->id)
                        ->where('status', 'active')
                        ->where('hide_in_shop', false)
                        ->select('id', 'business_id', 'updated_at')
                        ->chunk(200, function ($products) use (&$urls, $business) {
                            foreach ($products as $product) {
                                $urls[] = [
                                    'loc'        => route('shop.product', [$business->store_slug, $product->id]),
                                    'lastmod'    => $product->updated_at->toAtomString(),
                                    'changefreq' => 'weekly',
                                    'priority'   => '0.6',
                                ];
                            }
                        });
                }
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . e($u['loc']) . '</loc>' . "\n";
            if (!empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
            }
            $xml .= '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $u['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        $xml .= '</urlset>';

        return $xml;
    }
}
