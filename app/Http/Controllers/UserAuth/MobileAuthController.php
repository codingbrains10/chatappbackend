<?php

namespace App\Http\Controllers\UserAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobileAuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        // Validate the request data
        $validation = Validator::make($request->all(), [
            'country_code' => 'required|string|max:255',
            'number' => 'required|string|max:255',
        ]);

        if ($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        
        $country_code = $request->country_code;
        $number = $request->number;
        $otp = rand(100000, 999999);
        $full_number = $country_code . $number;
        
        // Check if user exists
        $existingUser = DB::table('users')->where('number', $full_number)->first();


        if ($existingUser) {
            // Update existing user
            DB::table('users')->where('number', $full_number)->update([
                'otp' => $otp,
                'otp_verified' => false,
                'updated_at' => Carbon::now()
            ]);
            $user_id = $existingUser->id;
        } else {
            // Insert new user
            $user_id = DB::table('users')->insertGetId([
                'number' => $full_number,
                'otp' => $otp,
                'otp_verified' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // Send OTP
        try {
            // $message = "Your OTP is: " . $otp;
            $message = "Ankit Your OTP code for login is {$otp}. It is valid for 1 minute. Do not share this code with anyone.";


            $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
            $messageInstance = $twilio->messages->create(
                $full_number,
                [
                    'from' => env('TWILIO_PHONE_NUMBER'),
                    'body' => $message
                ]
            );

            if ($messageInstance->sid) {
                Log::info("SMS Sent Successfully: " . $messageInstance->sid);

                return response()->json([
                    'status' => true,
                    'message' => "OTP sent successfully",
                    'user' => [
                        'id' => $user_id,
                        'number' => $number,
                    ],
                ], 200);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'OTP sending failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'status' => false,
            'message' => "Failed to send OTP"
        ], 500);
    }

    public function verifyOtp(Request $request)
    {
        // Validate the request data
        $validation = Validator::make($request->all(), [
            'id' => 'required|integer',
            'otp' => 'required|string|max:6',
        ]);

        if ($validation->fails()) {
            return response()->json(['error' => $validation->errors()], 422);
        }

        $user_id = $request->id;
        $otp = $request->otp;

        // Check if user exists and OTP is valid
        $user = DB::table('users')->where('id', $user_id)->where('otp', $otp)->first();

        if ($user) {
            // Update OTP status
            DB::table('users')->where('id', $user_id)->update([
                'otp_verified' => true,
                'updated_at' => Carbon::now()
            ]);

            return response()->json([
                'status' => true,
                'message' => "OTP verified successfully",
                'user' => [
                    'id' => $user_id,
                    'number' => $user->number,
                ],
            ], 200);
        }

        return response()->json(['error' => 'Invalid OTP'], 401);
    }
}
