<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'username' => 'required|string|between:2,100|unique:users',
            'email' => 'required|string|email|max:100|unique:users',
            'mobile' => 'required|numeric|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ]);

        if ($validation->fails()) {
            return response()->json(['status' => 'error', 'message' => $validation->errors()], 200);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => bcrypt($request->password),
            'email_verified_at' => Carbon::now(),
            'status' => 1,

        ]);
        $user->roles()->attach(Role::whereName('user')->first()->id);
        return $this->getRefreshedToken($request->email, $request->password);
        /*    $user->roles()->attach(Role::where('user')->first()->id);
            return response()->json(['status' => 'success', 'message' => 'User created successfully'], 200);*/
    }

    public function login(Request $request)
    {
        if (Auth::attempt(['username' => $request->username, 'password' => $request->password])) {
            $email = Auth::user()->email;
            return $this->getRefreshedToken($email, $request->password);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Invalid username or password'], 200);
        }
        /*
                $validation = Validator::make($request->all(), [
                    'username' => 'required|string',
                    'password' => 'required|string',
                    'remember_me' => 'boolean'
                ]);

                if ($validation->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $validation->errors()->first()
                    ], 200);
                }

                $credentials = request(['username', 'password']);
                if (!Auth::attempt($credentials)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized'
                    ], 200);
                }

                $user = $request->user();
                $tokenResult = $user->createToken('Personal Access Token');
                $token = $tokenResult->token;
                if ($request->remember_me) {
                    $token->expires_at = Carbon::now()->addWeeks(1);
                }
                $token->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'User logged in successfully',
                    'access_token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer',
                    'expires_at' => Carbon::parse(
                        $tokenResult->token->expires_at
                    )->toDateTimeString()
                ], 200);*/
    }

    public function getRefreshedToken($email, $password)
    {

        $verifyValue = app()->environment() == 'local' ? false : true;

        $response = Http::withOptions([
            'verify' => $verifyValue,
        ])->post(config('app.url') . '/oauth/token', [
            'grant_type' => 'password',
            'client_id' => config('passport.personal_access_client.id'),
            'client_secret' => config('passport.personal_access_client.secret'),
            'username' => $email,
            'password' => $password,
            'scope' => '*',
        ]);

        return response()->json($response->json(), 200);
    }

    public function refresh_token(Request $request)
    {
        try {
            $refresh_token = $request->header('RefreshTokenCode');

            $verifyValue = app()->environment() == 'local' ? false : true;

            $response = Http::withOptions([
                'verify' => $verifyValue,
            ])->post(config('app.url') . '/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id' => config('passport.personal_access_client.id'),
                'client_secret' => config('passport.personal_access_client.secret'),
                'scope' => '*',
            ]);

            return response()->json($response->json(), 200);
        } catch (\Exception $e) {
            return response()->json('Unauthorized', 200);
        }

    }

}
