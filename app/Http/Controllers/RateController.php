<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Expert;
use Illuminate\Http\Request;
use App\Models\Rate;
use App\Models\User;

class RateController extends Controller
{
    public function rate($appointment_id, Request $request)
    {
        // الحصول على الموعد
        $appointment = Appointment::find($appointment_id);

        if (!$appointment || !$appointment->is_completed) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid or incomplete appointment.'
            ], 400);
        }

        // التحقق من المستخدم المصادق
        $auth = $this->getAuthenticatedUser();

        if (!$auth) {
            return response()->json([
                'status' => 0,
                'message' => 'Unauthorized'
            ], 401);
        }

        // التحقق من نوع المصادق
        if ($auth->type == 'user') {
            // المستخدم يقيّم الخبير
            if ($appointment->user_id != $auth->id) {
                return response()->json([
                    'status' => 0,
                    'message' => 'You are not authorized to rate this expert.'
                ], 403);
            }

            $validated = $request->validate([
                'starsNumber' => ['required', 'numeric', 'min:0', 'max:5'],
                'comment' => ['nullable', 'string'],
                'low_rating_reason' => ['nullable', 'string'],
            ]);

            if ($validated['starsNumber'] < 3 && empty($validated['low_rating_reason'])) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Please provide a reason for the low rating (optional but can\'t be empty).'
                ], 400);
            }

            $rating = Rate::updateOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'rated_by' => 'user'
                ],
                [
                    'expert_id' => $appointment->expert_id,
                    'user_id' => $auth->id,
                    'starsNumber' => $validated['starsNumber'],
                    'comment' => $validated['comment'],
                    'low_rating_reason' => $validated['low_rating_reason'],
                    'rated_by' => 'user'
                ]
            );

            $this->updateExpertRate($appointment->expert);

            return response()->json([
                'status' => 1,
                'message' => 'Expert rated successfully',
                'data' => $rating
            ]);
        }

        if ($auth->type == 'expert') {
            // الخبير يقيّم المستخدم
            if ($appointment->expert_id != $auth->id) {
                return response()->json([
                    'status' => 0,
                    'message' => 'You are not authorized to rate this user.'
                ], 403);
            }

            $validated = $request->validate([
                'starsNumber' => ['required', 'numeric', 'min:0', 'max:5'],
                'comment' => ['nullable', 'string'],
                'low_rating_reason' => ['nullable', 'string'],
            ]);

            if ($validated['starsNumber'] < 3 && empty($validated['low_rating_reason'])) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Please provide a reason for the low rating (optional but can\'t be empty).'
                ], 400);
            }

            $rating = Rate::updateOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'rated_by' => 'expert'
                ],
                [
                    'expert_id' => $auth->id,
                    'user_id' => $appointment->user_id,
                    'starsNumber' => $validated['starsNumber'],
                    'comment' => $validated['comment'],
                    'low_rating_reason' => $validated['low_rating_reason'],
                    'rated_by' => 'expert'
                ]
            );

            $this->updateUserRate($appointment->user);

            return response()->json([
                'status' => 1,
                'message' => 'User rated successfully',
                'data' => $rating
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Unknown user type'
        ], 400);
    }

    private function updateExpertRate($expert)
    {
        $ratings = $expert->ratings()->where('rated_by', 'user');
        $count = $ratings->count();
        $average = $count ? $ratings->avg('starsNumber') : 0;

        $expert->rate = $average;
        $expert->save();
    }

    private function updateUserRate($user)
    {
        $ratings = $user->ratings()->where('rated_by', 'expert');
        $count = $ratings->count();
        $average = $count ? $ratings->avg('starsNumber') : 0;

        $user->rate = $average;
        $user->save();
    }

    private function getAuthenticatedUser()
    {
        $user = auth('users')->user();
        if ($user) {
            $user->type = 'user';
            return $user;
        }

        $expert = auth('experts')->user();
        if ($expert) {
            $expert->type = 'expert';
            return $expert;
        }

        return null;
    }
}
