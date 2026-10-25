<?php

namespace App\Http\Controllers;

use App\Services\Billing\InvoicePdf;
use App\Services\Billing\InvoiceView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/** Invoice page + PDF: console (auth) and customer links (signed, used in receipt emails). */
final class InvoiceController
{
    private function merchantId(Request $request): string
    {
        $u = Auth::guard('merchant_web')->user() ?? $request->user();
        abort_unless($u && $u->merchant_id, 403);

        return (string) $u->merchant_id;
    }

    public function show(Request $request, string $id): View
    {
        $d = InvoiceView::find($id, $this->merchantId($request));
        abort_unless($d, 404);

        return view('invoices.show', ['d' => $d, 'pdfUrl' => route('dashboard.invoice.pdf', $d['id']), 'backUrl' => url('/dashboard')]);
    }

    public function pdf(Request $request, string $id): Response
    {
        $d = InvoiceView::find($id, $this->merchantId($request));
        abort_unless($d, 404);

        return $this->pdfResponse($d);
    }

    public function preview(Request $request): View
    {
        $d = InvoiceView::sample($this->merchantId($request));

        return view('invoices.show', ['d' => $d, 'pdfUrl' => route('dashboard.invoice.preview.pdf'), 'backUrl' => route('settings').'#invoice-details']);
    }

    public function previewPdf(Request $request): Response
    {
        return $this->pdfResponse(InvoiceView::sample($this->merchantId($request)));
    }

    public function publicShow(string $id): View
    {
        $d = InvoiceView::find($id);
        abort_unless($d, 404);

        return view('invoices.show', ['d' => $d, 'pdfUrl' => self::publicPdfUrl($d['id']), 'backUrl' => null]);
    }

    public function publicPdf(string $id): Response
    {
        $d = InvoiceView::find($id);
        abort_unless($d, 404);

        return $this->pdfResponse($d);
    }

    public static function publicUrl(string $invoiceId): string
    {
        return URL::signedRoute('invoice.public', ['id' => $invoiceId]);
    }

    public static function publicPdfUrl(string $invoiceId): string
    {
        return URL::signedRoute('invoice.public.pdf', ['id' => $invoiceId]);
    }

    /** @param array<string,mixed> $d */
    private function pdfResponse(array $d): Response
    {
        $bin = InvoicePdf::render($d);
        $name = 'Invoice-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $d['number']).'.pdf';

        return response($bin, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
