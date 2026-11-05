<?php

namespace App\Http\Controllers;

use App\Models\SmartConvertRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Public, read-only "AI Negotiation Evidence" for one Smart-Convert run. Built for judges to open and screenshot.
 * Reads the stored run only (never the browser); the unguessable run UUID is the access token; no emails or secrets are included.
 */
final class EvidenceController extends Controller
{
    public function smartConvert(string $run): Response
    {
        $model = SmartConvertRun::findOrFail($run);

        return response()
            ->view('evidence.smart-convert', ['e' => $model->evidence(), 'jsonUrl' => url('/evidence/smart-convert/'.$model->id.'.json')])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }

    public function smartConvertJson(string $run): JsonResponse
    {
        $model = SmartConvertRun::findOrFail($run);

        return response()->json($model->evidence(), 200, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** Demo instances only: jump to the newest executed run so the judge link never changes. */
    public function latest(): RedirectResponse
    {
        abort_unless(config('luvupay.demo_simulator'), 404);
        $run = SmartConvertRun::where('status', 'executed')->orderByDesc('executed_at')->firstOrFail();

        return redirect('/evidence/smart-convert/'.$run->id);
    }
}
