<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Config\Cors as CorsConfig;

class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = new CorsConfig();
        $cors = $config->default;
        $origin = $request->getHeaderLine('Origin');

        // Handle preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = Services::response();
            
            // Check if origin is allowed
            if (in_array($origin, $cors['allowedOrigins'])) {
                $response->setHeader('Access-Control-Allow-Origin', $origin);
            }
            
            $response->setHeader('Access-Control-Allow-Methods', implode(', ', $cors['allowedMethods']));
            $response->setHeader('Access-Control-Allow-Headers', implode(', ', $cors['allowedHeaders']));
            $response->setHeader('Access-Control-Allow-Credentials', $cors['supportsCredentials'] ? 'true' : 'false');
            $response->setHeader('Access-Control-Max-Age', $cors['maxAge']);
            $response->setStatusCode(204);
            return $response;
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $config = new CorsConfig();
        $cors = $config->default;
        $origin = $request->getHeaderLine('Origin');

        // Check if origin is allowed
        if (in_array($origin, $cors['allowedOrigins'])) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $cors['allowedMethods']));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $cors['allowedHeaders']));
        $response->setHeader('Access-Control-Allow-Credentials', $cors['supportsCredentials'] ? 'true' : 'false');

        return $response;
    }
}
