<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('register' , [AuthController::class , 'register']);
Route::post('login' , [AuthController::class , 'login']);
Route::post('logout' , [AuthController::class , 'logout'])->middleware(['auth:sanctum']);
//---------------------------------------------------------------------------------------------



// راوت لجلب التصنيفات مع الأقسام الفرعية
Route::get('/categories', [CategoryController::class, 'getCategoriesWithSections']);
// راوت لعرض قسم معين بناءً على المعرف
Route::get('/categories/{id}', [CategoryController::class, 'showCategory']);
// راوت للبحث عن الخبراء بناءً على التقييم
Route::get('/categories/{categoryId}/experts/searchByRating', [CategoryController::class, 'searchExpertsByRating']);
Route::get('/get-all-categories', [CategoryController::class, 'getCategories']);
Route::get('/get-all-sections/{category_id}', [CategoryController::class, 'getSections']);



//----------------------------------------------------------------------------------------------------



Route::middleware('auth:sanctum')->group(function () {

    Route::post('/update-profile',[ProfileController::class,'updateProfile']);
    Route::get('/profile', [ProfileController::class, 'showProfile']);
    Route::get('/profile/{userName}', [ProfileController::class, 'showOtherProfile']);



//-----------------------------------------------------------------------------------------------------

//إنشاء منشور جديد

    Route::post('/create-post', [PostController::class, 'store']);

// جلب جميع المنشورات
    Route::get('/posts', [PostController::class, 'index']);

    // تعديل المنشور (فقط صاحب المنشور يمكنه التعديل)
    Route::put('/posts/{postId}', [PostController::class, 'update']);

    // حذف المنشور (فقط صاحب المنشور يمكنه الحذف)
    Route::delete('/posts/{postId}', [PostController::class, 'destroy']);

    Route::post('/posts/{postId}/like', [PostController::class, 'like']);

//------------------------------------------------------------------------------------------------------


    // إضافة تعليق جديد
    Route::post('posts/{post_id}/comments', [PostController::class, 'addComment']);

    //عرض كومينتات لبوست معين
    Route::get('/posts/{postId}/comments', [PostController::class, 'getPostComments']);

    // تعديل تعليق (فقط صاحب التعليق يمكنه التعديل)
    Route::put('/comments/{commentId}', [PostController::class, 'updateComment']);

    // حذف تعليق (فقط صاحب التعليق يمكنه الحذف)
    Route::delete('/comments/{commentId}', [PostController::class, 'deleteComment']);



//-----------------------------------------------------------------------------------------------------




    Route::post('/chats/{other_user_id}', [ChatController::class, 'openChat']); // بدء محادثة

    Route::post('/chats/{chat_id}/messages', [ChatController::class, 'sendMessage']); // إرسال رسالة

    Route::get('/chats/{chat_id}/messages', [ChatController::class, 'showMessages']); // عرض الرسائل

    Route::get('/chats', [ChatController::class, 'showChats']); // عرض جميع المحادثات

    Route::delete('/chats/{chat_id}', [ChatController::class, 'deleteChat']); // حذف محادثة
});


//------------------------------------------------------------------------------------------------------


Route::post('/payment', [PaymentController::class, 'processPayment'])->name('payment.process');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
//-------------------------------------------------------------------------------------------------------


Route::get('expert/{id}/appointments', [AppointmentController::class, 'showAppointments']); // عرض مواعيد الخبير المتاحة
Route::post('expert/{id}/appointments', [AppointmentController::class, 'addAppointment']); // إضافة موعد جديد
Route::patch('appointments/{id}/close', [AppointmentController::class, 'closeOpenAppointment']); // إغلاق الموعد المفتوح
Route::delete('appointments/{appointmentId}', [AppointmentController::class, 'cancelAppointment']); // إلغاء الموعد
Route::put('appointments/{appointmentId}', [AppointmentController::class, 'updateAppointment']); // تحديث الموعد
Route::patch('appointments/{appointmentId}/complete', [AppointmentController::class, 'completeAppointment']); // إكمال الموعد





Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
