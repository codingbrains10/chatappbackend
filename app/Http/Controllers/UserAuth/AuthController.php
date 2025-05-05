<?php

namespace App\Http\Controllers\UserAuth;

use App\Http\Controllers\Controller;
use App\Mail\sendOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Stringable;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validate the request data
        $validation = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'number' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:4',
        ]);
      
        if($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        // Create a new user
        $user = DB::table('users')->insert([
            'name' => $request->name,
            'number' => $request->number,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (!$user) {
            return response()->json(['error' => 'User registration failed'], 500);
        }else {
            return response()->json(['message' => 'User registered successfully'], 201);
        }
    }

    public function uploadProfileImage(Request $request, $userId)
    {
        $findUser = DB::table('users')->where('id', $userId)->first();

        if (!$findUser) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validation = Validator::make($request->all(), [
            'profileImage' => 'required|image|mimes:jpg,jpeg,png',
        ]);

        if ($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        // Save the image
        if ($request->hasFile('profileImage')) {
            $image = $request->file('profileImage');
            $filename = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/'), $filename);

            $imagePath = 'uploads/' . $filename;

            // Optionally delete old image if exists
            $oldImage = DB::table('users')->where('id', $userId)->value('profileImage');
            if ($oldImage && file_exists(public_path($oldImage))) {
                unlink(public_path($oldImage));
            }

            // Update profile_image using Query Builder
            DB::table('users')
                ->where('id', $userId)
                ->update(['profileImage' => $filename]);

            return response()->json([
                'message' => 'Profile image updated successfully.',
                'profileImage' => $filename,
            ]);
        } else {
            return response()->json(['error' => 'No image file found'], 422);
        }
    }


    public function login(Request $request)
    {
        // Validate the request data
        $validation = Validator::make($request->all(),[
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:4',
        ]);

        if($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        // Check if the user exists
        $userRecord  = DB::table('users')->where('email', $request->email)->first();

        if (!$userRecord  || !Hash::check($request->password, $userRecord->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = User::find($userRecord->id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Generate a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the token
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function forgotPassword(Request $request){
        
        $validation = Validator::make($request->all(),[
            'email' => 'required|string|email|max:255',
        ]);

        if($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        // Check if the user exists
        $user = DB::table('users')->where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'Enter registered email id'], 404);
        }
        $token = Str::random(6);
      
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $token, 'created_at' => now()]
        );

        $resetLink = "http://localhost:5173/reset-password?token=$token&email=" . urlencode($request->email);
        Mail::to($request->email)->send(new sendOtpMail($resetLink));
        
        return response()->json([
            'token' => $resetLink,
            'email' => $request->email,
            'message' => 'Reset link has been sent to your email.'], 200);
    }

    public function resetPassword(Request $request){
        $validation = Validator::make($request->all(), [
            'password' => 'required|string|min:4',
        ]);

        if($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        // Check if the user exists
        $user = DB::table('users')->where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        // Delete the OTP record from the database
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully'], 200);
    }
}
