@extends('layouts.site')
@section('title', 'LuvuPay · Ethical subscription infrastructure')
@section('content')

@php
    $features = [
        ['48-hour Second Chance', 'Every recurring charge is held for a short window. One click splits the money between the customer, a charity and the merchant, using the point basis you set', '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>'],
        ['Resale of unused access', 'Customers can sell the rest of a billing cycle to someone else. Access moves through the Entitlement Bridge and money moves through the ledger, with no fake refund', '<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>'],
        ['Adaptive Nudge', 'A reminder 72 hours before renewal plus a trust score, so Second Chance is not abused again and again', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/>'],
    ];
    $developerPoints = [
        ['Idempotency-Key on every request', 'Retry safely. A different body with the same key is rejected, never silently executed'],
        ['A webhook inbox that never loses events', 'Verified, stored, then processed. Failures retry with backoff, land in a dead letter queue and replay with one command'],
        ['Scoped API keys and per-merchant rate limits', 'Give each key only the access it needs'],
    ];
    $ecosystem = [
        ['name' => 'PayPal', 'kind' => 'Sandbox', 'svg' => '<path fill="#003087" d="M15.607 4.653H8.941L6.645 19.251H1.82L4.862 0h7.995c3.754 0 6.375 2.294 6.473 5.513-.648-.478-2.105-.86-3.722-.86m6.57 5.546c0 3.41-3.01 6.853-6.958 6.853h-2.493L11.595 24H6.74l1.845-11.538h3.592c4.208 0 7.346-3.634 7.153-6.949a5.24 5.24 0 0 1 2.848 4.686M9.653 5.546h6.408c.907 0 1.942.222 2.363.541-.195 2.741-2.655 5.483-6.441 5.483H8.714Z"/>'],
        ['name' => 'Laravel', 'kind' => 'Package', 'svg' => '<path fill="#FF2D20" d="M23.642 5.43a.364.364 0 01.014.1v5.149c0 .135-.073.26-.189.326l-4.323 2.49v4.934a.378.378 0 01-.188.326L9.93 23.949a.316.316 0 01-.066.027c-.008.002-.016.008-.024.01a.348.348 0 01-.192 0c-.011-.002-.02-.008-.03-.012-.02-.008-.042-.014-.062-.025L.533 18.755a.376.376 0 01-.189-.326V2.974c0-.033.005-.066.014-.098.003-.012.01-.02.014-.032a.369.369 0 01.023-.058c.004-.013.015-.022.023-.033l.033-.045c.012-.01.025-.018.037-.027.014-.012.027-.024.041-.034H.53L5.043.05a.375.375 0 01.375 0L9.93 2.647h.002c.015.01.027.021.04.033l.038.027c.013.014.02.03.033.045.008.011.02.021.025.033.01.02.017.038.024.058.003.011.01.021.013.032.01.031.014.064.014.098v9.652l3.76-2.164V5.527c0-.033.004-.066.013-.098.003-.01.01-.02.013-.032a.487.487 0 01.024-.059c.007-.012.018-.02.025-.033.012-.015.021-.03.033-.043.012-.012.025-.02.037-.028.014-.01.026-.023.041-.032h.001l4.513-2.598a.375.375 0 01.375 0l4.513 2.598c.016.01.027.021.042.031.012.01.025.018.036.028.013.014.022.03.034.044.008.012.019.021.024.033.011.02.018.04.024.06.006.01.012.021.015.032zm-.74 5.032V6.179l-1.578.908-2.182 1.256v4.283zm-4.51 7.75v-4.287l-2.147 1.225-6.126 3.498v4.325zM1.093 3.624v14.588l8.273 4.761v-4.325l-4.322-2.445-.002-.003H5.04c-.014-.01-.025-.021-.04-.031-.011-.01-.024-.018-.035-.027l-.001-.002c-.013-.012-.021-.025-.031-.04-.01-.011-.021-.022-.028-.036h-.002c-.008-.014-.013-.031-.02-.047-.006-.016-.014-.027-.018-.043a.49.49 0 01-.008-.057c-.002-.014-.006-.027-.006-.041V5.789l-2.18-1.257zM5.23.81L1.47 2.974l3.76 2.164 3.758-2.164zm1.956 13.505l2.182-1.256V3.624l-1.58.91-2.182 1.255v9.435zm11.581-10.95l-3.76 2.163 3.76 2.163 3.759-2.164zm-.376 4.978L16.21 7.087 14.63 6.18v4.283l2.182 1.256 1.58.908zm-8.65 9.654l5.514-3.148 2.756-1.572-3.757-2.163-4.323 2.489-3.941 2.27z"/>'],
        ['name' => 'PostgreSQL', 'kind' => 'Driver', 'svg' => '<path fill="#4169E1" d="M23.5594 14.7228a.5269.5269 0 0 0-.0563-.1191c-.139-.2632-.4768-.3418-1.0074-.2321-1.6533.3411-2.2935.1312-2.5256-.0191 1.342-2.0482 2.445-4.522 3.0411-6.8297.2714-1.0507.7982-3.5237.1222-4.7316a1.5641 1.5641 0 0 0-.1509-.235C21.6931.9086 19.8007.0248 17.5099.0005c-1.4947-.0158-2.7705.3461-3.1161.4794a9.449 9.449 0 0 0-.5159-.0816 8.044 8.044 0 0 0-1.3114-.1278c-1.1822-.0184-2.2038.2642-3.0498.8406-.8573-.3211-4.7888-1.645-7.2219.0788C.9359 2.1526.3086 3.8733.4302 6.3043c.0409.818.5069 3.334 1.2423 5.7436.4598 1.5065.9387 2.7019 1.4334 3.582.553.9942 1.1259 1.5933 1.7143 1.7895.4474.1491 1.1327.1441 1.8581-.7279.8012-.9635 1.5903-1.8258 1.9446-2.2069.4351.2355.9064.3625 1.39.3772a.0569.0569 0 0 0 .0004.0041 11.0312 11.0312 0 0 0-.2472.3054c-.3389.4302-.4094.5197-1.5002.7443-.3102.064-1.1344.2339-1.1464.8115-.0025.1224.0329.2309.0919.3268.2269.4231.9216.6097 1.015.6331 1.3345.3335 2.5044.092 3.3714-.6787-.017 2.231.0775 4.4174.3454 5.0874.2212.5529.7618 1.9045 2.4692 1.9043.2505 0 .5263-.0291.8296-.0941 1.7819-.3821 2.5557-1.1696 2.855-2.9059.1503-.8707.4016-2.8753.5388-4.1012.0169-.0703.0357-.1207.057-.1362.0007-.0005.0697-.0471.4272.0307a.3673.3673 0 0 0 .0443.0068l.2539.0223.0149.001c.8468.0384 1.9114-.1426 2.5312-.4308.6438-.2988 1.8057-1.0323 1.5951-1.6698zM2.371 11.8765c-.7435-2.4358-1.1779-4.8851-1.2123-5.5719-.1086-2.1714.4171-3.6829 1.5623-4.4927 1.8367-1.2986 4.8398-.5408 6.108-.13-.0032.0032-.0066.0061-.0098.0094-2.0238 2.044-1.9758 5.536-1.9708 5.7495-.0002.0823.0066.1989.0162.3593.0348.5873.0996 1.6804-.0735 2.9184-.1609 1.1504.1937 2.2764.9728 3.0892.0806.0841.1648.1631.2518.2374-.3468.3714-1.1004 1.1926-1.9025 2.1576-.5677.6825-.9597.5517-1.0886.5087-.3919-.1307-.813-.5871-1.2381-1.3223-.4796-.839-.9635-2.0317-1.4155-3.5126zm6.0072 5.0871c-.1711-.0428-.3271-.1132-.4322-.1772.0889-.0394.2374-.0902.4833-.1409 1.2833-.2641 1.4815-.4506 1.9143-1.0002.0992-.126.2116-.2687.3673-.4426a.3549.3549 0 0 0 .0737-.1298c.1708-.1513.2724-.1099.4369-.0417.156.0646.3078.26.3695.4752.0291.1016.0619.2945-.0452.4444-.9043 1.2658-2.2216 1.2494-3.1676 1.0128zm2.094-3.988-.0525.141c-.133.3566-.2567.6881-.3334 1.003-.6674-.0021-1.3168-.2872-1.8105-.8024-.6279-.6551-.9131-1.5664-.7825-2.5004.1828-1.3079.1153-2.4468.079-3.0586-.005-.0857-.0095-.1607-.0122-.2199.2957-.2621 1.6659-.9962 2.6429-.7724.4459.1022.7176.4057.8305.928.5846 2.7038.0774 3.8307-.3302 4.7363-.084.1866-.1633.3629-.2311.5454zm7.3637 4.5725c-.0169.1768-.0358.376-.0618.5959l-.146.4383a.3547.3547 0 0 0-.0182.1077c-.0059.4747-.054.6489-.115.8693-.0634.2292-.1353.4891-.1794 1.0575-.11 1.4143-.8782 2.2267-2.4172 2.5565-1.5155.3251-1.7843-.4968-2.0212-1.2217a6.5824 6.5824 0 0 0-.0769-.2266c-.2154-.5858-.1911-1.4119-.1574-2.5551.0165-.5612-.0249-1.9013-.3302-2.6462.0044-.2932.0106-.5909.019-.8918a.3529.3529 0 0 0-.0153-.1126 1.4927 1.4927 0 0 0-.0439-.208c-.1226-.4283-.4213-.7866-.7797-.9351-.1424-.059-.4038-.1672-.7178-.0869.067-.276.1831-.5875.309-.9249l.0529-.142c.0595-.16.134-.3257.213-.5012.4265-.9476 1.0106-2.2453.3766-5.1772-.2374-1.0981-1.0304-1.6343-2.2324-1.5098-.7207.0746-1.3799.3654-1.7088.5321a5.6716 5.6716 0 0 0-.1958.1041c.0918-1.1064.4386-3.1741 1.7357-4.4823a4.0306 4.0306 0 0 1 .3033-.276.3532.3532 0 0 0 .1447-.0644c.7524-.5706 1.6945-.8506 2.802-.8325.4091.0067.8017.0339 1.1742.081 1.939.3544 3.2439 1.4468 4.0359 2.3827.8143.9623 1.2552 1.9315 1.4312 2.4543-1.3232-.1346-2.2234.1268-2.6797.779-.9926 1.4189.543 4.1729 1.2811 5.4964.1353.2426.2522.4522.2889.5413.2403.5825.5515.9713.7787 1.2552.0696.087.1372.1714.1885.245-.4008.1155-1.1208.3825-1.0552 1.717-.0123.1563-.0423.4469-.0834.8148-.0461.2077-.0702.4603-.0994.7662zm.8905-1.6211c-.0405-.8316.2691-.9185.5967-1.0105a2.8566 2.8566 0 0 0 .135-.0406 1.202 1.202 0 0 0 .1342.103c.5703.3765 1.5823.4213 3.0068.1344-.2016.1769-.5189.3994-.9533.6011-.4098.1903-1.0957.333-1.7473.3636-.7197.0336-1.0859-.0807-1.1721-.151zm.5695-9.2712c-.0059.3508-.0542.6692-.1054 1.0017-.055.3576-.112.7274-.1264 1.1762-.0142.4368.0404.8909.0932 1.3301.1066.887.216 1.8003-.2075 2.7014a3.5272 3.5272 0 0 1-.1876-.3856c-.0527-.1276-.1669-.3326-.3251-.6162-.6156-1.1041-2.0574-3.6896-1.3193-4.7446.3795-.5427 1.3408-.5661 2.1781-.463zm.2284 7.0137a12.3762 12.3762 0 0 0-.0853-.1074l-.0355-.0444c.7262-1.1995.5842-2.3862.4578-3.4385-.0519-.4318-.1009-.8396-.0885-1.2226.0129-.4061.0666-.7543.1185-1.0911.0639-.415.1288-.8443.1109-1.3505.0134-.0531.0188-.1158.0118-.1902-.0457-.4855-.5999-1.938-1.7294-3.253-.6076-.7073-1.4896-1.4972-2.6889-2.0395.5251-.1066 1.2328-.2035 2.0244-.1859 2.0515.0456 3.6746.8135 4.8242 2.2824a.908.908 0 0 1 .0667.1002c.7231 1.3556-.2762 6.2751-2.9867 10.5405zm-8.8166-6.1162c-.025.1794-.3089.4225-.6211.4225a.5821.5821 0 0 1-.0809-.0056c-.1873-.026-.3765-.144-.5059-.3156-.0458-.0605-.1203-.178-.1055-.2844.0055-.0401.0261-.0985.0925-.1488.1182-.0894.3518-.1226.6096-.0867.3163.0441.6426.1938.6113.4186zm7.9305-.4114c.0111.0792-.049.201-.1531.3102-.0683.0717-.212.1961-.4079.2232a.5456.5456 0 0 1-.075.0052c-.2935 0-.5414-.2344-.5607-.3717-.024-.1765.2641-.3106.5611-.352.297-.0414.6111.0088.6356.1851z"/>'],
        ['name' => 'Redis', 'kind' => 'Queue', 'svg' => '<path fill="#FF4438" d="M22.71 13.145c-1.66 2.092-3.452 4.483-7.038 4.483-3.203 0-4.397-2.825-4.48-5.12.701 1.484 2.073 2.685 4.214 2.63 4.117-.133 6.94-3.852 6.94-7.239 0-4.05-3.022-6.972-8.268-6.972-3.752 0-8.4 1.428-11.455 3.685C2.59 6.937 3.885 9.958 4.35 9.626c2.648-1.904 4.748-3.13 6.784-3.744C8.12 9.244.886 17.05 0 18.425c.1 1.261 1.66 4.648 2.424 4.648.232 0 .431-.133.664-.365a100.49 100.49 0 0 0 5.54-6.765c.222 3.104 1.748 6.898 6.014 6.898 3.819 0 7.604-2.756 9.33-8.965.2-.764-.73-1.361-1.261-.73zm-4.349-5.013c0 1.959-1.926 2.922-3.685 2.922-.941 0-1.664-.247-2.235-.568 1.051-1.592 2.092-3.225 3.21-4.973 1.972.334 2.71 1.43 2.71 2.619z"/>'],
        ['name' => 'FrankenPHP', 'kind' => 'Runtime', 'img' => 'images/frankenphp.png', 'svg' => ''],
        ['name' => 'React', 'kind' => 'Frontend', 'svg' => '<path fill="#61DAFB" d="M14.23 12.004a2.236 2.236 0 0 1-2.235 2.236 2.236 2.236 0 0 1-2.236-2.236 2.236 2.236 0 0 1 2.235-2.236 2.236 2.236 0 0 1 2.236 2.236zm2.648-10.69c-1.346 0-3.107.96-4.888 2.622-1.78-1.653-3.542-2.602-4.887-2.602-.41 0-.783.093-1.106.278-1.375.793-1.683 3.264-.973 6.365C1.98 8.917 0 10.42 0 12.004c0 1.59 1.99 3.097 5.043 4.03-.704 3.113-.39 5.588.988 6.38.32.187.69.275 1.102.275 1.345 0 3.107-.96 4.888-2.624 1.78 1.654 3.542 2.603 4.887 2.603.41 0 .783-.09 1.106-.275 1.374-.792 1.683-3.263.973-6.365C22.02 15.096 24 13.59 24 12.004c0-1.59-1.99-3.097-5.043-4.032.704-3.11.39-5.587-.988-6.38-.318-.184-.688-.277-1.092-.278zm-.005 1.09v.006c.225 0 .406.044.558.127.666.382.955 1.835.73 3.704-.054.46-.142.945-.25 1.44-.96-.236-2.006-.417-3.107-.534-.66-.905-1.345-1.727-2.035-2.447 1.592-1.48 3.087-2.292 4.105-2.295zm-9.77.02c1.012 0 2.514.808 4.11 2.28-.686.72-1.37 1.537-2.02 2.442-1.107.117-2.154.298-3.113.538-.112-.49-.195-.964-.254-1.42-.23-1.868.054-3.32.714-3.707.19-.09.4-.127.563-.132zm4.882 3.05c.455.468.91.992 1.36 1.564-.44-.02-.89-.034-1.345-.034-.46 0-.915.01-1.36.034.44-.572.895-1.096 1.345-1.565zM12 8.1c.74 0 1.477.034 2.202.093.406.582.802 1.203 1.183 1.86.372.64.71 1.29 1.018 1.946-.308.655-.646 1.31-1.013 1.95-.38.66-.773 1.288-1.18 1.87-.728.063-1.466.098-2.21.098-.74 0-1.477-.035-2.202-.093-.406-.582-.802-1.204-1.183-1.86-.372-.64-.71-1.29-1.018-1.946.303-.657.646-1.313 1.013-1.954.38-.66.773-1.286 1.18-1.868.728-.064 1.466-.098 2.21-.098zm-3.635.254c-.24.377-.48.763-.704 1.16-.225.39-.435.782-.635 1.174-.265-.656-.49-1.31-.676-1.947.64-.15 1.315-.283 2.015-.386zm7.26 0c.695.103 1.365.23 2.006.387-.18.632-.405 1.282-.66 1.933-.2-.39-.41-.783-.64-1.174-.225-.392-.465-.774-.705-1.146zm3.063.675c.484.15.944.317 1.375.498 1.732.74 2.852 1.708 2.852 2.476-.005.768-1.125 1.74-2.857 2.475-.42.18-.88.342-1.355.493-.28-.958-.646-1.956-1.1-2.98.45-1.017.81-2.01 1.085-2.964zm-13.395.004c.278.96.645 1.957 1.1 2.98-.45 1.017-.812 2.01-1.086 2.964-.484-.15-.944-.318-1.37-.5-1.732-.737-2.852-1.706-2.852-2.474 0-.768 1.12-1.742 2.852-2.476.42-.18.88-.342 1.356-.494zm11.678 4.28c.265.657.49 1.312.676 1.948-.64.157-1.316.29-2.016.39.24-.375.48-.762.705-1.158.225-.39.435-.788.636-1.18zm-9.945.02c.2.392.41.783.64 1.175.23.39.465.772.705 1.143-.695-.102-1.365-.23-2.006-.386.18-.63.406-1.282.66-1.933zM17.92 16.32c.112.493.2.968.254 1.423.23 1.868-.054 3.32-.714 3.708-.147.09-.338.128-.563.128-1.012 0-2.514-.807-4.11-2.28.686-.72 1.37-1.536 2.02-2.44 1.107-.118 2.154-.3 3.113-.54zm-11.83.01c.96.234 2.006.415 3.107.532.66.905 1.345 1.727 2.035 2.446-1.595 1.483-3.092 2.295-4.11 2.295-.22-.005-.406-.05-.553-.132-.666-.38-.955-1.834-.73-3.703.054-.46.142-.944.25-1.438zm4.56.64c.44.02.89.034 1.345.034.46 0 .915-.01 1.36-.034-.44.572-.895 1.095-1.345 1.565-.455-.47-.91-.993-1.36-1.565z"/>'],
        ['name' => 'Next.js', 'kind' => 'Framework', 'svg' => '<path fill="#000000" d="M18.665 21.978C16.758 23.255 14.465 24 12 24 5.377 24 0 18.623 0 12S5.377 0 12 0s12 5.377 12 12c0 3.583-1.574 6.801-4.067 9.001L9.219 7.2H7.2v9.596h1.615V9.251l9.85 12.727Zm-3.332-8.533 1.6 2.061V7.2h-1.6v6.245Z"/>'],
        ['name' => 'Angular', 'kind' => 'Framework', 'svg' => '<path fill="#DD0031" d="M16.712 17.711H7.288l-1.204 2.916L12 24l5.916-3.373-1.204-2.916ZM14.692 0l7.832 16.855.814-12.856L14.692 0ZM9.308 0 .662 3.999l.814 12.856L9.308 0Zm-.405 13.93h6.198L12 6.396 8.903 13.93Z"/>'],
    ];

    $security = [
        ['Double-entry ledger', 'Append-only. Every journal balances, cannot be edited and is reconciled hourly'],
        ['HMAC audit chain', 'Each change is signed into a chain, so tampering is caught by audit:verify'],
        ['MFA, roles and teams', 'TOTP with recovery codes. Owner, Admin, Finance, Developer and Viewer'],
        ['PayPal reconciliation', 'Payouts left hanging are checked against PayPal and settled automatically'],
    ];
    // Rosette guilloche: pola garis halus ala uang kertas dan kartu premium.
    // Dipakai di hero dan CTA supaya dua section terasa satu keluarga.
    $rosette = function (string $id, string $class = '') {
        $lines = '';
        for ($i = 0; $i < 48; $i++) {
            $lines .= '<ellipse rx="300" ry="80" transform="rotate(' . ($i * 3.75) . ')" stroke-opacity=".17"/>';
        }
        for ($i = 0; $i < 36; $i++) {
            $lines .= '<ellipse rx="175" ry="48" transform="rotate(' . (2.5 + $i * 5) . ')" stroke-opacity=".14"/>';
        }
        return '<svg class="' . $class . '" viewBox="-330 -330 660 660" fill="none" aria-hidden="true">'
            . '<defs>'
            . '<radialGradient id="rs-halo-' . $id . '"><stop offset="0" stop-color="#e4e2ff" stop-opacity=".75"/><stop offset=".7" stop-color="#e4e2ff" stop-opacity=".25"/><stop offset="1" stop-color="#e4e2ff" stop-opacity="0"/></radialGradient>'
            . '<radialGradient id="rs-fade-' . $id . '"><stop offset=".45" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>'
            . '<mask id="rs-mask-' . $id . '" maskUnits="userSpaceOnUse" x="-330" y="-330" width="660" height="660"><circle r="330" fill="url(#rs-fade-' . $id . ')"/></mask>'
            . '</defs>'
            . '<g mask="url(#rs-mask-' . $id . ')">'
            . '<circle r="300" fill="url(#rs-halo-' . $id . ')"/>'
            . '<g stroke="#09027b" stroke-width=".7">' . $lines . '<circle r="300" stroke-opacity=".18"/><circle r="118" stroke-opacity=".16"/></g>'
            . '</g></svg>';
    };
@endphp

{{-- Hero --}}
<section class="lp-hero" style="position:relative;overflow:hidden;background:radial-gradient(70% 80% at 88% 8%,#ecebff 0%,rgba(236,235,255,0) 62%),linear-gradient(180deg,#ffffff 0%,#f7f8ff 100%)">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-14 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:pb-28 lg:pt-20">
        <div>
            <h1 class="text-[2.7rem] font-bold leading-[1.04] tracking-[-0.035em] text-ink sm:text-[3.5rem]">
                Build payment experiences that give a <span class="text-brand-600">second chance</span>
            </h1>
            <p class="mt-6 max-w-lg text-[1.05rem] leading-relaxed text-muted">One API for the 48-hour Second Chance, resale of unused access and money movement you can audit to the cent. Designed for developers and trusted by finance teams</p>
            <div class="mt-9 flex flex-wrap items-center gap-3">
                <a href="{{ route('register') }}" class="lp-btn lp-btn-dark lp-btn-lg">Start building <span aria-hidden="true">→</span></a>
                <a href="/docs" class="lp-btn lp-btn-outline lp-btn-lg">Read the docs</a>
            </div>
        </div>

        {{-- Product preview --}}
        <div class="lp-stage relative" aria-hidden="true">
            {{-- latar: rosette guilloche halus di belakang jendela + dua chip produk --}}
            <style>
                .hx-rose{position:absolute;left:50%;top:50%;width:40rem;height:40rem;transform:translate(-50%,-50%);z-index:0;pointer-events:none}
                .hx-rose svg{width:100%;height:100%;display:block}
                .hx-win{transform:none!important;box-shadow:0 50px 90px -40px rgba(9,2,123,.45),0 0 0 1px rgba(9,2,123,.07)!important}
                .hx-chip{position:absolute;z-index:2;display:flex;align-items:center;gap:.6rem;padding:.55rem .95rem .55rem .6rem;border-radius:.95rem;background:#fff;border:1px solid #e8eaf3;box-shadow:0 18px 36px -16px rgba(15,23,42,.3);font-size:11px;line-height:1.25;white-space:nowrap}
                .hx-chip b{display:block;font-weight:600;color:#0f172a}
                .hx-chip small{display:block;color:#64748b;font-size:10px}
                .hx-ico{flex:none;width:28px;height:28px;border-radius:99px;display:grid;place-items:center}
                .hx-chip-a{top:-2.2rem;right:1.5rem}
                .hx-chip-b{bottom:-2.2rem;left:.5rem}
                @media (min-width:1024px){
                    .hx-rose{left:56%;width:50rem;height:50rem}
                    .hx-chip-a{top:-2.4rem;right:3rem}
                    .hx-chip-b{bottom:-2.4rem;left:-3rem}
                }
            </style>
            <div class="hx-rose">{!! $rosette('h') !!}</div>

            <div class="hx-chip hx-chip-a">
                <span class="hx-ico" style="background:#ecfdf5"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                <span><b>Second Chance used</b><small>Martin H. · $12.00</small></span>
            </div>
            <div class="hx-chip hx-chip-b">
                <span class="hx-ico" style="background:#fff1f2"><svg width="14" height="14" viewBox="0 0 24 24" fill="#f43f5e"><path d="M12 21l-8.2-8.2a5 5 0 0 1 7.1-7.1L12 6.8l1.1-1.1a5 5 0 0 1 7.1 7.1z"/></svg></span>
                <span><b>Charity received +$1.20</b><small>Split settled to ledger</small></span>
            </div>

            <div class="lp-window hx-win relative" style="z-index:1">
                <aside class="lp-window-side">
                    <div class="flex items-center gap-2 font-semibold">@include('partials.logo', ['size' => 20]) LuvuPay</div>
                    <ul>
                        <li class="is-active">Dashboard</li><li>Transactions</li><li>Customers</li><li>Products</li><li>Webhooks</li><li>Settings</li>
                    </ul>
                </aside>
                <div class="lp-window-main">
                    <div class="flex items-center justify-between">
                        <strong class="text-[0.95rem]">Overview</strong>
                        <span class="lp-tabs"><i class="is-on">7 days</i><i>30 days</i><i>3 months</i></span>
                    </div>
                    <div class="mt-4 rounded-xl border border-line p-4">
                        <div class="text-[11px] text-muted">Payment volume</div>
                        <div class="mt-1 flex items-baseline gap-2"><span class="text-2xl font-bold tracking-tight">$124,530</span><span class="text-[11px] font-semibold text-emerald-600">▲ 12.5% vs last period</span></div>
                        <svg viewBox="0 0 400 90" class="mt-3 w-full" preserveAspectRatio="none">
                            <defs><linearGradient id="lpg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#2a24a3" stop-opacity=".28"/><stop offset="1" stop-color="#2a24a3" stop-opacity="0"/></linearGradient></defs>
                            <path d="M0 70 C40 62 60 72 100 56 S170 50 210 42 S290 38 330 22 S380 14 400 8 L400 90 L0 90Z" fill="url(#lpg)"/>
                            <path d="M0 70 C40 62 60 72 100 56 S170 50 210 42 S290 38 330 22 S380 14 400 8" fill="none" stroke="#09027b" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-3">
                        @foreach([['Successful', '4,832', '+12%'], ['Second Chance', '612', '+28%'], ['Resold access', '321', '+10%']] as [$k, $v, $d])
                            <div class="rounded-xl border border-line p-3"><div class="text-[10px] text-muted">{{ $k }}</div><div class="text-lg font-bold">{{ $v }}</div><div class="text-[10px] font-semibold text-emerald-600">{{ $d }}</div></div>
                        @endforeach
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-line text-[11px]">
                        @foreach([['tr_3f8a20c', 'Martin H.', 'Second Chance', 'Settled', 'ok'], ['tr_0b0e9ab', 'Salsabila A.', 'Resale', 'Settled', 'ok'], ['tr_1d1ac09', 'Dimas R.', 'Held', 'Pending', 'warn']] as [$id, $who, $kind, $status, $tone])
                            <div class="grid grid-cols-[1.2fr_1fr_1fr_auto] gap-2 border-b border-line px-3 py-2 last:border-0"><span class="font-mono text-muted">{{ $id }}</span><span>{{ $who }}</span><span class="text-muted">{{ $kind }}</span><span class="lp-badge {{ $tone === 'ok' ? 'lp-badge-ok' : 'lp-badge-warn' }}">{{ $status }}</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Product --}}
<section id="product" class="border-t border-line bg-wash py-24">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.6fr)] lg:items-center">
        <div>
            <p class="lp-eyebrow">Built for growth</p>
            <h2 class="lp-h2">Three subscription problems, one infrastructure layer</h2>
            <p class="mt-4 leading-relaxed text-muted">Customers forget to cancel, paid access goes to waste and merchants are blind to behavior. LuvuPay solves all three without you building a financial engine</p>
            <a href="/docs" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">Learn how it works <span aria-hidden="true">→</span></a>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach($features as [$title, $text, $icon])
                <article class="lp-card lp-card-pad">
                    <span class="lp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg></span>
                    <h3 class="mt-5 font-semibold tracking-tight">{{ $title }}</h3>
                    <p class="mt-2 text-[0.82rem] leading-relaxed text-muted">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Developers --}}
<section id="developers" class="py-24">
    <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-2">
        <div class="lp-terminal" x-data="{ copied: false }">
            <div class="lp-terminal-bar">
                <span>curl</span>
                <button type="button" @click="navigator.clipboard.writeText($refs.code.innerText); copied = true; setTimeout(() => copied = false, 1600)" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
<pre x-ref="code"><span class="c"># Create a subscription, safe to retry</span>
<span class="k">curl</span> -X POST https://api.luvupay.test/api/v1/subscriptions \
  -H <span class="s">"Authorization: Bearer sk_live_xxx"</span> \
  -H <span class="s">"Idempotency-Key: 9f1c7a52-order-1042"</span> \
  -H <span class="s">"Content-Type: application/json"</span> \
  -d <span class="s">'{"plan_id":"P-PRO","email":"ana@acme.com"}'</span>

<span class="c"># 201 Created</span>
{
  <span class="n">"id"</span>: <span class="s">"sub_01J8..."</span>,
  <span class="n">"status"</span>: <span class="s">"approval_pending"</span>,
  <span class="n">"approval_url"</span>: <span class="s">"https://www.sandbox.paypal.com/..."</span>
}</pre>
            <div class="lp-terminal-foot"><span class="size-2 rounded-full bg-emerald-400"></span> Response 200 OK</div>
        </div>
        <div>
            <p class="lp-eyebrow">Developer first</p>
            <h2 class="lp-h2">An API that is safe to retry, webhooks that never get lost</h2>
            <p class="mt-4 leading-relaxed text-muted">Idempotency keys on every money request, a durable webhook inbox and scoped API keys for each merchant</p>
            <ul class="mt-8 space-y-5">
                @foreach($developerPoints as [$title, $text])
                    <li class="flex gap-3">
                        <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700">@include('partials.icon', ['name' => 'check', 'size' => 14])</span>
                        <div><div class="font-semibold">{{ $title }}</div><p class="mt-0.5 text-sm leading-relaxed text-muted">{{ $text }}</p></div>
                    </li>
                @endforeach
            </ul>
            <a href="/docs/api" class="mt-8 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">View API reference <span aria-hidden="true">→</span></a>
        </div>
    </div>
</section>

{{-- Ecosystem --}}
<section class="eco" style="position:relative;overflow:hidden;border-top:1px solid #e8eaf3;padding:4.5rem 0;background:linear-gradient(180deg,#fbfcff 0%,#f4f2ff 100%)">
    <style>
        .eco *{box-sizing:border-box}
        .eco-bg{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}
        .eco-inner{position:relative;max-width:72rem;margin:0 auto;padding:0 1.25rem}
        .eco-top{display:grid;gap:2rem;align-items:center}
        .eco-title{margin:.6rem 0 0;max-width:26rem;font-size:1.9rem;line-height:1.18;font-weight:700;letter-spacing:-.02em;color:#0f172a}
        .eco-desc{margin:1rem 0 0;max-width:26rem;font-size:.95rem;line-height:1.65;color:#64748b}
        .eco-float{display:none;position:relative;height:230px;width:100%;max-width:500px;margin-left:auto}
        .eco-float svg.eco-lines{position:absolute;inset:0;width:100%;height:100%}
        .eco-node{position:absolute;display:flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:16px;background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:0 10px 26px rgba(91,61,245,.13);animation:ecoFloat 6s ease-in-out infinite}
        .eco-node.sm{width:46px;height:46px;border-radius:14px;color:#8b8fb0;font-weight:700;letter-spacing:.12em;font-size:14px}
        .eco-node svg{width:26px;height:26px;display:block}
        @keyframes ecoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
        .eco-tiles{display:grid;gap:10px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:2.25rem}
        .eco-tile,.eco-more{display:block;border-radius:14px;padding:14px;text-decoration:none}
        .eco-tile{background:#fff;border:1px solid rgba(15,23,42,.07);box-shadow:0 1px 2px rgba(15,23,42,.04);transition:border-color .2s,box-shadow .2s}
        .eco-tile:hover{border-color:#c4b5fd;box-shadow:0 8px 22px rgba(91,61,245,.12)}
        .eco-tile svg{width:24px;height:24px;display:block}
        .eco-tile b{display:block;margin-top:12px;font-size:.85rem;font-weight:600;color:#0f172a}
        .eco-tile span{display:block;font-size:.72rem;color:#64748b}
        .eco-more{display:flex;flex-direction:column;justify-content:center;gap:6px;border:1.5px dashed #c4b5fd;background:rgba(91,61,245,.04);color:#09027b;font-size:.82rem;font-weight:600}
        .eco-more svg{width:18px;height:18px}
        @media (min-width:640px){.eco-tiles{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media (min-width:1024px){
            .eco-inner{padding:0 2rem}
            .eco-top{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
            .eco-float{display:block}
            .eco-tiles{grid-template-columns:repeat(6,minmax(0,1fr))}
        }
        @media (prefers-reduced-motion:reduce){.eco-node{animation:none}}
    </style>

    <svg class="eco-bg" aria-hidden="true" preserveAspectRatio="xMidYMid slice" viewBox="0 0 1440 600" fill="none">
        <defs>
            <pattern id="eco-grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" stroke="#09027b" stroke-opacity=".07"/></pattern>
            <radialGradient id="eco-fade" cx="75%" cy="35%" r="55%"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
            <mask id="eco-mask"><rect width="1440" height="600" fill="url(#eco-fade)"/></mask>
            <linearGradient id="eco-wave" x1="0" x2="1"><stop offset="0" stop-color="#c4b5fd" stop-opacity="0"/><stop offset=".5" stop-color="#a78bfa" stop-opacity=".5"/><stop offset="1" stop-color="#c4b5fd" stop-opacity="0"/></linearGradient>
        </defs>
        <rect width="1440" height="600" fill="url(#eco-grid)" mask="url(#eco-mask)"/>
        <path d="M640 420 C 820 360, 900 120, 1100 190 S 1340 330, 1460 120" stroke="url(#eco-wave)" stroke-width="1.4"/>
        <rect x="1180" y="60" width="70" height="70" rx="14" stroke="#c4b5fd" stroke-opacity=".5" transform="rotate(14 1215 95)"/>
        <rect x="1300" y="420" width="50" height="50" rx="12" fill="#a78bfa" fill-opacity=".10" stroke="#c4b5fd" stroke-opacity=".5" transform="rotate(-10 1325 445)"/>
    </svg>

    <div class="eco-inner">
        <div class="eco-top">
            <div>
                <p class="lp-eyebrow">Ecosystem</p>
                <h2 class="eco-title">Integrated with the tools you already know and trust</h2>
                <p class="eco-desc">Use LuvuPay with the tools and platforms you already run. Our integrations keep growing to support your business stack.</p>
            </div>

            <div class="eco-float" aria-hidden="true">
                <svg class="eco-lines" viewBox="0 0 100 100" preserveAspectRatio="none" fill="none">
                    <path d="M11 44 C 22 44, 26 66, 37 66 S 58 72, 69 72" stroke="#a78bfa" stroke-opacity=".6" stroke-width="1.2" stroke-dasharray="3 5" vector-effect="non-scaling-stroke"/>
                    <path d="M11 44 C 30 44, 44 14, 59 14 S 82 41, 91 41" stroke="#a78bfa" stroke-opacity=".6" stroke-width="1.2" stroke-dasharray="3 5" vector-effect="non-scaling-stroke"/>
                    <path d="M37 66 C 47 42, 52 30, 59 14" stroke="#c4b5fd" stroke-opacity=".5" stroke-width="1" vector-effect="non-scaling-stroke"/>
                </svg>
                <div class="eco-node" style="left:6%;top:32%;animation-delay:-1s"><svg viewBox="0 0 24 24">{!! $ecosystem[0]['svg'] !!}</svg></div>
                <div class="eco-node" style="left:54%;top:2%;animation-delay:-3s"><svg viewBox="0 0 24 24">{!! $ecosystem[1]['svg'] !!}</svg></div>
                <div class="eco-node" style="left:32%;top:54%;animation-delay:-2s"><svg viewBox="0 0 24 24">{!! $ecosystem[3]['svg'] !!}</svg></div>
                <div class="eco-node" style="left:64%;top:60%;animation-delay:-4s"><svg viewBox="0 0 24 24">{!! $ecosystem[2]['svg'] !!}</svg></div>
                <div class="eco-node sm" style="left:86%;top:30%;animation-delay:-5s">···</div>
            </div>
        </div>

        <div class="eco-tiles">
            @foreach(array_slice($ecosystem, 0, 5) as $item)
                <div class="eco-tile">
                    @if(!empty($item['img']) && file_exists(public_path($item['img'])))
                        <img src="{{ asset($item['img']) }}" alt="" style="width:24px;height:24px;object-fit:contain;display:block">
                    @elseif(!empty($item['img']))
                        <span style="display:grid;place-items:center;width:24px;height:24px;border-radius:6px;background:#eef0f6;color:#64748b;font:700 12px/1 sans-serif">{{ substr($item['name'], 0, 1) }}</span>
                    @else
                        <svg viewBox="0 0 24 24" aria-hidden="true">{!! $item['svg'] !!}</svg>
                    @endif
                    <b>{{ $item['name'] }}</b>
                    <span>{{ $item['kind'] }}</span>
                </div>
            @endforeach
            <a href="/docs" class="eco-more">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                See all integrations →
            </a>
        </div>
    </div>
</section>

{{-- Infrastructure --}}
<section class="infra" style="position:relative;overflow:hidden;padding:4.5rem 0;background:#090b15">
    <style>
        .infra *{box-sizing:border-box}
        .infra-bg{position:absolute;inset:0;pointer-events:none}
        .infra-inner{position:relative;max-width:72rem;margin:0 auto;padding:0 1.25rem;display:grid;gap:2.5rem;align-items:center}
        .infra-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:10px;font-weight:600;letter-spacing:.14em;color:#c4b5fd;background:rgba(139,124,248,.14);border:1px solid rgba(139,124,248,.25)}
        .infra-title{margin:1rem 0 0;max-width:24rem;font-size:1.9rem;line-height:1.18;font-weight:700;letter-spacing:-.02em;color:#fff}
        .infra-desc{margin:.9rem 0 0;max-width:26rem;font-size:.92rem;line-height:1.65;color:rgba(255,255,255,.62)}
        .infra-list{list-style:none;margin:1.75rem 0 0;padding:0;display:flex;flex-direction:column;gap:1.1rem}
        .infra-list li{display:flex;align-items:center;gap:.9rem}
        .infra-ico{flex:none;width:40px;height:40px;border-radius:999px;display:flex;align-items:center;justify-content:center;color:#c4b5fd;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);box-shadow:0 0 0 4px rgba(139,124,248,.06)}
        .infra-ico svg{width:18px;height:18px}
        .infra-list b{display:block;font-size:.88rem;font-weight:600;color:#fff}
        .infra-list span{display:block;font-size:.78rem;color:rgba(255,255,255,.55)}
        .infra-mapcol{width:100%;max-width:540px;margin-left:auto}
        .infra-map{position:relative;width:100%;aspect-ratio:2/1}
        .infra-map>img,.infra-map>svg{position:absolute;inset:0;width:100%;height:100%}
        .infra-card{background:rgba(16,19,34,.9);border:1px solid rgba(255,255,255,.10);border-radius:12px;box-shadow:0 10px 28px rgba(0,0,0,.45)}
        .infra-chip{position:absolute;display:none;align-items:center;gap:6px;padding:4px 9px;border-radius:999px;white-space:nowrap;font-size:10px;font-weight:600;color:#fff;transform:translate(-50%,-190%)}
        .infra-chip i{width:6px;height:6px;border-radius:99px;background:#a78bfa}
        .infra-chip small{font-size:9px;font-weight:500;color:rgba(255,255,255,.5)}
        .infra-sg{position:absolute;left:78.8%;top:58.4%;transform:translate(-108%,24%);display:flex;align-items:center;gap:9px;padding:8px 12px}
        .infra-sg b{display:block;font-size:11px;font-weight:600;color:#fff}
        .infra-sg span{display:block;font-size:10px;color:rgba(255,255,255,.55)}
        .dot-ok{flex:none;width:8px;height:8px;border-radius:99px;background:#22c55e;box-shadow:0 0 9px #22c55e}
        .infra-status{margin-top:1rem;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px}
        .infra-status div{display:flex;align-items:center;gap:10px;min-width:0}
        .infra-status b{display:block;font-size:11.5px;font-weight:600;color:#fff}
        .infra-status small{display:block;font-size:10.5px;color:rgba(255,255,255,.55)}
        .infra-status a{flex:none;font-size:10.5px;font-weight:600;color:#c4b5fd;text-decoration:none}
        .infra-status a:hover{color:#fff}
        .infra-pin-ring{transform-box:fill-box;transform-origin:center;animation:infraPulse 2.8s ease-out infinite}
        @keyframes infraPulse{from{transform:scale(.5);opacity:.7}to{transform:scale(2.4);opacity:0}}
        .infra-arc{stroke-dasharray:5 7;animation:infraFlow 3s linear infinite}
        @keyframes infraFlow{to{stroke-dashoffset:-96}}
        @media (min-width:640px){.infra-chip{display:flex}}
        @media (min-width:1024px){
            .infra-inner{padding:0 2rem;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr)}
            .infra-title{font-size:2.2rem}
        }
        @media (prefers-reduced-motion:reduce){.infra-pin-ring,.infra-arc{animation:none}}
    </style>

    <div class="infra-bg" aria-hidden="true">
        <div style="position:absolute;inset:0;background-image:linear-gradient(rgba(139,124,248,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(139,124,248,.07) 1px,transparent 1px);background-size:40px 40px;-webkit-mask-image:radial-gradient(ellipse 70% 80% at 72% 45%,#000 20%,transparent 75%);mask-image:radial-gradient(ellipse 70% 80% at 72% 45%,#000 20%,transparent 75%)"></div>
        <div style="position:absolute;right:-6%;top:-25%;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle,rgba(91,61,245,.26),transparent 68%)"></div>
        <div style="position:absolute;left:-8%;bottom:-35%;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,rgba(91,61,245,.13),transparent 70%)"></div>
    </div>

    <div class="infra-inner">
        <div>
            <span class="infra-badge">INFRASTRUCTURE</span>
            <h2 class="infra-title">Built for subscription apps at scale</h2>
            <p class="infra-desc">LuvuPay runs on stable cloud infrastructure, a microservice architecture and layered security, so you can focus on shipping product.</p>

            <ul class="infra-list">
                @foreach([
                    ['High availability', '99.9% uptime, always online.', '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>'],
                    ['Auto scaling', 'Scales with demand automatically.', '<path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/>'],
                    ['Global CDN', 'Fast access worldwide: US, Europe, APAC.', '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3M3.6 9h16.8M3.6 15h16.8"/>'],
                ] as [$t, $d, $icon])
                    <li>
                        <span class="infra-ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">{!! $icon !!}</svg></span>
                        <div><b>{{ $t }}</b><span>{{ $d }}</span></div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="infra-mapcol">
            <div class="infra-map" aria-hidden="true">
                <img src="{{ asset('images/world-dots.svg') }}" alt="" style="opacity:.55" loading="lazy">
                <svg viewBox="0 0 1000 500" fill="none">
                    <defs>
                        <linearGradient id="infra-arc" x1="0" x2="1000" y1="0" y2="0" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#6d5cf6"/><stop offset=".6" stop-color="#a78bfa"/><stop offset="1" stop-color="#c4b5fd"/>
                        </linearGradient>
                    </defs>
                    <path class="infra-arc" d="M228 157 Q 376 30 525 118" stroke="url(#infra-arc)" stroke-width="2.5" opacity=".9"/>
                    <path class="infra-arc" d="M525 118 Q 720 120 788 292" stroke="url(#infra-arc)" stroke-width="2.5" opacity=".85"/>
                    <path class="infra-arc" d="M228 157 Q 500 400 788 292" stroke="url(#infra-arc)" stroke-width="2" opacity=".5"/>
                    <path class="infra-arc" d="M788 292 Q 880 250 888 168" stroke="url(#infra-arc)" stroke-width="2.2" opacity=".75"/>
                    @foreach([[228,157,'#a78bfa'],[525,118,'#a78bfa'],[788,292,'#22c55e'],[888,168,'#a78bfa']] as [$x, $y, $c])
                        <circle class="infra-pin-ring" cx="{{ $x }}" cy="{{ $y }}" r="9" fill="{{ $c }}" fill-opacity=".35"/>
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="5" fill="{{ $c }}"/>
                    @endforeach
                </svg>

                <div class="infra-card infra-chip" style="left:22.8%;top:31.4%"><i></i>United States <small>28ms</small></div>
                <div class="infra-card infra-chip" style="left:52.5%;top:23.6%"><i></i>Europe <small>24ms</small></div>
                <div class="infra-card infra-chip" style="left:88.8%;top:33.6%"><i></i>Japan <small>35ms</small></div>

                <div class="infra-card infra-sg">
                    <span class="dot-ok"></span>
                    <div><b>Singapore</b><span>Latency 32ms</span></div>
                </div>
            </div>

            <div class="infra-card infra-status">
                <div>
                    <span class="dot-ok"></span>
                    <div><b>Infrastructure status</b><small>All systems operational.</small></div>
                </div>
                <a href="/status">View status →</a>
            </div>
        </div>
    </div>
</section>

{{-- Security --}}
<section id="security" class="lp-dark py-24">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.6fr)] lg:items-center">
        <div>
            <p class="lp-eyebrow text-brand-300">Security and trust</p>
            <h2 class="lp-h2 text-white">Built to be audited, not just to run</h2>
            <p class="mt-4 leading-relaxed text-white/65">Controls that usually arrive after an incident are here from day one, even in the sandbox</p>
            <a href="/docs/security" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-white">Read about our security <span aria-hidden="true">→</span></a>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($security as [$title, $text])
                <article class="rounded-2xl border border-white/10 bg-white/[0.04] p-5">
                    <h3 class="text-sm font-semibold text-white">{{ $title }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-white/60">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Call to action --}}
<section class="py-20">
    <div class="mx-auto grid max-w-7xl items-center gap-10 overflow-hidden px-5 sm:px-8 lg:grid-cols-2">
        <div>
            <p class="lp-eyebrow">Ready to start</p>
            <h2 class="lp-h2">Start in the sandbox, go live when you are ready</h2>
            <p class="mt-4 max-w-md text-muted">No real money moves until you decide it should, so your team can test every payment flow and webhook in the sandbox and switch to live in one click when you are ready</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="lp-btn lp-btn-dark lp-btn-lg">Create merchant account <span aria-hidden="true">→</span></a>
                <a href="/docs/sandbox" class="lp-btn lp-btn-outline lp-btn-lg">Sandbox guide</a>
            </div>
        </div>
        <div class="lp-cta-visual cs" aria-hidden="true">
            <style>
                .cs{position:relative;display:flex;flex-direction:column;align-items:center;width:100%;max-width:34rem;margin:0 auto;overflow:hidden;border-radius:2rem;background:#eef0ff}
                @media (min-width:1024px){.cs{max-width:none}}
                /* latar panel kaca bertumpuk, dibuat murni dengan CSS (tanpa gambar) */
                .cs-bg{position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:0;background:radial-gradient(70% 55% at 50% 45%,#f4f3ff 0%,rgba(244,243,255,0) 70%)}
                .cs-bg i{position:absolute;display:block;border-radius:1.25rem;border:1px solid rgba(255,255,255,.55);box-shadow:0 26px 42px -28px rgba(98,70,234,.5),inset 0 1px 0 rgba(255,255,255,.6)}
                .cs-l1{left:4%;width:14%;top:7%;bottom:5%;background:linear-gradient(180deg,#cbc1fc 0%,#a89bf2 100%)}
                .cs-l2{left:12%;width:15%;top:11%;bottom:9%;background:linear-gradient(180deg,#d6cffd 0%,#b3b9fa 100%)}
                .cs-l3{left:20.5%;width:14%;top:18%;bottom:16%;background:linear-gradient(180deg,#e5e1fe 0%,#cfd2fb 100%)}
                .cs-l4{left:29%;width:12%;top:26%;bottom:24%;background:linear-gradient(180deg,rgba(255,255,255,.72),rgba(236,233,255,.55))}
                .cs-r1{right:4%;width:14%;top:7%;bottom:5%;background:linear-gradient(180deg,#cbc1fc 0%,#a89bf2 100%)}
                .cs-r2{right:12%;width:15%;top:11%;bottom:9%;background:linear-gradient(180deg,#d6cffd 0%,#b3b9fa 100%)}
                .cs-r3{right:20.5%;width:14%;top:18%;bottom:16%;background:linear-gradient(180deg,#e5e1fe 0%,#cfd2fb 100%)}
                .cs-r4{right:29%;width:12%;top:26%;bottom:24%;background:linear-gradient(180deg,rgba(255,255,255,.72),rgba(236,233,255,.55))}
                .cs-bg .cs-orb{left:50%;top:50%;width:52%;aspect-ratio:1/1;height:auto;transform:translate(-50%,-50%);border-radius:50%;border:1px solid rgba(255,255,255,.8);background:radial-gradient(circle at 30% 25%,rgba(255,255,255,.95),rgba(255,255,255,.42) 60%,rgba(255,255,255,.25));box-shadow:0 30px 60px -32px rgba(98,70,234,.4)}
                .cs-phone{position:relative;z-index:1;display:block;width:78%;margin:1.25rem auto 0}
                .cs-card{position:relative;z-index:1;align-self:stretch;margin:.25rem 1.1rem 1.1rem;padding:1rem 1.15rem;border-radius:1.15rem;background:rgba(255,255,255,.72);border:1px solid rgba(255,255,255,.8);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px);box-shadow:0 18px 40px -22px rgba(9,2,123,.35)}
                /* semua animasi 12s, tiga langkah x 4s, selalu sinkron */
                .cs-a{opacity:0;animation:csFade 12s ease-in-out infinite}
                .cs-s2{animation-delay:4s}
                .cs-s3{animation-delay:8s}
                .cs-slides{position:relative;height:4.4rem}
                .cs-slides p{position:absolute;inset:0;margin:0;font-size:1.2rem;line-height:1.28;font-weight:600;letter-spacing:-.02em;color:#0f172a;opacity:0;animation:csSlide 12s ease-in-out infinite}
                .cs-slides p:nth-child(2){animation-delay:4s}
                .cs-slides p:nth-child(3){animation-delay:8s}
                .cs-bars{display:flex;gap:6px;margin-top:.75rem}
                .cs-bars span{flex:1;height:3px;border-radius:99px;background:rgba(9,2,123,.14);overflow:hidden}
                .cs-bars i{display:block;height:100%;width:0;border-radius:99px;background:#09027b;animation:csSeg 12s linear infinite}
                .cs-bars span:nth-child(2) i{animation-delay:4s}
                .cs-bars span:nth-child(3) i{animation-delay:8s}
                @keyframes csFade{0%{opacity:0}6%,30%{opacity:1}36%,100%{opacity:0}}
                @keyframes csSlide{0%{opacity:0;transform:translateY(10px)}6%,30%{opacity:1;transform:none}36%,100%{opacity:0;transform:translateY(-10px)}}
                @keyframes csSeg{0%{width:0}33.3%{width:100%}33.4%,100%{width:0}}
                @media (prefers-reduced-motion:reduce){
                    .cs-a,.cs-slides p,.cs-bars i{animation:none}
                    .cs-s1,.cs-slides p:first-child{opacity:1}
                    .cs-bars span:first-child i{width:100%}
                }
            </style>

            {{-- latar: panel kaca bertumpuk kiri dan kanan, tanpa gambar dan tanpa logo --}}
            <div class="cs-bg">
                <i class="cs-l1"></i><i class="cs-l2"></i><i class="cs-l3"></i><i class="cs-l4"></i>
                <i class="cs-r1"></i><i class="cs-r2"></i><i class="cs-r3"></i><i class="cs-r4"></i>
                <i class="cs-orb"></i>
            </div>

            <svg viewBox="0 20 520 470" class="cs-phone" fill="none" font-family="inherit">
                <defs>
                    <linearGradient id="cta-fr" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3b3e5e"/><stop offset=".5" stop-color="#14172b"/><stop offset="1" stop-color="#0b0f1e"/></linearGradient>
                    <linearGradient id="cta-cd" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2b23c6"/><stop offset=".55" stop-color="#09027b"/><stop offset="1" stop-color="#050245"/></linearGradient>
                    <clipPath id="cta-cc"><rect x="172" y="100" width="176" height="150" rx="18"/></clipPath>
                    <filter id="cta-sh" x="-30%" y="-30%" width="160%" height="170%"><feDropShadow dx="0" dy="16" stdDeviation="18" flood-color="#09027b" flood-opacity=".28"/></filter>
                    <filter id="cta-sh2" x="-30%" y="-40%" width="160%" height="190%"><feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="#0f172a" flood-opacity=".18"/></filter>
                    <filter id="cta-sh3" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="8" stdDeviation="7" flood-color="#09027b" flood-opacity=".32"/></filter>
                    <g id="cta-card">
                        <rect x="172" y="100" width="176" height="150" rx="18" fill="url(#cta-cd)"/>
                        <g clip-path="url(#cta-cc)" stroke="#fff" stroke-opacity=".10" fill="none">
                            <circle cx="338" cy="110" r="40"/><circle cx="338" cy="110" r="70"/><circle cx="338" cy="110" r="100"/><circle cx="338" cy="110" r="130"/>
                        </g>
                        <rect x="172.5" y="100.5" width="175" height="149" rx="17.5" stroke="#fff" stroke-opacity=".16"/>
                    </g>
                </defs>

                {{-- ponsel: bingkai titanium dan elemen yang tetap --}}
                <rect x="147" y="112" width="3" height="26" rx="1.5" fill="#1a1d33"/>
                <rect x="370" y="142" width="3" height="46" rx="1.5" fill="#1a1d33"/>
                <g filter="url(#cta-sh)"><rect x="150" y="30" width="220" height="450" rx="38" fill="url(#cta-fr)"/></g>
                <rect x="150.5" y="30.5" width="219" height="449" rx="37.5" stroke="#fff" stroke-opacity=".22"/>
                <rect x="158" y="38" width="204" height="434" rx="31" fill="#f7f8fc"/>
                <rect x="228" y="46" width="64" height="16" rx="8" fill="#0b0f1e"/>
                <text x="176" y="90" font-size="13" font-weight="700" fill="#0f172a">Subscriptions</text>
                <circle cx="340" cy="85" r="9" fill="#e7e5ff"/>
                <text x="340" y="88.5" font-size="9" font-weight="700" fill="#09027b" text-anchor="middle">A</text>
                <path d="M158 424H362" stroke="#e8eaf3"/>
                <circle cx="196" cy="448" r="4.5" fill="#cbd5e1"/>
                <rect x="249" y="444" width="22" height="8" rx="4" fill="#09027b"/>
                <circle cx="324" cy="448" r="4.5" fill="#cbd5e1"/>

                {{-- LAYAR 1: pengingat sebelum perpanjangan --}}
                <g class="cs-a cs-s1">
                    <g filter="url(#cta-sh3)"><use href="#cta-card"/></g>
                    <circle cx="192" cy="124" r="10" fill="#fff" fill-opacity=".16"/>
                    <text x="192" y="127.5" font-size="10" font-weight="700" fill="#fff" text-anchor="middle">S</text>
                    <text x="208" y="123" font-size="10.5" font-weight="600" fill="#fff">Streamly Pro</text>
                    <text x="208" y="136" font-size="9" fill="#fff" fill-opacity=".62">$12.00 / month</text>
                    <text x="190" y="170" font-size="9" fill="#fff" fill-opacity=".62">Renews in 3 days</text>
                    <text x="190" y="197" font-size="25" font-weight="700" fill="#fff" font-family="ui-monospace,SFMono-Regular,Menlo,monospace" letter-spacing="-.5">Nov 2</text>
                    <rect x="190" y="206" width="140" height="3.5" rx="1.75" fill="#fff" fill-opacity=".2"/>
                    <rect x="190" y="206" width="122" height="3.5" rx="1.75" fill="#fff"/>
                    <rect x="190" y="220" width="140" height="24" rx="12" fill="#fff"/>
                    <text x="260" y="236" font-size="10.5" font-weight="700" fill="#09027b" text-anchor="middle">Keep plan</text>
                    <text x="176" y="280" font-size="11" font-weight="600" fill="#0f172a">Upcoming</text>
                    <circle cx="186" cy="303" r="8" fill="#dbeafe"/><text x="186" y="306" font-size="8.5" font-weight="700" fill="#1d4ed8" text-anchor="middle">C</text>
                    <text x="202" y="302" font-size="10" font-weight="600" fill="#0f172a">Cloudbox</text>
                    <text x="202" y="314" font-size="9" fill="#64748b">Nov 9</text>
                    <text x="344" y="306" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">$4.99</text>
                    <path d="M176 322H344" stroke="#e8eaf3"/>
                    <circle cx="186" cy="339" r="8" fill="#fce7f3"/><text x="186" y="342" font-size="8.5" font-weight="700" fill="#be185d" text-anchor="middle">R</text>
                    <text x="202" y="338" font-size="10" font-weight="600" fill="#0f172a">Readly</text>
                    <text x="202" y="350" font-size="9" fill="#64748b">Nov 14</text>
                    <text x="344" y="342" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">$8.00</text>
                    <rect x="176" y="366" width="168" height="40" rx="12" fill="#fffbeb" stroke="#fde68a"/>
                    <circle cx="195" cy="386" r="8" fill="#fef3c7"/>
                    <path d="M195 381.5V386l2.8 1.7" stroke="#b45309" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="209" y="383" font-size="9.5" font-weight="600" fill="#92400e">Reminder sent 72h ahead</text>
                    <text x="209" y="396" font-size="9" fill="#a16207">Trust score · High</text>
                </g>

                {{-- LAYAR 2: Second Chance dan pembagian dana --}}
                <g class="cs-a cs-s2">
                    <g filter="url(#cta-sh3)"><use href="#cta-card"/></g>
                    <circle cx="192" cy="124" r="10" fill="#fff" fill-opacity=".16"/>
                    <text x="192" y="127.5" font-size="10" font-weight="700" fill="#fff" text-anchor="middle">S</text>
                    <text x="208" y="123" font-size="10.5" font-weight="600" fill="#fff">Streamly Pro</text>
                    <text x="208" y="136" font-size="9" fill="#fff" fill-opacity=".62">$12.00 / month</text>
                    <text x="190" y="170" font-size="9" fill="#fff" fill-opacity=".62">Charge held for</text>
                    <text x="190" y="197" font-size="25" font-weight="700" fill="#fff" font-family="ui-monospace,SFMono-Regular,Menlo,monospace" letter-spacing="-.5">47:52:10</text>
                    <rect x="190" y="206" width="140" height="3.5" rx="1.75" fill="#fff" fill-opacity=".2"/>
                    <rect x="190" y="206" width="132" height="3.5" rx="1.75" fill="#fff"/>
                    <rect x="190" y="220" width="140" height="24" rx="12" fill="#fff"/>
                    <text x="260" y="236" font-size="10.5" font-weight="700" fill="#09027b" text-anchor="middle">Second Chance</text>
                    <text x="176" y="280" font-size="11" font-weight="600" fill="#0f172a">Where your money goes</text>
                    <rect x="176" y="290" width="99" height="8" rx="4" fill="#09027b"/>
                    <rect x="278" y="290" width="15" height="8" rx="4" fill="#f59e0b"/>
                    <rect x="296" y="290" width="48" height="8" rx="4" fill="#10b981"/>
                    <circle cx="182" cy="318" r="3.5" fill="#09027b"/><text x="192" y="321" font-size="10" fill="#64748b">Customer</text><text x="344" y="321" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">$7.20</text>
                    <path d="M176 330H344" stroke="#e8eaf3"/>
                    <circle cx="182" cy="343" r="3.5" fill="#f59e0b"/><text x="192" y="346" font-size="10" fill="#64748b">Charity</text><text x="344" y="346" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">$1.20</text>
                    <path d="M176 355H344" stroke="#e8eaf3"/>
                    <circle cx="182" cy="368" r="3.5" fill="#10b981"/><text x="192" y="371" font-size="10" fill="#64748b">Merchant</text><text x="344" y="371" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">$3.60</text>
                    <rect x="176" y="382" width="168" height="26" rx="10" fill="#ecfdf5"/>
                    <circle cx="190" cy="395" r="5.5" fill="#10b981"/><path d="M187.6 395l1.8 1.8 3.2-3.6" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="201" y="398" font-size="9.5" font-weight="600" fill="#047857">Recorded in ledger, balanced</text>
                </g>

                {{-- LAYAR 3: jual sisa akses --}}
                <g class="cs-a cs-s3">
                    <g filter="url(#cta-sh3)"><use href="#cta-card"/></g>
                    <circle cx="192" cy="124" r="10" fill="#fff" fill-opacity=".16"/>
                    <text x="192" y="127.5" font-size="10" font-weight="700" fill="#fff" text-anchor="middle">S</text>
                    <text x="208" y="123" font-size="10.5" font-weight="600" fill="#fff">Streamly Pro</text>
                    <text x="208" y="136" font-size="9" fill="#fff" fill-opacity=".62">18 days left</text>
                    <text x="190" y="170" font-size="9" fill="#fff" fill-opacity=".62">You receive</text>
                    <text x="190" y="197" font-size="25" font-weight="700" fill="#fff" font-family="ui-monospace,SFMono-Regular,Menlo,monospace" letter-spacing="-.5">$5.40</text>
                    <rect x="190" y="206" width="140" height="3.5" rx="1.75" fill="#fff" fill-opacity=".2"/>
                    <rect x="190" y="206" width="84" height="3.5" rx="1.75" fill="#fff"/>
                    <rect x="190" y="220" width="140" height="24" rx="12" fill="#fff"/>
                    <text x="260" y="236" font-size="10.5" font-weight="700" fill="#09027b" text-anchor="middle">Sell remaining days</text>
                    <text x="176" y="280" font-size="11" font-weight="600" fill="#0f172a">Sale status</text>
                    <path d="M186 310V318M186 334V342" stroke="#a7f3d0" stroke-width="2"/>
                    <circle cx="186" cy="303" r="6.5" fill="#10b981"/><path d="M183.2 303l2 2 3.6-4" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="186" cy="326" r="6.5" fill="#10b981"/><path d="M183.2 326l2 2 3.6-4" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="186" cy="349" r="6.5" fill="#10b981"/><path d="M183.2 349l2 2 3.6-4" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="202" y="306" font-size="10" font-weight="600" fill="#0f172a">Listed</text><text x="344" y="306" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">0:01</text>
                    <text x="202" y="329" font-size="10" font-weight="600" fill="#0f172a">Buyer matched</text><text x="344" y="329" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">0:09</text>
                    <text x="202" y="352" font-size="10" font-weight="600" fill="#0f172a">Access moved</text><text x="344" y="352" font-size="10" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">0:10</text>
                    <rect x="176" y="372" width="168" height="34" rx="10" fill="#ecfdf5"/>
                    <text x="190" y="387" font-size="9.5" font-weight="600" fill="#047857">No fake refund</text>
                    <text x="190" y="399" font-size="9" fill="#059669">Ledger entry posted</text>
                </g>

                {{-- modal kiri: muncul bersama layar 1 --}}
                <g class="cs-a cs-s1">
                    <g filter="url(#cta-sh2)"><rect x="10" y="150" width="190" height="56" rx="16" fill="#fff" fill-opacity=".96"/></g>
                    <rect x="22" y="165" width="26" height="26" rx="8" fill="#fef3c7"/>
                    <path d="M35 171.5V178l4 2.5" stroke="#b45309" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="58" y="176" font-size="10.5" font-weight="600" fill="#0f172a">Renews in 3 days</text>
                    <text x="58" y="191" font-size="9" fill="#64748b">Reminder sent, keep or undo</text>
                    <text x="188" y="176" font-size="8.5" fill="#94a3b8" text-anchor="end">now</text>
                </g>

                {{-- modal kanan: muncul bersama layar 2 --}}
                <g class="cs-a cs-s2">
                    <g filter="url(#cta-sh2)"><rect x="330" y="300" width="180" height="52" rx="16" fill="#fff" fill-opacity=".96"/></g>
                    <rect x="342" y="313" width="26" height="26" rx="8" fill="#fff1f2"/>
                    <path d="M355 333L348.5 326.5A3.6 3.6 0 0 1 355 322.2A3.6 3.6 0 0 1 361.5 326.5Z" fill="#f43f5e"/>
                    <text x="378" y="322" font-size="10" font-weight="600" fill="#0f172a">Charity received</text>
                    <text x="378" y="337" font-size="9" fill="#64748b">+$1.20 · Split settled</text>
                </g>

                {{-- modal kanan: muncul bersama layar 3 --}}
                <g class="cs-a cs-s3">
                    <g filter="url(#cta-sh2)"><rect x="330" y="300" width="180" height="52" rx="16" fill="#fff" fill-opacity=".96"/></g>
                    <rect x="342" y="313" width="26" height="26" rx="8" fill="#ecfdf5"/>
                    <path d="M349 326.5l4 4 7-8" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="378" y="322" font-size="10" font-weight="600" fill="#0f172a">Access sold</text>
                    <text x="378" y="337" font-size="9" fill="#64748b">+$5.40 · Ledger posted</text>
                </g>
            </svg>

            {{-- teks auto slide, menyatu dengan gambar --}}
            <div class="cs-card">
                <div class="cs-slides">
                    <p>Get a nudge<br>before every<br>renewal.</p>
                    <p>Changed your mind?<br>Second Chance<br>lasts 48 hours.</p>
                    <p>Sell unused access,<br>no fake refund.</p>
                </div>
                <div class="cs-bars"><span><i></i></span><span><i></i></span><span><i></i></span></div>
            </div>
        </div>
    </div>
</section>
@endsection
