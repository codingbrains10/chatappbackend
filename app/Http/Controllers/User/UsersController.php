<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{
    public function getAllUsers(){
        
        $loggedInUser = auth()->user()->id;
        $users = DB::table('users')
            ->where('id', '!=', $loggedInUser)
            ->get();

        if($users->isEmpty()){
            return response()->json([
                'status' => false,
                'message' => 'No users found',
            ]);
        }else{
            return response()->json([
                'status' => true,
                'message' => 'Users found',
                'data' => $users
            ]);
        }
    }

    public function getMessageByUserId($userid){
        return response()->json([
            'status' => true,
            'message' => 'User found',
            'data' => $userid
        ]);
    }

}
