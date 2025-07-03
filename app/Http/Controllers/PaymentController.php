<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Charge;

class PaymentController extends Controller
{
    /**
     * Create a payment intent without requiring confirmation
     * This is used when creating a payment that will be confirmed later
     */
    public function createPaymentIntent(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Validate request
            $request->validate([
                'amount' => 'required|numeric|min:0.5', // Minimum amount for Stripe
            ]);

            $amount = round($request->amount * 100); // Convert to cents and ensure integer

            $paymentIntent = PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'usd',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never', // Disables redirect-based payment methods
                    ],
                    'metadata' => [
                        'appointment_id' => $request->appointment_id ?? null,
                    ],
                    'setup_future_usage' => 'off_session', // Optional: For saving payment method
                    ]);

                    return response()->json([
                        'status' => 1,
                        'payment_intent' => $paymentIntent,
                        'client_secret' => $paymentIntent->client_secret
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Payment creation failed: ' . $e->getMessage()
                    ], 500);
                }
            }

    /**
     * Confirm a payment intent
     * Used when the user actually makes the payment
     */
    public function confirmPaymentIntent(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $request->validate([
                'payment_intent_id' => 'required|string',
                'payment_method' => 'required|string',
            ]);

            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);
            $paymentIntent->confirm([
                'payment_method' => $request->payment_method,
            ]);

            return response()->json([
                'status' => 1,
                'payment_intent' => $paymentIntent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Payment confirmation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieve payment intent status
     * Used to check if a payment has been completed
     */
    public function getPaymentStatus(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $request->validate([
                'payment_intent_id' => 'required|string',
            ]);

            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            return response()->json([
                'status' => 1,
                'payment_status' => $paymentIntent->status,
                'payment_intent' => $paymentIntent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve payment status: ' . $e->getMessage()
            ], 500);
        }
    }
}
