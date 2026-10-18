<?php

namespace App\Http\Controllers;

use App\Models\Funding;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reçu PDF d'un don encaissé (réservé au donateur, FundingPolicy::downloadReceipt) avec un QR code
 * pointant vers une URL signée publique de vérification d'authenticité.
 */
class ReceiptController extends Controller
{
    public function download(Funding $funding): Response
    {
        Gate::authorize('downloadReceipt', $funding);

        $funding->load('project');
        $verifyUrl = self::verificationUrl($funding);
        $qr = (new Writer(new ImageRenderer(new RendererStyle(160, 1), new SvgImageBackEnd)))->writeString($verifyUrl);

        return Pdf::loadView('pages.fundings.receipt-pdf', [
            'funding' => $funding,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($qr),
        ])->download("recu-{$funding->receipt_number}.pdf");
    }

    /** GET /recus/{funding}/verifier?signature=… : accessible sans compte, signature obligatoire (middleware signed). */
    public function verify(Funding $funding): View
    {
        abort_unless($funding->hasReceipt(), 404);

        return view('pages.fundings.receipt-verify', ['funding' => $funding->load('project')]);
    }

    public static function verificationUrl(Funding $funding): string
    {
        return URL::signedRoute('receipts.verify', ['funding' => $funding]);
    }
}
