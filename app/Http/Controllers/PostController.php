<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class PostController extends Controller
{



    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        // استرجاع المستخدم المتصل عبر التوكن
        $user = Auth::user();

        // التحقق مما إذا كان المستخدم خبيرًا
        $expert = Expert::where('email', $user->email)->orWhere('mobile', $user->mobile)->first();

        if ($expert) {
            // المستخدم خبير: إظهار البوستات التي تتناسب مع category_id الخاص به فقط
            $posts = Post::where('category_id', $expert->category_id)
                ->with(['comments.userable', 'comments.replies.userable', 'likes'])
                ->get();
        } else {
            // المستخدم عادي: يجب أن يرى فقط البوستات التي هو صاحبها
            $posts = Post::where('user_id', $user->id)
                ->with(['comments.userable', 'comments.replies.userable', 'likes'])
                ->get();
        }

        // تنسيق البيانات للعرض
        return response()->json($posts->map(function ($post) {
            return [
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'body' => $post->body,
                    'category_id' => $post->category_id,
                    'section_id' => $post->section_id,
                    'author' => [
                        'id' => $post->user->id,
                        'userName' => $post->user->userName,
                    ],
                    'created_at' => $post->created_at,
                ],
                'comments' => $post->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'user_name' => optional($comment->userable)->userName ?? 'غير معروف',
                        'comment' => $comment->body,
                        'created_at' => $comment->created_at,
                        'replies' => $comment->replies->map(function ($reply) {
                            return [
                                'id' => $reply->id,
                                'user_name' => optional($reply->userable)->userName ?? 'غير معروف',
                                'comment' => $reply->body,
                                'created_at' => $reply->created_at,
                            ];
                        }),
                    ];
                }),
                'likes_count' => $post->likes->count(),
            ];
        }));
    }



    /**
     * إنشاء منشور جديد.
     */
    public function store(Request $request)
    {
        // التحقق من صحة البيانات المدخلة
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category_id' => 'required|integer|exists:categories,id',
            'section_id' => 'required|integer|exists:sections,id',
            'images' => 'nullable|array',
            'images.*' => 'string', // تأكد أن الصور عبارة عن مسارات نصية
        ]);

        // تحقق من تسجيل الدخول
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // سجل بيانات الطلب في الـ Log لسهولة تصحيح الأخطاء
        \Log::info('Request Data:', $request->all());

        // إنشاء المنشور
        $post = Post::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'body' => $request->body,
            'category_id' => $request->category_id,
            'section_id' => $request->section_id,
            'images' => $request->images ?? [],
        ]);

        // التحقق من الحفظ
        if (!$post) {
            return response()->json(['error' => 'Failed to create post'], 500);
        }

        // التحقق من الحفظ في قاعدة البيانات
        \Log::info('Post Created:', $post->toArray());

        // إعادة الرد مع المنشور الذي تم إنشاؤه
        return response()->json(['message' => 'Post created successfully', 'post' => $post]);
    }


    /**
     * عرض منشور معين مع تعليقاته وإعجاباته.
     */
//    public function show($id)
//    {
//        $post = Post::with('user', 'comments.replies', 'likes')->findOrFail($id);
//        return response()->json($post);
//    }

    /**
     * تحديث منشور.
     */
    public function update(Request $request, Post $post)
    {
        // استخدام الحارس الصحيح للمستخدم العادي
        $user = Auth::user();

        // التحقق من وجود المستخدم
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401); // إذا لم يكن هناك مستخدم مصادق عليه
        }

        // التحقق إذا كان المستخدم خبيرًا (باستعلام على جدول الخبراء)
        $isExpert = \App\Models\Expert::where('email', $user->email)->exists();
        if ($isExpert) {
            return response()->json(['message' => 'Experts cannot update posts.'], 403); // رفض التعديل إذا كان المستخدم خبير
        }

        // التحقق إذا كان المستخدم هو صاحب المنشور
        if ($user->id !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403); // رفض التعديل إذا لم يكن المستخدم هو صاحب المنشور
        }

        // التحقق من صحة المدخلات
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'section_id' => 'nullable|exists:sections,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // تحديث الصور
        $images = $post->images ?? [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('uploads/posts', 'public');
                $images[] = $path;
            }
        }

        // تحديث المنشور
        $post->update([
            'title' => $request->title,
            'body' => $request->body,
            'category_id' => $request->category_id,
            'section_id' => $request->section_id,
            'images' => $images,
        ]);

        return response()->json(['message' => 'Post updated successfully!', 'post' => $post]);
    }






    /**
     * حذف منشور.
     */
    public function destroy(Post $post)
    {
        // استخدام الحارس الصحيح للمستخدم العادي
        $user = Auth::user();

        // التحقق من وجود المستخدم
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401); // إذا لم يكن هناك مستخدم مصادق عليه
        }

        // التحقق إذا كان المستخدم خبيرًا (باستعلام على جدول الخبراء)
        $isExpert = \App\Models\Expert::where('email', $user->email)->exists();
        if ($isExpert) {
            return response()->json(['message' => 'Experts cannot delete posts.'], 403); // رفض الحذف إذا كان المستخدم خبير
        }

        // التحقق إذا كان المستخدم هو صاحب المنشور
        if ($user->id !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403); // رفض الحذف إذا لم يكن المستخدم هو صاحب المنشور
        }

        // حذف الصور من التخزين
        if ($post->images) {
            foreach ($post->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        // حذف المنشور
        $post->delete();

        return response()->json(['message' => 'Post deleted successfully!']);
    }

    /**
     * إضافة تعليق إلى منشور.
     */

    public function addComment(Request $request, $postId)
    {
        // جلب المستخدم من التوكن
        $user = auth()->user();

        // التحقق مما إذا كان المستخدم خبيرًا بناءً على جدول الخبراء
        $isExpert = Expert::where('id', $user->id)->exists();

        // تحديد نوع المستخدم بناءً على نتيجة البحث
        if ($isExpert) {
            $userable_type = Expert::class; // يربط التعليق مع جدول الخبراء
            $userable_id = $user->id;
        } else {
            $userable_type = User::class; // يربط التعليق مع جدول المستخدمين العاديين
            $userable_id = $user->id;
        }

        // إنشاء التعليق
        $comment = Comment::create([
            'post_id' => $request->post_id,
            'body' => $request->body,
            'parent_id' => $request->parent_id,
            'userable_id' => auth()->id(),
            'userable_type' => auth()->user()->getMorphClass(), // تأكد من هذا الحقل
        ]);


        return response()->json($comment, 201);
    }


    public function deleteComment($commentId)
    {
        // جلب المستخدم من التوكن
        $user = auth()->user();

        // البحث عن التعليق
        $comment = Comment::findOrFail($commentId);

        // التحقق مما إذا كان المستخدم هو صاحب التعليق
        if ($comment->userable_id !== $user->id || $comment->userable_type !== $user->getMorphClass()) {
            return response()->json(['message' => 'غير مسموح لك بحذف هذا التعليق.'], 403);
        }

        // حذف التعليق
        $comment->delete();

        return response()->json(['message' => 'تم حذف التعليق بنجاح.'], 200);
    }



//    public function updateComment(Request $request, $commentId)
//    {
//        // جلب المستخدم من التوكن
//        $user = auth()->user();
//
//        // البحث عن التعليق
//        $comment = Comment::findOrFail($commentId);
//
//        // التحقق مما إذا كان المستخدم هو صاحب التعليق
//        if ($comment->userable_id !== $user->id || $comment->userable_type !== $user->getMorphClass()) {
//            return response()->json(['message' => 'غير مسموح لك بتعديل هذا التعليق.'], 403);
//        }
//
//        // التحقق من أن حقل `body` موجود في الطلب
//        $request->validate([
//            'body' => 'required|string|max:1000'
//        ]);
//
//        // تحديث التعليق
//        $comment->update(['body' => $request->body]);
//
//        return response()->json(['message' => 'تم تعديل التعليق بنجاح.', 'comment' => $comment], 200);
//    }


    /**
     * تسجيل إعجاب على منشور.
     */
    public function like(Request $request, $postId)
    {
        // جلب المستخدم من التوكن
        $user = auth()->user();

        // التحقق مما إذا كان المستخدم خبيرًا بناءً على جدول الخبراء
        $isExpert = Expert::where('id', $user->id)->exists();

        // تحديد نوع المستخدم بناءً على نتيجة البحث
        if ($isExpert) {
            $liker_id = $user->id;
            $liker_type = 'expert'; // تحديد أن الـ "لايك" من خبير
        } else {
            $liker_id = $user->id;
            $liker_type = 'user'; // تحديد أن الـ "لايك" من مستخدم عادي
        }

        // التحقق إذا كان المستخدم قد أضاف الـ "لايك" مسبقًا على نفس المنشور
        $existingLike = Like::where('liker_id', $liker_id)
            ->where('liker_type', $liker_type)
            ->where('post_id', $postId)
            ->first();

        // إذا كان قد أضاف الـ "لايك" مسبقًا، نقوم بإزالته
        if ($existingLike) {
            $existingLike->delete();
            return response()->json(['message' => 'Like removed successfully'], 200);
        }

        // إضافة الـ "لايك" إذا لم يكن موجودًا
        $like = Like::create([
            'liker_id' => $liker_id,
            'liker_type' => $liker_type,
            'post_id' => $postId
        ]);

        return response()->json(['message' => 'Like added successfully', 'like' => $like], 201);
    }


    /**
     * استرجاع جميع تعليقات منشور معين.
     */
    public function updateComment(Request $request, $commentId)
    {
        // جلب المستخدم من التوكن
        $user = auth()->user();

        // البحث عن التعليق باستخدام الـ ID
        $comment = Comment::findOrFail($commentId);

        // التحقق من أن التعليق تابع للبوست الذي يرغب المستخدم في تعديله
        if ($comment->post_id != $request->post_id) {
            return response()->json(['message' => 'التعليق لا ينتمي لهذا البوست.'], 400);
        }

        // التحقق إذا كان المستخدم هو صاحب التعليق
        if ($comment->userable_id != $user->id) {
            return response()->json(['message' => 'لا يمكنك تعديل تعليق ليس لك.'], 403);
        }

        // تحديث التعليق
        $comment->update([
            'body' => $request->body,
        ]);

        return response()->json([
            'message' => 'تم تعديل التعليق بنجاح.',
            'comment' => $comment,
        ]);
    }

    /**
     * استرجاع جميع الإعجابات على منشور معين.
     */
    public function getLikes(Post $post)
    {
        return response()->json($post->likes()->with('user')->get());
    }
}
