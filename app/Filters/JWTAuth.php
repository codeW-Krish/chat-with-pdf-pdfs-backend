<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Config\JWT;
use Firebase\JWT\JWT as JWTLib;
use Firebase\JWT\Key;

class JWTAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Add CORS headers first
        $response = Services::response();
        $response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                 ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                 ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                 ->setHeader('Access-Control-Allow-Credentials', 'true');

        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            $response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                     ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                     ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                     ->setHeader('Access-Control-Allow-Credentials', 'true');
            return $response->setJSON([
                'status' => 'error',
                'message' => 'Authorization header required'
            ])->setStatusCode(401);
        }
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            
            try {
                $jwtConfig = new JWT();
                $decoded = JWTLib::decode($token, new Key($jwtConfig->key, $jwtConfig->algorithm));
                
                // Add user to request for use in controllers
                $request->user = $decoded;
                
            } catch (\Exception $e) {
                $response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                         ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                         ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                         ->setHeader('Access-Control-Allow-Credentials', 'true');
                return $response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ])->setStatusCode(401);
            }
        } else {
            $response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                     ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                     ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                     ->setHeader('Access-Control-Allow-Credentials', 'true');
            return $response->setJSON([
                'status' => 'error',
                'message' => 'Invalid authorization format'
            ])->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add CORS headers to response
        $response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
                 ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                 ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                 ->setHeader('Access-Control-Allow-Credentials', 'true');
        
        return $response;
    }
}
