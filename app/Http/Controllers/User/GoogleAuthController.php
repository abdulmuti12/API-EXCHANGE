<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Auth;

class GoogleAuthController extends BaseController
{
    private const GOOGLE_JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function redirectToGoogle()
    {
        $url = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        return response()->json(['url' => $url]);
    }

    public function handleGoogleCallback(\Illuminate\Http\Request $request)
    {
        $idToken = $request->input('id_token');
        if (!$idToken) {
            return $this->sendError(['auth' => ['Token not found.']], 'Token not found.', 400);
        }

        $googleUser = $this->verifyGoogleToken($idToken);
        if (!$googleUser) {
            return $this->sendError(['auth' => ['Google authentication failed. Please try again.']], 'Google authentication failed.', 401);
        }

        $user = User::where('google_id', $googleUser['sub'])->first();

        if (!$user) {
            $user = User::firstOrCreate(
                ['email' => $googleUser['email']],
                [
                    'name' => $googleUser['name'],
                    'google_id' => $googleUser['sub'],
                    'password' => bcrypt(str_shuffle('GoogleAuth_' . uniqid())),
                    'status' => 'active',
                ]
            );
        }

        if ($user->status !== 'active') {
            $message = $user->status === 'banned' ? 'Akun dibanned.' : 'Akun nonaktif.';
            return $this->sendError(['auth' => [$message]], 'Login failed.', 403);
        }

        $token = Auth::guard('user')->login($user);

        return $this->sendResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'address' => $user->address,
                'status' => $user->status,
            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('user')->factory()->getTTL() * 60,
        ], 'Google login successful.');
    }

    private function verifyGoogleToken($idToken)
    {
        try {
            // 获取 Google 的公钥
            $ch = curl_init(self::GOOGLE_JWKS_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $jwksResponse = @json_decode(curl_exec($ch), true);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($curlErr) {
                \Log::error('Google Auth: cURL error fetching JWKS - ' . $curlErr);
                return null;
            }
            if (!$jwksResponse || !isset($jwksResponse['keys'])) {
                \Log::error('Google Auth: Cannot fetch Google JWKS');
                return null;
            }

            // 用 firebase/php-jwt 内置的 JWK 解析 JWKS
            $keySet = JWK::parseKeySet($jwksResponse, 'RS256');

            // 解码并验证 token
            $decoded = JWT::decode($idToken, $keySet);
            $decodedArray = (array) $decoded;

            // 验证 issuer
            if ($decodedArray['iss'] !== 'accounts.google.com' && $decodedArray['iss'] !== 'https://accounts.google.com') {
                \Log::error('Google Auth: Invalid issuer, iss=' . ($decodedArray['iss'] ?? 'null'));
                return null;
            }

            // 验证 audience
            if ($decodedArray['aud'] !== env('GOOGLE_CLIENT_ID')) {
                \Log::error('Google Auth: Invalid audience, aud=' . ($decodedArray['aud'] ?? 'null') . ', expected=' . env('GOOGLE_CLIENT_ID'));
                return null;
            }

            \Log::info('Google Auth: Token verified successfully, email=' . ($decodedArray['email'] ?? 'null'));
            return $decodedArray;
        } catch (\Exception $e) {
            \Log::error('Google Auth: Token verification failed - ' . $e->getMessage());
            return null;
        }
    }
}
