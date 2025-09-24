<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UserModel;
use Config\JWT;
use Config\Services;
use Firebase\JWT\JWT as JWTLib;
use Firebase\JWT\Key;
use App\Models\UserRefreshTokenModel;

class Auth extends BaseController
{
    protected $jwt;
    protected $userModel;
    protected $refreshTokenModel;

    public function __construct()
    {
        $this->jwt = new JWT();
        $this->userModel = new UserModel();
        $this->refreshTokenModel = new UserRefreshTokenModel();
        helper('text');
    }

    // ---------- QUICK CORS FIX ----------
    // private function handleCors()
    // {
    //     header("Access-Control-Allow-Origin: http://localhost:3000");
    //     header("Access-Control-Allow-Credentials: true");
    //     header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    //     header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

    //     if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    //         http_response_code(200);
    //         exit;
    //     }
    // }
    // -----------------------------------

    public function register()
    {
        // $this->handleCors(); // Apply CORS headers

        $data = $this->request->getJSON(true);

        // Validation
        $validation = Services::validation();
        $validation->setRules([
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[8]',
            'name' => 'required'
        ]);

        if (!$validation->run($data)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validation->getErrors()
            ])->setStatusCode(400);
        }

        // Create user
        $userData = [
            'user_id' => $this->generateUuid(),
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'name' => $data['name']
        ];

        $this->userModel->insert($userData);

        // Generate tokens
        $tokens = $this->generateTokens($userData['user_id']);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'tokens' => $tokens,
                'user' => [
                    'user_id' => $userData['user_id'],
                    'email' => $userData['email'],
                    'name' => $userData['name']
                ]
            ]
        ]);
    }

    public function login()
    {
        // $this->handleCors(); // Apply CORS headers

        $data = $this->request->getJSON(true);

        $user = $this->userModel->where('email', $data['email'])->first();

        if (!$user || !password_verify($data['password'], $user->password_hash)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid email or password'
            ])->setStatusCode(401);
        }

        $tokens = $this->generateTokens($user->user_id);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'tokens' => $tokens,
                'user' => [
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'name' => $user->name
                ]
            ]
        ]);
    }

    public function logout()
    {
        // $this->handleCors(); // Apply CORS headers

        $data = $this->request->getJSON(true);
        $refreshToken = $data['refresh_token'] ?? null;

        if (!$refreshToken) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Refresh token required'
            ])->setStatusCode(400);
        }

        $this->refreshTokenModel->where('refresh_token', $refreshToken)->delete();

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    public function refresh()
    {
        // $this->handleCors(); // Apply CORS headers

        $data = $this->request->getJSON(true);
        $refreshToken = $data['refresh_token'] ?? null;

        if (!$refreshToken) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Refresh token required'
            ])->setStatusCode(400);
        }

        try {
            $decoded = JWTLib::decode($refreshToken, new Key($this->jwt->key, $this->jwt->algorithm));
            $userId = $decoded->user_id;

            $stored = $this->refreshTokenModel
                ->where('user_id', $userId)
                ->where('refresh_token', $refreshToken)
                ->first();

            if (!$stored || strtotime($stored['expires_at']) < time()) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Refresh token invalid or expired'
                ])->setStatusCode(401);
            }

            $this->refreshTokenModel->delete($stored['token_id']);

            $tokens = $this->generateTokens($userId);

            return $this->response->setJSON([
                'status' => 'success',
                'data' => ['tokens' => $tokens]
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid refresh token'
            ])->setStatusCode(401);
        }
    }

    private function generateTokens($userId)
    {
        $payload = [
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + $this->jwt->expireTime
        ];

        $refreshPayload = [
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + $this->jwt->refreshExpireTime
        ];

        $accessToken = JWTLib::encode($payload, $this->jwt->key, $this->jwt->algorithm);
        $refreshToken = JWTLib::encode($refreshPayload, $this->jwt->key, $this->jwt->algorithm);

        $this->refreshTokenModel->insert([
            'token_id' => $this->generateUuid(),
            'user_id' => $userId,
            'refresh_token' => $refreshToken,
            'user_agent' => $this->request->getUserAgent()->getAgentString(),
            'ip_address' => $this->request->getIPAddress(),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->jwt->refreshExpireTime),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->jwt->expireTime
        ];
    }

    private function generateUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
