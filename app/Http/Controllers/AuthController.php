<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Lang;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'full_name' => 'required|min:3',
            'password' => 'required|min:7',
            'confirm_password' => 'required|min:7'
        ]);

        if ($validator->fails()) {
            return $this->returnError($validator->errors()->first(),400);
        }

        try {

            if($request->password !== $request->confirm_password){
                return $this->returnError('Password and Confirm Password do not match',400);
            }

            $hashed_password = Hash::make($request->password);

            $user = User::create([
                'email' => $request->email,
                'full_name' => $request->full_name,
                'password' => $hashed_password,
            ]);

            if (!($token = Auth::setTTL(50000)->attempt(['email' => $request->email, 'password' => $request->password]))) {
                return response()->json(
                    [
                        'success' => true,
                        'msg' => 'User created, error authenticating the user afterwards',
                    ],
                    201
                );
            }

            return response()->json([
                'success' => true,
                'msg' => 'user successfully registered',
                'data' => [
                    'token' => $token
                ]
            ]);
        } catch (\Exception $e) {
            return $this->returnError($e,422);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnError($validator->errors()->first(),400);
        }

        if (!($token = Auth::setTTL(50000)->attempt(['email' => $request->email, 'password' => $request->password]))) {
            return $this->returnError('Invalid credentials',401);
        }

        return $this->respondWithToken($token);
    }

    public function logout()
    {
        if (!Auth::user()) {
            return $this->returnError('Cannot validate or find user',404);
        }
        Auth::logout();

        return response()->json([
            'success' => true,
            'msg' => 'successfully logged user out',
        ]);
    }

    public function me()
    {
        $user = Auth::user();

        if ($user) {
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        }
        return $this->returnError('Unable to fetch user details',422);
    }
}