<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /**
     * Generate an SVG QR code for the given URL.
     */
    public function svg(string $url, int $size = 300): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Return the dine-in URL for a table QR token.
     */
    public function tableUrl(string $qrToken): string
    {
        return url("/qr/{$qrToken}");
    }

    /**
     * Return the menu URL for a general menu QR.
     */
    public function menuUrl(): string
    {
        return url('/menu');
    }
}
