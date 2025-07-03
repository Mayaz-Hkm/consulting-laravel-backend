<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Expert;
use App\Models\User;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // بدء محادثة جديدة بين المستخدم والخبير
    public function openChat(Request $request, $other_user_id)
    {
        $user = Auth::user(); // المستخدم المصادق عليه

        // التحقق من نوع المستخدم وجلب المستخدم الآخر
        if ($user instanceof User) {
            $other_user = Expert::find($other_user_id);
        } elseif ($user instanceof Expert) {
            $other_user = User::find($other_user_id);
        } else {
            return response()->json(['message' => 'Invalid user type.'], 403);
        }

        if (!$other_user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // البحث عن محادثة سابقة بين المستخدم والخبير
        $chat = Chat::where(function ($query) use ($user, $other_user) {
            $query->where('user_id', $user instanceof User ? $user->id : $other_user->id)
                ->where('expert_id', $user instanceof Expert ? $user->id : $other_user->id);
        })->first();

        if (!$chat) {
            // إنشاء محادثة جديدة إذا لم تكن موجودة مسبقًا
            $chat = Chat::create([
                'user_id' => $user instanceof User ? $user->id : $other_user->id,
                'expert_id' => $user instanceof Expert ? $user->id : $other_user->id,
            ]);
        }

        return response()->json(['chat' => $chat]);
    }

    // إرسال رسالة بين المستخدم والخبير
    public function sendMessage(Request $request, $chat_id)
    {
        $request->validate(['message' => 'required|string']);

        $user = Auth::user(); // المستخدم المصادق عليه
        $chat = Chat::find($chat_id);

        if (!$chat) {
            return response()->json(['message' => 'Chat not found.'], 404);
        }

        // تحديد المرسل والمستقبل
        $receiver_id = ($chat->user_id == $user->id) ? $chat->expert_id : $chat->user_id;
        $receiver_type = ($chat->user_id == $user->id) ? Expert::class : User::class;

        $message = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => $user->id,
            'sender_type' => get_class($user),
            'receiver_id' => $receiver_id,
            'receiver_type' => $receiver_type,
            'message' => $request->message,
        ]);

        return response()->json(['message' => $message]);
    }

    // استعراض جميع الرسائل في محادثة معينة
    public function showMessages($chat_id)
    {
        $chat = Chat::find($chat_id);
        if (!$chat) {
            return response()->json(['message' => 'Chat not found.'], 404);
        }

        $messages = Message::where('chat_id', $chat->id)->get();
        return response()->json(['messages' => $messages]);
    }

    // استعراض جميع المحادثات الخاصة بالمستخدم الحالي
    public function showChats()
    {
        $user = Auth::user();
        $chats = Chat::where('user_id', $user->id)
            ->orWhere('expert_id', $user->id)
            ->get();

        $chatsData = $chats->map(function ($chat) use ($user) {
            if ($chat->user_id == $user->id) {
                $other = Expert::find($chat->expert_id);
                $type = 'Expert';
            } else {
                $other = User::find($chat->user_id);
                $type = 'User';
            }

            return [
                'chat_id' => $chat->id,
                'other_user_id' => $other?->id,
                'other_user_name' => $other?->userName ?? 'غير معروف',
                'other_user_type' => $type,
            ];
        });

        return response()->json(['chats' => $chatsData]);
    }

    // حذف محادثة
    public function deleteChat($chat_id)
    {
        $user = Auth::user();
        $chat = Chat::find($chat_id);

        if (!$chat || ($chat->user_id !== $user->id && $chat->expert_id !== $user->id)) {
            return response()->json(['message' => 'Chat not found or unauthorized.'], 403);
        }

        $chat->messages()->delete(); // حذف جميع الرسائل المرتبطة
        $chat->delete(); // حذف المحادثة

        return response()->json(['message' => 'Chat deleted successfully.']);
    }
}
