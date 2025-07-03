<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Models\Appointment;
class NotificationController extends Controller

{

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $expert = auth()->guard('experts')->user();

        $notifications = $expert->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data;
                $user = null;

                // إذا كانت بيانات المستخدم موجودة بشكل منفصل في الجدول
                if ($notification->user_id) {
                    $user = [
                        'id' => $notification->user_id,
                        'name' => $notification->user_name,
                    ];
                } else {
                    // إذا كانت بيانات المستخدم مخزنة في data
                    if (!empty($data['appointment_id'])) {
                        $appointment = Appointment::with('user')->find($data['appointment_id']);

                        // التأكد من وجود appointment والمستخدم
                        if ($appointment && $appointment->user) {
                            $user = [
                                'id' => $appointment->user->id,
                                'name' => $appointment->user->userName,
                            ];
                        }
                    }
                }

                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? 'general',
                    'message' => $data['message'] ?? 'No message',
                    'appointment_id' => $data['appointment_id'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'user' => $user,
                    'expert_id' => $notification->notifiable_id, // إضافة expert_id هنا
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => 1,
            'notifications' => $notifications,
        ]);
    }
    public function userNotifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not authenticated'], 401);
        }

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data;
                $baseMessage = [
                    'type' => $data['type'] ?? 'general',
                    'message' => $data['message'] ?? 'No message',
                ];

                // إذا نوع الإشعار "تم قبول الموعد"
                if (($data['type'] ?? '') === 'appointment_accepted') {
                    $appointment = Appointment::with('expert')->find($data['appointment_id'] ?? null);
                    return [
                        'id' => $notification->id,
                        'type' => 'appointment_accepted',
                        'message' => array_merge($baseMessage, [
                            'appointment_id' => $data['appointment_id'] ?? null,
                            'deposit_amount' => $data['deposit_amount'] ?? null,
                        ]),
                        'appointment_id' => $data['appointment_id'] ?? null,
                        'expert_id' => $appointment?->expert?->id,
                        'expert_name' => $appointment?->expert?->name,
                        'created_at' => $notification->created_at->toDateTimeString(),
                    ];
                }

                // إشعارات أخرى
                return [
                    'id' => $notification->id,
                    'type' => $baseMessage['type'],
                    'message' => $baseMessage,
                    'created_at' => $notification->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'status' => 1,
            'notifications' => $notifications,
        ]);
    }


}
