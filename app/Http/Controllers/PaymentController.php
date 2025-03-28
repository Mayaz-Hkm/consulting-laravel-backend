<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Charge;


class PaymentController extends Controller
{
    public function PaymentIntent(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100, // تحويل المبلغ إلى سنتات
                'currency' => 'usd',
                'payment_method' => $request->token, // استخدام الـ token المرسل من الـ frontend
                'confirm' => true,
            ]);

            return response()->json([
                'status' => 1,
                'payment_intent' => $paymentIntent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Payment creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
