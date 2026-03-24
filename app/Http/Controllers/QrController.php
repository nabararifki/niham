<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use URL;

class QrController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->only('image');
    }

    public function image(Asset $asset)
    {
        $this->authorize('view', $asset);

        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute('qr.resolve', ['uuid' => $asset->uuid]);

        // SVG fallback: simple-qrcode's PNG backend REQUIRES imagick; there is no GD
        // fallback in BaconQrCode. On servers without imagick, use the pure-PHP SVG backend.
        if (! extension_loaded('imagick')) {
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(360)
                ->margin(1)
                ->errorCorrection('H')
                ->generate($signedUrl);

            return response((string) $qrSvg)->header('Content-Type', 'image/svg+xml');
        }

        // Generate raw PNG QR (imagick available path)
        $qrRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(360)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($signedUrl);

        try {
            // Initiate Intervention Engine
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read((string) $qrRaw);

            // Determine Logo Path
            $logoPath = public_path('niham-logo-cr-rd.png');
            if ($asset->property && $asset->property->logo_path) {
                $propLogoPath = storage_path('app/public/' . $asset->property->logo_path);
                if (file_exists($propLogoPath)) {
                    $logoPath = $propLogoPath;
                }
            }

            if (file_exists($logoPath)) {
                $logo = $manager->read($logoPath);
                // Scale logo relative to QR size
                $logo->scaleDown(width: 80);
                // Insert Logo into Center
                $image->place($logo, 'center');
            }

            // Create a canvas with extra height for the text (increased padding)
            $canvasHeight = $image->height() + 70;
            $canvas = $manager->create($image->width(), $canvasHeight)->fill('fff');
            
            // Place QR Code at top with a slight 5px margin
            $canvas->place($image, 'top-center', 0, 5);

            // Determine if Arial font exists to use dynamic sizing
            $fontFile = public_path('arial.ttf');
            $fontSize = 14; // Default fallback size for TTF
            
            if (file_exists($fontFile)) {
                $targetWidth = $canvas->width() - 40; // 20px padding on each side
                $testSize = 10;
                do {
                    $bbox = imagettfbbox($testSize, 0, $fontFile, $asset->tag);
                    $textWidth = $bbox[2] - $bbox[0];
                    // Also account for height to prevent overlapping the QR code
                    $textHeight = abs($bbox[5] - $bbox[1]);
                    if ($textWidth < $targetWidth && $textHeight < 40) {
                        $testSize++;
                    } else {
                        break;
                    }
                } while ($testSize < 100);
                $fontSize = max(10, $testSize - 1); // Step back 1 point, min 10
            }

            // Draw Tag text at the bottom
            $canvas->text($asset->tag, $canvas->width() / 2, $canvasHeight - 15, function (\Intervention\Image\Typography\FontFactory $font) use ($fontFile, $fontSize) {
                if (file_exists($fontFile)) {
                    $font->filename($fontFile);
                    $font->size($fontSize);
                }
                $font->color('000');
                $font->align('center');
                $font->valign('bottom');
            });

            return response($canvas->toPng())->header('Content-Type', 'image/png');
        } catch (\Exception $e) {
            // Log error
            \Illuminate\Support\Facades\Log::error('QR Generation failed: ' . $e->getMessage());
            // Fallback to raw QR if intervention fails to find fonts or process
            return response($qrRaw)->header('Content-Type', 'image/png');
        }
    }

    public function resolve(Request $request, string $uuid)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired QR link');
        }
        $asset = Asset::where('uuid', $uuid)->firstOrFail();

        // Opsi 1: tampilkan halaman minimal yang bisa diakses umum
        return view('qr.asset-public', ['asset' => $asset]);
        // Opsi 2: memerlukan login
        // return redirect()->route('assets.show', $asset);
    }
}
