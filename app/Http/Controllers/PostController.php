<?php

namespace App\Http\Controllers;

use App\Models\Expert;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class PostController extends Controller
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



    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($user->type === 'expert') {
            // المستخدم خبير
            $posts = Post::where('category_id', $user->category_id)
                ->with(['comments.userable', 'comments.replies.userable', 'likes', 'user'])
                ->get();
        } else {
            // المستخدم عادي
            $posts = Post::where('user_id', $user->id)
                ->with(['comments.userable', 'comments.replies.userable', 'likes', 'user'])
                ->get();
        }

        return response()->json($posts->map(function ($post) {
            return [
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'body' => $post->body,
                    'category_id' => $post->category_id,
                    'section_id' => $post->section_id,
                    'author' => [
                        'id' => optional($post->user)->id,
                        'userName' => optional($post->user)->userName,
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
        // الحصول على المستخدم المصادق عليه
        $user = $this->getAuthenticatedUser();

        // السماح فقط للمستخدمين (وليس الخبراء) بإنشاء المنشورات
        if (!$user || $user->type !== 'user') {
            return response()->json(['error' => 'Unauthorized. Only users can create posts.'], 401);
        }

        // التحقق من صحة البيانات
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category_id' => 'required|integer|exists:categories,id',
            'section_id' => 'required|integer|exists:sections,id',
            'images' => 'nullable|array',
            'images.*' => 'string', // تأكد أن الصور عبارة عن مسارات نصية
        ]);

        // سجل بيانات الطلب في الـ Log
        \Log::info('Post Store Request Data:', $request->all());

        // إنشاء المنشور
        $post = Post::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'body' => $request->body,
            'category_id' => $request->category_id,
            'section_id' => $request->section_id,
            'images' => $request->images ?? [],
        ]);

        // التحقق من نجاح الإنشاء
        if (!$post) {
            return response()->json(['error' => 'Failed to create post'], 500);
        }

        // سجل بيانات المنشور في الـ Log
        \Log::info('Post Created:', $post->toArray());

        // الرد الناجح
        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post
        ], 201);
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
        // الحصول على المستخدم المصادق عليه من خلال MultipleMiddleware
        $user = $this->getAuthenticatedUser();

        // التحقق من وجود المستخدم
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // السماح فقط للمستخدمين (وليس الخبراء) بالتعديل
        if ($user->type !== 'user') {
            return response()->json(['message' => 'Experts cannot update posts.'], 403);
        }

        // التحقق أن المستخدم هو صاحب المنشور
        if ($user->id !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // التحقق من صحة البيانات المدخلة
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
        // الحصول على المستخدم المصادق عليه من خلال MultipleMiddleware
        $user = $this->getAuthenticatedUser();

        // التحقق من وجود المستخدم
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // السماح فقط للمستخدمين (وليس الخبراء) بالحذف
        if ($user->type !== 'user') {
            return response()->json(['message' => 'Experts cannot delete posts.'], 403);
        }

        // التحقق أن المستخدم هو صاحب المنشور
        if ($user->id !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
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
        // الحصول على المستخدم من الحراس المتعددين
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // التحقق من صحة البيانات
        $request->validate([
            'body' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        // التأكد من وجود المنشور
        $post = Post::findOrFail($postId);

        // إنشاء التعليق
        $comment = Comment::create([
            'post_id' => $post->id,
            'body' => $request->body,
            'parent_id' => $request->parent_id,
            'userable_id' => $user->id,
            'userable_type' => $user->getMorphClass(),
        ]);

        return response()->json($comment, 201);
    }

    public function deleteComment($commentId)
    {
        // الحصول على المستخدم من الحراس المتعددين
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // البحث عن التعليق
        $comment = Comment::findOrFail($commentId);

        // التحقق من ملكية التعليق
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
        $user = $this->getAuthenticatedUser(); // دالتك المخصصة

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // تحديد الـ type من الـ morph class
        $liker_id = $user->id;
        $liker_type = $user->getMorphClass(); // مثل: App\Models\User أو App\Models\Expert

        // التحقق إذا كان المستخدم قد أضاف الـ "لايك" مسبقًا على نفس المنشور
        $existingLike = Like::where('liker_id', $liker_id)
            ->where('liker_type', $liker_type)
            ->where('post_id', $postId)
            ->first();

        if ($existingLike) {
            $existingLike->delete();
            return response()->json(['message' => 'Like removed successfully'], 200);
        }

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
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $comment = Comment::findOrFail($commentId);

        if ($comment->post_id != $request->post_id) {
            return response()->json(['message' => 'التعليق لا ينتمي لهذا البوست.'], 400);
        }

        if ($comment->userable_id != $user->id || $comment->userable_type !== $user->getMorphClass()) {
            return response()->json(['message' => 'لا يمكنك تعديل تعليق ليس لك.'], 403);
        }

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
