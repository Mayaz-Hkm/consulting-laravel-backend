<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{


    private function getAuthenticatedUser()
    {
        return auth()->user();
    }

    // دالة مساعدة لبناء بيانات البروفايل بناءً على نوع المستخدم
    private function buildProfileData($user)
    {
        if ($user instanceof User) {
            return [
                'type' => 'User',
                'userName' => $user->userName,
                'email' => $user->email,
                'imagePath' => $user->imagePath,
                'timezone' => $user->timezone,
                'mobile' => $user->mobile
            ];
        }

        if ($user instanceof Expert) {
            $scheduleData = $user->schedules->map(function ($schedule) {
                return [
                    'day' => $schedule->day,
                    'start' => $schedule->start,
                    'end' => $schedule->end,
                ];
            });

            return [
                'type' => 'Expert',
                'userName' => $user->userName,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'imagePath' => $user->imagePath,
                'timezone' => $user->timezone,
                'category' => $user->category->categoryName,
                'section' => $user->section->sectionName,
                'experience' => $user->experience,
                'rate' => $user->rate,
                'schedules' => $scheduleData
            ];
        }

        return [];
    }

    // دالة مساعدة لرفع الصورة والتحقق من طول المسار
    private function uploadImage($request, $field = 'imagePath')
    {
        if ($request->hasFile($field)) {
            $imagePath = $request->file($field)->store('profiles', 'public');
            if (strlen($imagePath) > 255) {
                return response()->json(['status' => 0, 'message' => 'Image path too long'], 400);
            }
            return $imagePath;
        }
        return null;
    }

    // عرض بيانات البروفايل للمستخدم الحالي
    public function showProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser();

        if (!$currentUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $profileData = $this->buildProfileData($currentUser);

        return response()->json($profileData);
    }





    // تحديث بيانات البروفايل للمستخدم
    public function updateProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser();

        if ($currentUser instanceof User) {
            return $this->updateUserProfile($request, $currentUser);
        }

        if ($currentUser instanceof Expert) {
            return $this->updateExpertProfile($request, $currentUser);
        }

        return response()->json(['status' => 0, 'message' => 'Invalid user type'], 400);
    }
    // تحديث بروفايل المستخدم
    private function updateUserProfile(Request $request, User $currentUser)
    {

        $validatedData = $request->validate([
            'userName' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users,email,' . $currentUser->id],
            'mobile' => ['nullable', 'string', 'max:13'],
            'imagePath' => ['nullable', 'image', 'mimes:jpg,jpeg,png|max:2048'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        // رفع الصورة إذا تم تقديمها
        $imagePath = $this->uploadImage($request);
        if ($imagePath) {
            $validatedData['imagePath'] = $imagePath;
        }

        // تحديث البيانات
        $currentUser->update($validatedData);

        return response()->json([
            'status' => 1,
            'message' => 'User profile updated successfully',
            'profile' => $currentUser->only(['userName', 'email', 'mobile', 'imagePath', 'timezone']),
        ]);
    }





    // تحديث بروفايل الخبير
    private function updateExpertProfile(Request $request, Expert $currentUser)
    {

        $validatedData = $request->validate([
            'userName' => 'required|string|max:30',
            'email' => ['required', 'email', 'unique:experts,email,' . $currentUser->id],
            'mobile' => ['required', 'string', 'max:10'],
            'imagePath' => ['nullable', 'image', 'mimes:jpg,jpeg,png|max:2048'],
            'timezone' => ['required', 'string', 'timezone'],
            'category_id' => ['required', 'exists:categories,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'experience' => ['nullable', 'string', 'max:500'],
            //'start_time' => ['required', 'date_format:H:i'],
            //'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.day' => ['required', 'string', 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'schedules.*.start' => ['required', 'date_format:H:i'],
            'schedules.*.end' => ['required', 'date_format:H:i', 'after:schedules.*.start'],
        ]);

        // رفع الصورة إذا تم تقديمها
        $imagePath = $this->uploadImage($request);
        if ($imagePath) {
            $validatedData['imagePath'] = $imagePath;
        }

        // تحديث الحقول العامة
        $currentUser->update([
            'userName' => $validatedData['userName'],
            'email' => $validatedData['email'],
            'mobile' => $validatedData['mobile'],
            'imagePath' => $validatedData['imagePath'] ?? $currentUser->imagePath,
            'timezone' => $validatedData['timezone'],
            'category_id' => $validatedData['category_id'],
            'section_id' => $validatedData['section_id'],
            'experience' => $validatedData['experience'],
        ]);

        // التحقق من الجدول الزمني
        if (isset($validatedData['schedules'])) {
            // اجلب جميع الجداول القديمة الخاصة بالخبير الحالي
            $existingSchedules = $currentUser->schedules;

            // اجلب الأيام الجديدة من البيانات المدخلة
            $newScheduleDays = array_map(function ($schedule) {
                return $schedule['day'];
            }, $validatedData['schedules']);

            // حذف الأيام القديمة التي لم تعد موجودة في البيانات المدخلة
            foreach ($existingSchedules as $existingSchedule) {
                // إذا كان اليوم الحالي ليس ضمن الأيام الجديدة، قم بحذفه
                if (!in_array($existingSchedule->day, $newScheduleDays)) {
                    $existingSchedule->delete(); // حذف الجدول الذي تم إلغاؤه
                }
            }

            // إضافة الأيام الجديدة أو تحديث الأوقات
            foreach ($validatedData['schedules'] as $schedule) {
                $currentUser->schedules()->updateOrCreate(
                    ['day' => $schedule['day']], // إذا كان اليوم موجوداً، سيتم تحديثه، وإلا سيتم إنشاؤه
                    ['start' => $schedule['start'], 'end' => $schedule['end']]  // تحديث أوقات البداية والنهاية
                );
            }
        }

        return response()->json([
            'status' => 1,
            'message' => 'Expert profile updated successfully',
            'profile' => $currentUser->only(['userName', 'email', 'mobile', 'imagePath', 'timezone', 'category_id', 'section_id', 'experience']),
        ]);
    }






    // عرض بيانات بروفايل مستخدم آخر
    public function showOtherProfile($userName)
    {
        // Check Expert table first
        $currentUser = Expert::where('userName', $userName)->first();

        // If not found, check User table
        if (!$currentUser) {
            $currentUser = User::where('userName', $userName)->first();
        }

        if (!$currentUser) {
            return response()->json([
                'status' => 0,
                'message' => 'User not found'
            ], 404);
        }

        // بناء بيانات الملف الشخصي
        $profileData = $this->buildProfileData($currentUser);

        // إضافة الـ id إلى البيانات المعادة
        $profileData['id'] = $currentUser->id;

        return response()->json($profileData);
    }

}
