<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expert;
use App\Models\User;
use App\Models\Section;
use App\Models\ExpertSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Register Function
    public function register(): \Illuminate\Http\JsonResponse
    {
        if (request('isExpert')) {
            return $this->registerExpert();
        } else {
            return $this->registerClient();
        }
    }

    // Expert Registration
    public function registerExpert(): \Illuminate\Http\JsonResponse
    {
        $validated = $this->validateExpertRegistration();
        // Creating expert instance and assigning its password
        $expert = Expert::create($validated);
        $expert->password = Hash::make($validated['password']);
        $expert->tokens()->delete();
        $token = $expert->createToken('ExpertReg_auth_token')->plainTextToken;
        $expert->save();

        // Getting the selected working days and working hours
        $workingDays = $validated['working_days']; // Array of selected days
        $start_time = $validated['start_time'];
        $end_time = $validated['end_time'];

        // Add schedule for each selected day
        foreach ($workingDays as $day) {
            $expert->schedules()->create([
                'day' => $day,
                'start' => $start_time,
                'end' => $end_time,
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Expert registration successful',
            'token' => $token,
        ]);
    }

    // Client Registration
    public function registerClient(): \Illuminate\Http\JsonResponse
    {
        $validated = $this->validateClientRegistration();

        $user = User::create($validated);
        $user->password = Hash::make($validated['password']);
        $user->tokens()->delete();
        $token = $user->createToken('ClientReg_auth_token')->plainTextToken;

        $user->save();

        // Creating wallet for client
        //$user->wallet()->create();

        return response()->json([
            'status' => 1,
            'message' => 'Client registered successfully',
            "token" => $token,
        ]);
    }

    // Login Function
    public function login(): \Illuminate\Http\JsonResponse
    {
        \Log::info('isExpert value: ' . request('isExpert'));

        $credentials = request()->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ]);

        if (request('isExpert')) {
            return $this->loginExpert($credentials);
        } else {
            return $this->loginClient($credentials);
        }
    }

    // Expert Login
    public function loginExpert($credentials): \Illuminate\Http\JsonResponse
{
    \Log::info('Attempting expert login with: ' . json_encode($credentials));

    $expert = Expert::where('email', $credentials['email'])->first();

    if (!$expert || !Hash::check($credentials['password'], $expert->password)) {
        return response()->json([
            'status' => 0,
            'message' => 'Invalid credentials',
        ], 401);
    }

    $expert->tokens()->delete();
    $token = $expert->createToken('expert_auth_token')->plainTextToken;

    return response()->json([
        'status' => 1,
        'message' => 'Expert login successful',
        'isExpert' => true,
        'token' => $token,
    ]);
}

    // Client Login
    public function loginClient($credentials): \Illuminate\Http\JsonResponse
    {
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid user credentials',
            ]);
        }

        $user = Auth::user();
        $user->tokens()->delete();
        $token = $user->createToken('client_auth_token')->plainTextToken;

        return response()->json([
            'status' => 1,
            'message' => 'Client login successful',
            'isExpert' => 0,
            'token' => $token,
        ]);
    }

    public function logout(): \Illuminate\Http\JsonResponse
    {
        if (Auth::user() instanceof App\Models\Expert){
            return $this->logoutExpert();
        }
        else{
            return $this->logoutClient();
        }
    }

    // Logout Expert
    public function logoutExpert(): \Illuminate\Http\JsonResponse
    {
        $expert = request()->user('experts');
        $expert->tokens()->delete();
        return response()->json([
            'status' => 1,
            'message' => 'Logged out successfully',
        ]);
    }

    // Logout Client
    public function logoutClient(): \Illuminate\Http\JsonResponse
    {
        $client = request()->user();
        $client->tokens()->delete();
        return response()->json([
            'status' => 1,
            'message' => 'Logged out successfully',
        ]);
    }

    // Validation for Expert Registration
    public function validateExpertRegistration(): array
    {
        return request()->validate([
            'userName' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users', 'unique:experts'],
            'mobile' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'timezone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'category_id' => ['required', 'exists:categories,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'experience' => ['nullable', 'string', 'max:500'],
            'working_days' => ['required', 'array'], // Array of days
            'working_days.*' => ['required', 'string', 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);
    }

    // Validation for Client Registration
    public function validateClientRegistration(): array
    {
        return request()->validate([
            'userName' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'unique:users', 'unique:experts'],
            'mobile' => ['string', 'max:13'],
            'timezone' => ['required', 'string', 'timezone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

}

