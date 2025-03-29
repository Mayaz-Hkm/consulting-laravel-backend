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


class AppointmentController extends Controller
{

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

         // Rest of the existing code remains the same
         $deposit = $expert->hourPrice * 0.2;

         // استدعاء PaymentController لإنشاء PaymentIntent
         $paymentResponse = app(PaymentController::class)->PaymentIntent($request->merge(['amount' => $deposit]));

         if ($paymentResponse->getData()->status === 0) {
             return response()->json(['status' => 0, 'message' => __('Payment creation failed')], 500);
         }

         // إذا كانت عملية الدفع ناجحة
         $paymentIntent = $paymentResponse->getData()->payment_intent;


         // Create appointment without payment first
         DB::transaction(function () use ($user, $expert, $date, $isOpen) {
             $appointment = Appointment::create([
                 'user_id' => $user->id,
                 'expert_id' => $expert->id,
                 'from' => $date,
                 'to' => $isOpen ? null : $date->copy()->addMinutes($expert->session_duration ?? 60),
                 'is_open' => $isOpen,
                 'status' => 'pending',
             ]);

             // Notify expert about new appointment request
             Notification::send($expert, new AppointmentNotification([
                 "type" => "new_request",
                 "message" => "New appointment request for: " . $date->format('Y-m-d H:i'),
                 "appointment_id" => $appointment->id
             ]));
         });

         return response()->json([
             'status' => 1,
             'message' => __('Appointment request sent successfully, waiting for expert approval')
         ]);
     }

     // New function to handle expert's response to appointment request
     public function respondToAppointment(Request $request, $appointmentId): \Illuminate\Http\JsonResponse
     {
         $expert = auth()->guard('experts')->user();
         $appointment = Appointment::findOrFail($appointmentId);

         if ($appointment->expert_id !== $expert->id) {
             return response()->json(['status' => 0, 'message' => __('Unauthorized')], 403);
         }

         $response = $request->input('response'); // 'accept' or 'reject'

         if ($response === 'accept') {
             $appointment->status = 'accepted';

             $deposit = max(5, 0); // تأكد أن المبلغ لا يقل عن الحد الأدنى

             if ($deposit < 1) { // استبدل "1" بالحد الأدنى لحسابك في Stripe
                 return response()->json([
                     'status' => 0,
                     'message' => __('Deposit amount must be at least $1.')
                 ], 400);
             }

             $paymentResponse = app(PaymentController::class)->PaymentIntent($request->merge(['amount' => $deposit]));


             $paymentIntent = $paymentResponse->getData()->payment_intent;
             $appointment->payment_intent_id = $paymentIntent->id;
             $appointment->deposit_amount = $deposit;

             // Notify user about acceptance and payment requirement
             Notification::send($appointment->user, new AppointmentNotification([
                 "type" => "appointment_accepted",
                 "message" => "Your appointment request has been accepted. Please complete the payment.",
                 "payment_intent" => $paymentIntent
             ]));

         } else {
             $appointment->status = 'rejected';

             // Notify user about rejection
             Notification::send($appointment->user, new AppointmentNotification([
                 "type" => "appointment_rejected",
                 "message" => "Your appointment request has been rejected by the expert."
             ]));
         }

         $appointment->save();

         return response()->json([
             'status' => 1,
             'message' => __('Response recorded successfully')
         ]);
     }

     // New function to confirm payment and finalize appointment
     public function confirmAppointmentPayment($appointmentId): \Illuminate\Http\JsonResponse
     {
         $appointment = Appointment::findOrFail($appointmentId);
         $user = auth()->user();

         if ($appointment->user_id !== $user->id) {
             return response()->json(['status' => 0, 'message' => __('Unauthorized')], 403);
         }

         if ($appointment->status !== 'accepted') {
             return response()->json(['status' => 0, 'message' => __('Invalid appointment status')], 400);
         }

         $appointment->status = 'confirmed';
         $appointment->save();

         // Notify expert about confirmed appointment
         Notification::send($appointment->expert, new AppointmentNotification([
             "type" => "appointment_confirmed",
             "message" => "Appointment has been confirmed and payment received."
         ]));

         return response()->json([
             'status' => 1,
             'message' => __('Appointment confirmed successfully')
         ]);
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


//    public function addAppointment($id, Request $request): \Illuminate\Http\JsonResponse
//    {
//        $user = auth()->user();
//        $expert = Expert::findOrFail($id);
//
//        // تحويل التاريخ من توقيت المستخدم إلى توقيت الخبير
//        $date = Carbon::parse($request->date, $request->timezone)->setTimezone($expert->timezone);
//
//        // التحقق من أن الموعد ليس في الماضي
//        if ($date->isPast()) {
//            return response()->json(['status' => 0, 'message' => __('Cannot book appointments in the past')], 400);
//        }
//
//        // التحقق من أن الموعد قبل 24 ساعة على الأقل
//        if ($date->diffInHours(now()) < 24) {
//            return response()->json(['status' => 0, 'message' => __('Appointments must be booked at least 24 hours in advance')], 400);
//        }
//
//        $isOpen = $request->boolean('is_open', false);
//
//        $dayName = $date->format('D'); // تغيير التنسيق ليتناسب مع قاعدة البيانات
//        $schedule = $expert->schedules()->where('day', $dayName)->first();
//
//        // تسجيل معلومات التصحيح
//        Log::info('Requested day: ' . $dayName);
//        Log::info('Schedule found: ', ['schedule' => $schedule]);
//
//        if (!$schedule || !$schedule->isAvailable) {
//            return response()->json(['status' => 0, 'message' => __('Expert unavailable on this day')], 400);
//        }
//
//        // التحقق مما إذا كان الموعد ضمن ساعات عمل الخبير
//        $workStart = Carbon::parse($schedule->start)->setDateFrom($date);
//        $workEnd = Carbon::parse($schedule->end)->setDateFrom($date);
//
//        if ($date->lt($workStart) || $date->gt($workEnd)) {
//            return response()->json(['status' => 0, 'message' => __('Appointment time is outside working hours')], 400);
//        }
//
//        // التحقق من توافر الوقت
//        $isTimeSlotTaken = $expert->appointments()
//            ->where('from', '<=', $date)
//            ->where('to', '>', $date)
//            ->exists();
//
//        $hasOpenAppointments = $isOpen && $expert->appointments()->where('is_openيعن', true)->exists();
//
//        if ($isTimeSlotTaken || $hasOpenAppointments) {
//            return response()->json(['status' => 0, 'message' => __('Time slot not available')], 400);
//        }
//
//        //.
//        // إنشاء الموعد داخل معاملة لضمان السلامة
//        try {
//            $appointment = null;
//
//            DB::transaction(function () use ($user, $expert, $date, $isOpen, &$appointment) {
//                try {
//                    Log::info("Starting transaction for appointment creation");
//
//                    $appointment = Appointment::create([
//                        'user_id' => $user->id,
//                        'expert_id' => $expert->id,
//                        'from' => $date,
//                        'to' => $isOpen ? null : $date->copy()->addMinutes($expert->session_duration ?? 60),
//                        'is_open' => $isOpen,
//                        'status' => 'pending',
//                    ]);
//
//                    Log::info("Appointment created: ", ['appointment' => $appointment]);
//
//                    if (!$appointment || !$appointment->id) {
//                        throw new \Exception("Appointment record not created properly");
//                    }
//
//                    Notification::send($expert, new AppointmentNotification([
//                        "type" => "new_request",
//                        "message" => "New appointment request for: " . $date->format('Y-m-d H:i'),
//                        "appointment_id" => $appointment->id
//                    ]));
//
//                    Log::info("Notification sent successfully");
//                } catch (\Exception $e) {
//                    Log::error("Transaction failed: " . $e->getMessage());
//                    throw $e; // هذا يسمح بـ rollback
//                }
//            });
//
//            // التحقق مما إذا تم إنشاء الموعد بنجاح
//            if (!$appointment || !$appointment->id) {
//                throw new \Exception("Failed to create appointment record");
//            }
//
//            return response()->json([
//                'status' => 1,
//                'message' => __('Appointment request sent successfully, waiting for expert approval'),
//                'appointment_id' => $appointment->id
//            ]);
//        } catch (\Exception $e) {
//            Log::error('Failed to create appointment: ' . $e->getMessage());
//            return response()->json([
//                'status' => 0,
//                'message' => __('Failed to create appointment: ') . $e->getMessage()
//            ], 500);
//        }
//    }
//




// public function addAppointment($id, Request $request): \Illuminate\Http\JsonResponse
// {
//     $user = auth()->user();
//     $expert = Expert::findOrFail($id);

//     // Convert date from user's timezone to expert's timezone
//     $date = Carbon::parse($request->date, $request->timezone)->setTimezone($expert->timezone);

//     // Check if the appointment date is in the past
//     if ($date->isPast()) {
//         return response()->json(['status' => 0, 'message' => __('Cannot book appointments in the past')], 400);
//     }

//     // Check if the appointment is at least 24 hours in advance
//     if ($date->diffInHours(now()) < 24) {
//         return response()->json(['status' => 0, 'message' => __('Appointments must be booked at least 24 hours in advance')], 400);
//     }

//     $isOpen = $request->boolean('is_open', false);

//     $dayName = $date->format('D'); // Change from 'l' to 'D' to match your database format
//     $schedule = $expert->schedules()->where('day', $dayName)->first();

//     // Add debug logging
//     Log::info('Requested day: ' . $dayName);
//     Log::info('Schedule found: ', ['schedule' => $schedule]);

//     if (!$schedule || !$schedule->isAvailable) {
//         return response()->json(['status' => 0, 'message' => __('Expert unavailable on this day')], 400);
//     }

//     // Check if the appointment time falls within the expert's working hours
//     $workStart = Carbon::parse($schedule->start)->setDateFrom($date);
//     $workEnd = Carbon::parse($schedule->end)->setDateFrom($date);

//     if ($date->lt($workStart) || $date->gt($workEnd)) {
//         return response()->json(['status' => 0, 'message' => __('Appointment time is outside working hours')], 400);
//     }

//     // Check for time slot availability
//     if ($expert->appointments()
//         ->where('from', '<=', $date)
//         ->where('to', '>', $date)
//         ->exists() ||
//         ($isOpen && $expert->appointments()->where('is_open', true)->exists())) {
//         return response()->json(['status' => 0, 'message' => __('Time slot not available')], 400);
//     }

//     // Rest of the existing code remains the same
//     $deposit = $expert->hourPrice * 0.2;

//        // استدعاء PaymentController لإنشاء PaymentIntent
//        $paymentResponse = app(PaymentController::class)->createPaymentIntent($request->merge(['amount' => $deposit]));

//        if ($paymentResponse->getData()->status === 0) {
//            return response()->json(['status' => 0, 'message' => __('Payment creation failed')], 500);
//        }

//        // إذا كانت عملية الدفع ناجحة
//        $paymentIntent = $paymentResponse->getData()->payment_intent;

//        // تخزين الموعد بعد التأكد من الدفع
//        DB::transaction(function () use ($user, $expert, $date, $deposit, $paymentIntent, $isOpen) {
//            Appointment::create([
//                'user_id' => $user->id,
//                'expert_id' => $expert->id,
//                'from' => $date,
//                'to' => $isOpen ? null : $date->copy()->addMinutes($expert->session_duration ?? 60),
//                'is_open' => $isOpen,
//                'deposit_amount' => $deposit,
//                'payment_intent_id' => $paymentIntent->id,
//            ]);

//            // إرسال إشعار للخبير
//            Notification::send($expert, new AppointmentNotification("New appointment booked: " . $date->format('Y-m-d H:i')));

//            // إرسال إشعار للمستخدم
//            Notification::send($user, new AppointmentNotification("Your appointment has been booked successfully."));
//        });

//        return response()->json([
//            'status' => 1,
//            'message' => __('Appointment booked successfully with deposit'),
//            'payment_intent' => $paymentIntent
//        ]);
//    }














//----------------------------------------------------------------------------------------------------------------------

//--------------------------------------------------------------------------------------------------------------------------------------------------------------

}
