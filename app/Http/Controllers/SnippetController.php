<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SnippetController
{
    /** GET /api/v1/snippet (merchant.auth): the ONE line the merchant pastes into their checkout page. */
    public function embed(Request $request): JsonResponse
    {
        /** @var Merchant $m */
        $m = $request->attributes->get('merchant');
        $src = route('badge.js', ['key' => $m->publishable_key]);

        return response()->json([
            'snippet' => '<script async src="'.e($src).'"></script>',
            'optional_target' => 'Add data-luvupay to any element to choose where the badge appears, or append &target=%23my-selector to the URL.',
            'publishable_key' => $m->publishable_key,
        ]);
    }

    /** GET /api/v1/badge.js?key=lp_pub_...&target=&theme= (public, cacheable, same bytes for every visitor). */
    public function badge(Request $request): Response
    {
        $headers = ['Content-Type' => 'application/javascript; charset=utf-8', 'X-Content-Type-Options' => 'nosniff'];

        $merchant = Merchant::query()->where('publishable_key', (string) $request->query('key'))->where('status', 'active')->first();
        if (! $merchant) {
            return response('/* LuvuPay: unknown or inactive key */', 404, $headers + ['Cache-Control' => 'public, max-age=60']);
        }

        $target = (string) $request->query('target', '');
        $cfg = [
            'merchant' => $merchant->name,
            'origins' => array_values(array_filter((array) $merchant->allowed_origins, 'is_string')),
            'window' => $merchant->second_chance_window_hours,
            'user' => $merchant->split_user_bps / 100,
            'charity' => $merchant->split_charity_bps / 100,
            'target' => mb_strlen($target) <= 100 ? $target : '',
            'theme' => $request->query('theme') === 'dark' ? 'dark' : 'light',
        ];

        // HEX flags make the JSON safe inside a script (no </script>, quotes or ampersands can break out).
        $json = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $js = preg_replace('#/\*__LUVUPAY_CONFIG__\*/.*?/\*__END__\*/#s', addcslashes($json, '\\$'), file_get_contents(resource_path('js/luvupay-badge.js')), 1);

        $response = response($js, 200, $headers + ['Cache-Control' => 'public, max-age=300']);
        $response->setEtag(md5($js));

        return $response->isNotModified($request) ? $response : $response;
    }
}
