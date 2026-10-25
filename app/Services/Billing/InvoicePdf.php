<?php

namespace App\Services\Billing;

use App\Support\MerchantBranding;
use Illuminate\Support\Facades\DB;

/**
 * Dependency-free A4 invoice PDF (Bookipi-style). Pure PHP: Helvetica core fonts + optional JPEG/PNG logo.
 * Needs only ext-zlib (for PNG logos) — no GD, no composer package, safe on shared hosting.
 */
final class InvoicePdf
{
    private const W = 595.28;
    private const H = 841.89;
    private const M = 48;

    /** Helvetica / Helvetica-Bold advance widths for ASCII 32..126 (1/1000 em). */
    private const REG = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const BLD = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    private string $page = '';
    /** @var array<int,string> */
    private array $objects = [];
    /** @var array<string,array{obj:int,w:int,h:int}> */
    private array $images = [];

    /** @param array<string,mixed> $d InvoiceView::build() output */
    public static function render(array $d): string
    {
        return (new self)->build($d);
    }

    /** @param array<string,mixed> $d */
    private function build(array $d): string
    {
        $accent = [0.31, 0.275, 0.898]; // #4f46e5
        $ink = [0.06, 0.09, 0.16];
        $muted = [0.39, 0.45, 0.55];
        $line = [0.89, 0.91, 0.94];
        $M = self::M;
        $right = self::W - $M;

        // ---- header: logo + business details (left), INVOICE (right)
        $logoName = $this->logo($d);
        $textX = $M;
        if ($logoName) {
            $this->drawImage($logoName, $M, 48, 52, 52);
            $textX = $M + 64;
        } else {
            $this->rect($M, 48, 52, 52, [0.93, 0.95, 1.0]);
            $this->text($M + 26, 80, $d['from']['initials'], 18, true, $accent, 'center');
            $textX = $M + 64;
        }
        $this->text($textX, 62, $this->clip($d['from']['name'], 280, 14, true), 14, true, $ink);
        $y = 78;
        foreach (array_slice($d['from']['address'], 0, 3) as $ln) {
            $this->text($textX, $y, $this->clip($ln, 260, 9, false), 9, false, $muted);
            $y += 12;
        }
        if ($d['from']['tax_id'] !== '') {
            $this->text($textX, $y, 'Tax ID: '.$d['from']['tax_id'], 9, false, $muted);
        }
        $this->text($right, 70, 'INVOICE', 28, true, $accent, 'right');
        $this->text($right, 88, '#'.$d['number'], 10, false, $muted, 'right');

        // ---- billed to + meta
        $top = 150;
        $this->text($M, $top, 'BILLED TO', 8, true, $muted);
        $this->text($M, $top + 16, $this->clip($d['to']['name'], 250, 12, true), 12, true, $ink);
        if (! empty($d['to']['email'])) {
            $this->text($M, $top + 31, $this->clip($d['to']['email'], 250, 10, false), 10, false, $muted);
        }
        $mx = $right - 170;
        foreach ([['Invoice date', $d['issued']], ['Due date', $d['due']], ['Payment', $d['payment_method']]] as $i => [$k, $v]) {
            $this->text($mx, $top + 2 + $i * 16, $k, 9, false, $muted);
            $this->text($right, $top + 2 + $i * 16, (string) $v, 9, true, $ink, 'right');
        }
        if ($d['paid']) {
            $this->rect($right - 62, $top + 54, 62, 20, [0.82, 0.98, 0.9]);
            $this->text($right - 31, $top + 68, 'PAID', 11, true, [0.02, 0.47, 0.34], 'center');
        } else {
            $this->text($right, $top + 68, $d['status'], 11, true, [0.7, 0.3, 0.1], 'right');
        }

        // ---- items table
        $ty = 255;
        $this->rect($M, $ty, $right - $M, 26, $accent);
        $cx = [$M + 12, $right - 215, $right - 135, $right - 12];
        $this->text($cx[0], $ty + 17, 'DESCRIPTION', 8, true, [1, 1, 1]);
        $this->text($cx[1], $ty + 17, 'QTY', 8, true, [1, 1, 1], 'right');
        $this->text($cx[2], $ty + 17, 'PRICE', 8, true, [1, 1, 1], 'right');
        $this->text($cx[3], $ty + 17, 'AMOUNT', 8, true, [1, 1, 1], 'right');
        $y = $ty + 26;
        foreach ($d['items'] as $it) {
            $lines = $this->wrap((string) $it['description'], $cx[1] - $cx[0] - 40, 10);
            $h = max(34, 16 + count($lines) * 13);
            foreach ($lines as $i => $ln) {
                $this->text($cx[0], $y + 21 + $i * 13, $ln, 10, $i === 0, $ink);
            }
            $this->text($cx[1], $y + 21, (string) $it['qty'], 10, false, $ink, 'right');
            $this->text($cx[2], $y + 21, (string) $it['unit'], 10, false, $ink, 'right');
            $this->text($cx[3], $y + 21, (string) $it['amount'], 10, true, $ink, 'right');
            $y += $h;
            $this->hline($M, $right, $y, $line);
        }

        // ---- totals
        $y += 22;
        $lx = $right - 210;
        $rows = [['Subtotal', $d['subtotal'], false]];
        if ($d['has_tax']) {
            $rows[] = ['Tax', $d['tax'], false];
        }
        foreach ($rows as [$k, $v]) {
            $this->text($lx, $y, $k, 10, false, $muted);
            $this->text($right, $y, $v, 10, false, $ink, 'right');
            $y += 18;
        }
        $this->rect($lx - 10, $y - 12, 220, 30, [0.93, 0.95, 1.0]);
        $this->text($lx, $y + 7, 'Total', 11, true, $ink);
        $this->text($right - 8, $y + 7, $d['total'], 13, true, $accent, 'right');
        $y += 40;
        $this->text($lx, $y, 'Amount paid', 10, false, $muted);
        $this->text($right, $y, $d['amount_paid'], 10, false, $ink, 'right');
        $y += 18;
        $this->text($lx, $y, 'Balance due', 10, true, $ink);
        $this->text($right, $y, $d['balance'], 10, true, $ink, 'right');

        // ---- payment info + notes
        $y += 46;
        $this->hline($M, $right, $y - 18, $line);
        $this->text($M, $y, 'PAYMENT INFORMATION', 8, true, $muted);
        $this->text($M, $y + 16, 'Paid with '.$d['payment_method'].($d['payment_ref'] !== '' ? ' · Ref '.$d['payment_ref'] : ''), 10, false, $ink);
        $this->text($M, $y + 36, 'NOTES', 8, true, $muted);
        $this->text($M, $y + 52, 'Thank you for your business. This invoice was paid in full.', 10, false, $ink);

        // ---- footer
        $this->hline($M, $right, 790, $line);
        $this->text($M, 808, 'Invoice generated by LuvuPay on behalf of '.$d['from']['name'], 8, false, $muted);
        $this->text($right, 808, '#'.$d['number'], 8, false, $muted, 'right');

        return $this->assemble();
    }

    // ------------------------------------------------------------------ drawing primitives (y measured from top)
    private function fy(float $y): float
    {
        return round(self::H - $y, 2);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->page .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $this->fy($y + $h), $w, $h);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function hline(float $x1, float $x2, float $y, array $rgb): void
    {
        $this->page .= sprintf("%.3f %.3f %.3f RG 0.6 w %.2f %.2f m %.2f %.2f l S\n", $rgb[0], $rgb[1], $rgb[2], $x1, $this->fy($y), $x2, $this->fy($y));
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function text(float $x, float $y, string $s, float $size, bool $bold, array $rgb, string $align = 'left'): void
    {
        $s = $this->enc($s);
        $w = $this->width($s, $size, $bold);
        if ($align === 'right') {
            $x -= $w;
        } elseif ($align === 'center') {
            $x -= $w / 2;
        }
        $this->page .= sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, $this->fy($y), $this->esc($s));
    }

    private function enc(string $s): string
    {
        $s = str_replace(["\u{2014}", "\u{2013}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2022}", "\u{00B7}"], ['-', '-', "'", "'", '"', '"', '*', '·'], $s);
        $c = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);

        return $c === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $c;
    }

    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    private function width(string $s, float $size, bool $bold): float
    {
        $t = $bold ? self::BLD : self::REG;
        $w = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $o = ord($s[$i]);
            $w += ($o >= 32 && $o <= 126) ? $t[$o - 32] : 556;
        }

        return $w * $size / 1000;
    }

    private function clip(string $s, float $max, float $size, bool $bold): string
    {
        $e = $this->enc($s);
        if ($this->width($e, $size, $bold) <= $max) {
            return $s;
        }
        while ($s !== '' && $this->width($this->enc($s.'...'), $size, $bold) > $max) {
            $s = mb_substr($s, 0, -1);
        }

        return $s.'...';
    }

    /** @return list<string> */
    private function wrap(string $s, float $max, float $size): array
    {
        $out = [];
        $cur = '';
        foreach (preg_split('/\s+/', trim($s)) ?: [] as $word) {
            $try = $cur === '' ? $word : $cur.' '.$word;
            if ($cur !== '' && $this->width($this->enc($try), $size, false) > $max) {
                $out[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        $out[] = $cur;

        return array_slice($out, 0, 4);
    }

    // ------------------------------------------------------------------ images
    /** @param array<string,mixed> $d */
    private function logo(array $d): ?string
    {
        $m = DB::table('merchants')->where('id', $d['merchant_id'] ?? '')->first(['id', 'logo_path']);
        $l = $m ? MerchantBranding::logo($m) : null;
        if (! $l) {
            return null;
        }
        try {
            return match ($l['mime']) {
                'image/jpeg' => $this->addJpeg($l['bytes']),
                'image/png' => $this->addPng($l['bytes']),
                default => null, // webp: falls back to the initials badge
            };
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function addJpeg(string $b): ?string
    {
        $i = @getimagesizefromstring($b);
        if (! $i || ($i['mime'] ?? '') !== 'image/jpeg') {
            return null;
        }
        $cs = match ((int) ($i['channels'] ?? 3)) {
            1 => '/DeviceGray', 4 => '/DeviceCMYK', default => '/DeviceRGB',
        };
        $extra = $cs === '/DeviceCMYK' ? ' /Decode [1 0 1 0 1 0 1 0]' : '';
        $obj = $this->addObject(sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode%s /Length %d >>', $i[0], $i[1], $cs, $extra, strlen($b))."\nstream\n".$b."\nendstream");
        $this->images['Im1'] = ['obj' => $obj, 'w' => $i[0], 'h' => $i[1]];

        return 'Im1';
    }

    private function addPng(string $b): ?string
    {
        if (! function_exists('gzuncompress') || substr($b, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $pos = 8;
        $idat = '';
        $plte = '';
        $trns = '';
        $hdr = null;
        while ($pos < strlen($b)) {
            $len = unpack('N', substr($b, $pos, 4))[1];
            $type = substr($b, $pos + 4, 4);
            $data = substr($b, $pos + 8, $len);
            $pos += 12 + $len;
            if ($type === 'IHDR') {
                $hdr = unpack('Nw/Nh/Cbd/Cct/Ccm/Ccf/Cil', $data);
            } elseif ($type === 'PLTE') {
                $plte = $data;
            } elseif ($type === 'tRNS') {
                $trns = $data;
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }
        if (! $hdr || $hdr['il'] !== 0 || $hdr['bd'] !== 8 || $hdr['w'] > 2000 || $hdr['h'] > 2000) {
            return null; // interlaced / 16-bit / huge: use the initials badge instead
        }
        $w = $hdr['w'];
        $h = $hdr['h'];
        $ct = $hdr['ct'];
        if ($ct === 2 || $ct === 0 || $ct === 3) {
            $cs = $ct === 0 ? '/DeviceGray' : ($ct === 2 ? '/DeviceRGB' : '/Indexed /DeviceRGB '.((int) (strlen($plte) / 3) - 1).' <'.bin2hex($plte).'>');
            $cols = $ct === 2 ? 3 : 1;
            $obj = $this->addObject(sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace [%s] /BitsPerComponent 8 /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors %d /Columns %d /BitsPerComponent 8 >> /Length %d >>', $w, $h, $cs, $cols, $w, strlen($idat))."\nstream\n".$idat."\nendstream");
            $this->images['Im1'] = ['obj' => $obj, 'w' => $w, 'h' => $h];

            return 'Im1';
        }
        if ($ct !== 6 && $ct !== 4) {
            return null;
        }
        // RGBA / gray+alpha: un-filter, split colour and alpha, re-compress (alpha becomes an /SMask)
        $raw = @gzuncompress($idat);
        if ($raw === false) {
            return null;
        }
        $bpp = $ct === 6 ? 4 : 2;
        $stride = $w * $bpp;
        $prev = str_repeat("\0", $stride);
        $color = '';
        $alpha = '';
        $p = 0;
        for ($row = 0; $row < $h; $row++) {
            $f = ord($raw[$p++]);
            $cur = substr($raw, $p, $stride);
            $p += $stride;
            $out = '';
            for ($i = 0; $i < $stride; $i++) {
                $x = ord($cur[$i]);
                $a = $i >= $bpp ? ord($out[$i - $bpp]) : 0;
                $u = ord($prev[$i]);
                $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
                $v = match ($f) {
                    0 => $x,
                    1 => $x + $a,
                    2 => $x + $u,
                    3 => $x + intdiv($a + $u, 2),
                    4 => $x + $this->paeth($a, $u, $c),
                    default => $x,
                } & 0xFF;
                $out .= chr($v);
            }
            $prev = $out;
            for ($i = 0; $i < $w; $i++) {
                if ($ct === 6) {
                    $color .= substr($out, $i * 4, 3);
                    $alpha .= $out[$i * 4 + 3];
                } else {
                    $color .= $out[$i * 2];
                    $alpha .= $out[$i * 2 + 1];
                }
            }
        }
        $cz = gzcompress($color);
        $az = gzcompress($alpha);
        $mask = $this->addObject(sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>', $w, $h, strlen($az))."\nstream\n".$az."\nendstream");
        $obj = $this->addObject(sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /FlateDecode /SMask %d 0 R /Length %d >>', $w, $h, $ct === 6 ? '/DeviceRGB' : '/DeviceGray', $mask, strlen($cz))."\nstream\n".$cz."\nendstream");
        $this->images['Im1'] = ['obj' => $obj, 'w' => $w, 'h' => $h];

        return 'Im1';
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
    }

    private function drawImage(string $name, float $x, float $y, float $boxW, float $boxH): void
    {
        $im = $this->images[$name];
        $s = min($boxW / $im['w'], $boxH / $im['h']);
        $w = $im['w'] * $s;
        $h = $im['h'] * $s;
        $this->page .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $w, $h, $x + ($boxW - $w) / 2, $this->fy($y + $boxH - ($boxH - $h) / 2), $name);
    }

    // ------------------------------------------------------------------ PDF assembly
    private function addObject(string $body): int
    {
        $this->objects[] = $body;

        return count($this->objects) + 6; // objects 1..6 are reserved for catalog/pages/page/fonts/content
    }

    private function assemble(): string
    {
        $res = '/Font << /F1 4 0 R /F2 5 0 R >>';
        if ($this->images) {
            $xo = '';
            foreach ($this->images as $n => $im) {
                $xo .= '/'.$n.' '.$im['obj'].' 0 R ';
            }
            $res .= ' /XObject << '.$xo.'>>';
        }
        $stream = $this->page;
        $objs = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << %s >> /Contents 6 0 R >>', self::W, self::H, $res),
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            6 => '<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream",
        ];
        // extra objects (images) were numbered starting at 7 by addObject()
        foreach ($this->objects as $i => $body) {
            $objs[7 + $i] = $body;
        }
        ksort($objs);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $off = [];
        foreach ($objs as $n => $body) {
            $off[$n] = strlen($pdf);
            $pdf .= $n." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objs) + 1)."\n0000000000 65535 f \n";
        foreach ($off as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer
<< /Size '.(count($objs) + 1).' /Root 1 0 R >>
startxref
'.$xref."\n%%EOF";

        return $pdf;
    }
}
