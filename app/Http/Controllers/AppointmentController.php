<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Expert;
use App\Models\Rate;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AppointmentNotification;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

class AppointmentController extends Controller
{

    private function getAuthenticatedUser()
    {
        $user = auth('users')->user(); // الحارس الافتراضي للمستخدم العادي
        if ($user) {
            $user->type = 'user';
            return $user;
        }

        $expert = auth('experts')->user(); // الحارس الخاص بالخبير
        if ($expert) {
            $expert->type = 'expert';
            return $expert;
        }

        return null;
    }







    public function showAppointments($id, Request $request)
    {
        $selectedDate = $request->input('selectedDate');

        if (!$selectedDate) {
            return response()->json(['error' => 'Missing required parameter: selectedDate'], 400);
        }

        $currentDate = Carbon::today();
        $selectedDateParsed = Carbon::parse($selectedDate);

        if ($selectedDateParsed->lt($currentDate)) {
            return response()->json(['error' => 'Invalid date: selected date cannot be in the past'], 400);
        }

        $expert = Expert::find($id);
        if (!$expert) {
            return response()->json(['error' => 'Expert not found'], 404);
        }

        $selectedDay = $selectedDateParsed->format('D');
        Log::info('Selected Day: ' . $selectedDay);

        // Get all schedules for the expert for the selected day
        $schedules = Schedule::where('expert_id', $id)
            ->where('day', $selectedDay)
            ->where('isAvailable', true)
            ->get();

        // Check if the expert has an available schedule for that day
        if ($schedules->isEmpty()) {
            return response()->json([
                'message' => 'Sorry, the expert is not available for appointments on this date or all slots are already taken.',
                'availableAppointments' => []
            ], 400);
        }

        $response = [
            'date' => $selectedDate,
            'status' => 'success',
            'appointments' => [
                'availableAppointments' => []
            ]
        ];

        // Loop through the schedules and determine availability
        foreach ($schedules as $schedule) {
            $workHoursStart = Carbon::parse($schedule->start);
            $workHoursEnd = Carbon::parse($schedule->end);

            $availableAppointments = [];
            $currentSlot = clone $workHoursStart;

            // Get appointments that are already booked for the selected date
            $appointments = Appointment::where('expert_id', $id)
                ->whereDate('from', $selectedDateParsed)
                ->where('is_open', true)
                ->get();

            // Collect blocked slots
            $blockedSlots = [];
            foreach ($appointments as $appointment) {
                $blockedSlots[] = [
                    'from' => Carbon::parse($appointment->from),
                    'to' => Carbon::parse($appointment->to)
                ];
            }

            // Determine available time slots
            $lastAvailableEnd = $workHoursStart;

            foreach ($blockedSlots as $blockedSlot) {
                // Before blocked slot, if there's available time, add it
                if ($lastAvailableEnd->lt($blockedSlot['from'])) {
                    $availableAppointments[] = [
                        'from' => $lastAvailableEnd->format('H:i'),
                        'to' => $blockedSlot['from']->format('H:i')
                    ];
                }

                // Update last available time to be after the blocked slot
                $lastAvailableEnd = $blockedSlot['to'];
            }

            // Add remaining time after last blocked slot, if any
            if ($lastAvailableEnd->lt($workHoursEnd)) {
                $availableAppointments[] = [
                    'from' => $lastAvailableEnd->format('H:i'),
                    'to' => $workHoursEnd->format('H:i')
                ];
            }

            // If there are available slots, add them to the response
            if (!empty($availableAppointments)) {
                $response['appointments']['availableAppointments'] = $availableAppointments;
            } else {
                // If no slots are available, send a message to the user
                $response['appointments']['message'] = 'No available appointments for the selected day, please choose another time.';
            }
        }

        return response()->json($response, 200);
    }








    public function addAppointment($id, Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $expert = Expert::findOrFail($id);

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (!$latitude || !$longitude) {
            return response()->json(['status' => 0, 'message' => __('Location coordinates are required')]);
        }
        // Convert date from user's timezone to expert's timezone
        $date = Carbon::parse($request->date, $request->timezone)->setTimezone($expert->timezone);

        // Check if the appointment date is in the past
        if ($date->isPast()) {
            return response()->json(['status' => 0, 'message' => __('Cannot book appointments in the past')], 400);
        }

        // Check if the appointment is at least 24 hours in advance
        if ($date->diffInHours(now()) < 24) {
            return response()->json(['status' => 0, 'message' => __('Appointments must be booked at least 24 hours in advance')], 400);
        }

        $isOpen = $request->boolean('is_open', false);

        $dayName = $date->format('D'); // Change from 'l' to 'D' to match your database format
        $schedule = $expert->schedules()->where('day', $dayName)->first();

        // Add debug logging
        Log::info('Requested day: ' . $dayName);
        Log::info('Schedule found: ', ['schedule' => $schedule]);

        if (!$schedule || !$schedule->isAvailable) {
            return response()->json(['status' => 0, 'message' => __('Expert unavailable on this day')], 400);
        }

        // Check if the appointment time falls within the expert's working hours
        $workStart = Carbon::parse($schedule->start)->setDateFrom($date);
        $workEnd = Carbon::parse($schedule->end)->setDateFrom($date);

        if ($date->lt($workStart) || $date->gt($workEnd)) {
            return response()->json(['status' => 0, 'message' => __('Appointment time is outside working hours')], 400);
        }

        // Check for time slot availability
        if ($expert->appointments()
                ->where('from', '<=', $date)
                ->where('to', '>', $date)
                ->exists() ||
            ($isOpen && $expert->appointments()->where('is_open', true)->exists())) {
            return response()->json(['status' => 0, 'message' => __('Time slot not available')], 400);
        }

        // Create appointment without payment first
        $appointment = DB::transaction(function () use ($longitude, $latitude, $user, $expert, $date, $isOpen) {
            $appointment = Appointment::create([
                'user_id' => $user->id,
                'expert_id' => $expert->id,
                'from' => $date,
                'to' => $isOpen ? null : $date->copy()->addMinutes($expert->session_duration ?? 60),
                'is_open' => $isOpen,
                'status' => 'pending',
                'deposit_amount' => 0,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'payment_intent_id' => '',
            ]);

            // Notify expert about new appointment request
            Notification::send($expert, new AppointmentNotification([
                "type" => "new_request",
                "message" => "New appointment request for: " . $date->format('Y-m-d H:i'),
                "appointment_id" => $appointment->id,
                'latitude' => $latitude,
                'longitude' => $longitude,

            ]));

            return $appointment;
        });

        return response()->json([
            'status' => 1,
            'message' => __('Appointment request sent successfully, waiting for expert approval'),
            'appointment_id' => $appointment->id
        ]);
    }

    // New function to handle expert's response to appointment request
    public function respondToAppointment(Request $request, $expert_id, Appointment $appointment): \Illuminate\Http\JsonResponse
{
    $expert = auth()->guard('experts')->user();

    if (!$expert) {
        return response()->json(['status' => 0, 'message' => __('Expert not authenticated')], 401);
    }

    if ($appointment->expert_id != $expert->id) {
        return response()->json(['status' => 0, 'message' => __('Unauthorized')], 403);
    }

    $response = $request->input('response'); // 'accept' or 'reject'

    if (!in_array($response, ['accept', 'reject'])) {
        return response()->json(['status' => 0, 'message' => __('Invalid response')], 400);
    }

    // Handle expert's response
    if ($response === 'accept') {
        try {

            $hourPrice = $expert->hourPrice ?? 0;
            $depositAmount = max(10, $hourPrice * 0.2); // minimum $10 deposit or 20% hourly rate

            Stripe::setApiKey(config('services.stripe.secret'));

            // create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => round($depositAmount * 100), // convert to cents
                'currency' => 'usd',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'metadata' => [
                    'appointment_id' => $appointment->id,
                    'user_id' => $appointment->user_id,
                    'expert_id' => $expert->id
                ],
                'setup_future_usage' => 'off_session', // Optional: For saving payment method
            ]);

            $appointment->status = 'accepted';
            $appointment->deposit_amount = $depositAmount;
            $appointment->payment_intent_id = $paymentIntent->id;
            $appointment->save();

            // Notify user about acceptance and payment requirement
            if ($appointment->user) {
                Notification::send($appointment->user, new AppointmentNotification([
                    "type" => "appointment_accepted",
                    "message" => "Your appointment request has been accepted. Please complete the payment.",
                    "appointment_id" => $appointment->id,
                    "payment_client_secret" => $paymentIntent->client_secret,
                    "deposit_amount" => $depositAmount
                ]));
            }

            return response()->json([
                'status' => 1,
                'message' => __('Appointment accepted, payment intent created'),
                'payment_intent' => [
                    'id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret
                ]
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            return response()->json(['status' => 0, 'message' => __('Payment processing error: ') . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('Error accepting appointment: ' . $e->getMessage());
            return response()->json(['status' => 0, 'message' => __('Error accepting appointment: ') . $e->getMessage()], 500);
        }
    } else {
        $appointment->status = 'rejected';
        $appointment->save();

        if ($appointment->user) {
            Notification::send($appointment->user, new AppointmentNotification([
                "type" => "appointment_rejected",
                "message" => "Your appointment request has been rejected by the expert."
            ]));
        }

        return response()->json(['status' => 1, 'message' => __('Appointment rejected')]);
    }
}

    // New function to confirm payment and finalize appointment
    public function confirmPayment(Request $request, $appointmentId): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $appointment = Appointment::findOrFail($appointmentId);

        if ($appointment->user_id !== $user->id) {
            return response()->json(['status' => 0, 'message' => __('Unauthorized')], 403);
        }

        if ($appointment->status !== 'accepted') {
            return response()->json(['status' => 0, 'message' => __('Invalid appointment status')], 400);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $paymentIntent = PaymentIntent::retrieve($appointment->payment_intent_id);

            if ($request->has('payment_method_id')) {
                $paymentIntent = PaymentIntent::update($appointment->payment_intent_id, [
                    'payment_method' => $request->input('payment_method_id'),
                ]);

                $paymentIntent->confirm();

                $paymentIntent = PaymentIntent::retrieve($appointment->payment_intent_id);
            }

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'status' => 0,
                    'message' => __('Payment not completed'),
                    'payment_status' => $paymentIntent->status,
                    'requires_action' => $paymentIntent->status === 'requires_action',
                    'payment_intent_client_secret' => $paymentIntent->client_secret
                ], 400);
            }

            $appointment->status = 'confirmed';
            $appointment->save();

            // Notify expert about confirmed appointment
            $expert = $appointment->expert;
            if ($expert) {
                Notification::send($expert, new AppointmentNotification([
                    "type" => "appointment_confirmed",
                    "message" => "Appointment has been confirmed and payment received."
                ]));
            } else {
                Log::warning('Could not notify expert for appointment #' . $appointmentId . ' - Expert not found');
            }

            return response()->json([
                'status' => 1,
                'message' => __('Appointment confirmed successfully')
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            return response()->json(['status' => 0, 'message' => __('Payment verification error: ') . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('Error confirming appointment: ' . $e->getMessage());
            return response()->json(['status' => 0, 'message' => __('Error confirming appointment: ') . $e->getMessage()], 500);
        }
    }

    // check payment status
    public function checkPaymentStatus($appointmentId): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $appointment = Appointment::findOrFail($appointmentId);

        if ($appointment->user_id !== $user->id) {
            return response()->json(['status' => 0, 'message' => __('Unauthorized')], 403);
        }

        if (empty($appointment->payment_intent_id)) {
            return response()->json(['status' => 0, 'message' => __('No payment associated with this appointment')], 400);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $paymentIntent = PaymentIntent::retrieve($appointment->payment_intent_id);

            return response()->json([
                'status' => 1,
                'payment_status' => $paymentIntent->status,
                'appointment_status' => $appointment->status,
                'client_secret' => $paymentIntent->client_secret
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            return response()->json(['status' => 0, 'message' => __('Payment status check error: ') . $e->getMessage()], 500);
        }
    }

    // New function to lock/unlock open appointments
    public function toggleAppointmentLock($appointmentId): \Illuminate\Http\JsonResponse
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $expert = auth()->guard('experts')->user();

        if ($appointment->expert_id !== $expert->id) {
            return response()->json(['status' => 0, 'message' => __('Unauthorized')], 403);
        }

        if (!$appointment->is_open) {
            return response()->json(['status' => 0, 'message' => __('Only open appointments can be locked')], 400);
        }

        $appointment->is_locked = !$appointment->is_locked;
        $appointment->save();

        return response()->json([
            'status' => 1,
            'message' => $appointment->is_locked ?
                __('Appointment locked successfully') :
                __('Appointment unlocked successfully')
        ]);
    }

    //ـــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــــ
//    public function userAppointmentHistory(Request $request)
//    {
//        $user = auth()->user();
//
//        // جلب كل المواعيد للمستخدم التي حالتها pending أو accepted أو rejected
//        $appointments = Appointment::where('user_id', $user->id)
//            ->whereIn('status', ['pending', 'accepted', 'rejected'])
//            ->orderByDesc('from') // من الأحدث إلى الأقدم
//            ->get();
//
//        return response()->json([
//            'status' => 1,
//            'appointments' => $appointments
//        ]);
//    }

    public function myAppointments(Request $request)
    {
        $statuses = ['pending', 'accepted', 'rejected'];

        $authUser = $this->getAuthenticatedUser();

        if (!$authUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($authUser->type === 'expert') {
            $appointments = Appointment::with(['user']) // جلب بيانات المستخدم
            ->where('expert_id', $authUser->id)
                ->whereIn('status', $statuses)
                ->orderByDesc('from')
                ->get();

            return response()->json([
                'type' => 'expert',
                'appointments' => $appointments
            ]);
        }

        if ($authUser->type === 'user') {
            $appointments = Appointment::with(['expert']) // جلب بيانات الخبير
            ->where('user_id', $authUser->id)
                ->whereIn('status', $statuses)
                ->orderByDesc('from')
                ->get();

            return response()->json([
                'type' => 'user',
                'appointments' => $appointments
            ]);
        }


        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
