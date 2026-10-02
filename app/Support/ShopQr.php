<?php

namespace App\Support;

use App\Models\Business;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR code for a store's public online shop.
 *
 * The link inside the code carries ?src=qr so the shop can count how many
 * visits came from a scan. Rendered as SVG (sharp at any print size); the
 * browser turns it into a PNG when the owner wants one.
 */
class ShopQr
{
    /** The address a shopper lands on, or null when the store has no web address yet. */
    public static function shopUrl(Business $business, bool $forQr = false): ?string
    {
        if (! $business->store_slug) {
            return null;
        }

        $url = route('shop.index', $business->store_slug);

        return $forQr ? $url . '?src=qr' : $url;
    }

    public static function svg(string $url, int $size = 320): string
    {
        // Margin of 1 module: the printed poster and the on-screen card supply their own white border.
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));

        return trim(preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($url)));
    }
}
