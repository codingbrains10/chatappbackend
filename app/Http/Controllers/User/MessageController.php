<?php

namespace App\Http\Controllers\User;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function storeMessage(Request $request){
        

        $validatedData = Validator::make($request->all(),[
            'sender_id' => 'required|integer',
            'receiver_id' => 'required|integer',
            'message' => 'required|string',
        ]);

        if($validatedData->fails()){
            return response()->json([
                'status' => false,
                'message' => $validatedData->errors(),
            ], 422);
        }

        $storedMessage = Message::create([
            'sender_id' => $request->sender_id,
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

       
        if($storedMessage){
            broadcast(new MessageSent($storedMessage));
            return response()->json([
                'status' => true,
                'data' => $storedMessage,
                'message' => 'Message sent successfully',
            ], 200);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'Failed to send message',
            ], 500);
        }
    }  

    public function getMessages(Request $request){

        $user1 = $request->query('sender_id');
        $user2 = $request->query('receiver_id');
        
        if (!$user1 || !$user2) {
            return response()->json(['success' => false, 'message' => 'User1 and User2 are required'], 400);
        }

        $messages = Message::where(function ($query) use ($user1, $user2) {
            $query->where('sender_id', $user1)
                ->where('receiver_id', $user2);
        })
            ->orWhere(function ($query) use ($user1, $user2) {
                $query->where('sender_id', $user2)
                    ->where('receiver_id', $user1);
            })
            ->orderBy('created_at', 'asc')
            ->get();
        // event(new MessageSent($messages));
        // Check if messages were found
        if ($messages->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No messages found'], 404);
        }

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    public function deleteMessage($messageId)
    {

        $loggedInUser = auth()->user()->id;

        if (!$loggedInUser) {
            return response()->json([
                'status' => false,
                'message' => 'User not logged in',
            ], 401);
        }

        $findedMessage = Message::find($messageId);
        if (!$findedMessage) {
            return response()->json([
                'status' => false,
                'message' => 'Message not found',
            ], 404);
        }
        $deletedMessage = Message::where('id', $messageId)
            ->where(function ($query) use ($loggedInUser) {
                $query->where('sender_id', $loggedInUser)
                    ->orWhere('receiver_id', $loggedInUser);
            })
            ->delete();

        if ($deletedMessage) {
            return response()->json([
                'status' => true,
                'message' => 'Message deleted successfully',
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete message or message not found',
            ], 404);
        }
    }

    public function markMessagesAsRead(Request $request)
    {
        $loggedInUser = auth()->user()->id;

        if (!$loggedInUser) {
            return response()->json([
                'status' => false,
                'message' => 'User not logged in',
            ], 401);
        }

        $messages = DB::table('messages')
            ->where('sender_id', $request->sender_id)
            ->where('receiver_id', $request->receiver_id)
            ->where('is_read', false)
            ->get();

        foreach ($messages as $message) {
            broadcast(new MessageRead($message->id, $request->sender_id)); // notify sender
        }

        DB::table('messages')
            ->whereIn('id', $messages->pluck('id'))
            ->update(['is_read' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Messages marked as read and broadcasted successfully',
        ], 200);
    }
}
