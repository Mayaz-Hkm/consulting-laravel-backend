<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Expert;
use Illuminate\Http\Request;


class RateController extends Controller
{
    public function rateExpert($id, Request $request)
    {
        $expert = Expert::find($id);
        if (!$expert) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid Expert ID'
            ], 404);
        }

        // التحقق من وجود موعد مكتمل مع هذا الخبير من نفس المستخدم
        $appointment = Appointment::where('expert_id', $id)
            ->where('user_id', auth()->user()->id)
            ->where('is_completed', true) // التأكد من اكتمال الموعد
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 0,
                'message' => 'No completed appointment found for this expert'
            ], 400);
        }

        // التحقق إذا كان المستخدم قد قام بتقييم الخبير مسبقًا لهذا الموعد
        $rate = $expert->ratings()->where('user_id', auth()->user()->id)
            ->where('appointment_id', $appointment->id)
            ->first();

        if ($rate) {
            // تحديث التقييم الحالي
            $validated = $request->validate([
                'starsNumber' => ['required', 'numeric', 'min:0', 'max:5'],
                'comment' => ['nullable', 'string']
            ]);

            try {
                $rate->update($validated);
                $this->updateExpertRate($expert);

                return response()->json([
                    'status' => 1,
                    'message' => 'Rate Updated Successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 0,
                    'message' => 'An error occurred while updating the rate.',
                    'error' => $e->getMessage() // إضافة تفاصيل الخطأ
                ], 500);
            }

        } else {
            // إذا كانت هذه أول مرة يقوم فيها المستخدم بتقييم الخبير
            $data = $request->validate([
                'starsNumber' => ['required', 'numeric', 'min:0', 'max:5'],
                'comment' => ['nullable', 'string']
            ]);
            $data['user_id'] = auth()->user()->id;
            $data['appointment_id'] = $appointment->id;

            try {
                $expert->ratings()->create($data);
                $this->updateExpertRate($expert);

                return response()->json([
                    'status' => 1,
                    'message' => 'Rate Added Successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 0,
                    'message' => 'An error occurred while adding the rate.',
                    'error' => $e->getMessage() // إضافة تفاصيل الخطأ
                ], 500);
            }
        }
    }

    public function updateExpertRate($expert)
    {
        // حساب المتوسط بناءً على التقييمات الموجودة
        $ratingsCount = $expert->ratings()->count();
        if ($ratingsCount > 0) {
            $sum = $expert->ratings()->sum('starsNumber');
            $expertRate = $sum / $ratingsCount;
            $expert->rate = $expertRate;
        } else {
            $expert->rate = 0; // إذا لم يكن هناك تقييمات، تعيينه إلى 0.
        }
        $expert->save();
    }
}
