<?php

declare(strict_types=1);

// Esempio Laravel (dopo `php artisan vendor:publish --tag=zipsms-config`):
//
// .env:
//   ZIPSMS_API_KEY=sk_live_...
//   ZIPSMS_BASE_URL=https://api.zipsms.it
//
// Controller:
//
// use ZipSms\Laravel\Facades\ZipSms;
//
// class OrderController extends Controller
// {
//     public function notify(string $phone, string $deviceId)
//     {
//         // Idempotenza legata all'ordine: retry sicuri, niente doppi SMS.
//         $res = ZipSms::sms()->send(
//             deviceId: $deviceId,
//             to: $phone,
//             message: 'Il tuo ordine è pronto!',
//             reference: 'ordine-' . request()->route('id'),
//             idempotencyKey: 'ordine-' . request()->route('id'),
//         );
//
//         return response()->json($res, 202);
//     }
// }
