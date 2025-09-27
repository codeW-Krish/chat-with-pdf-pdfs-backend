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

        // Handle preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = Services::response();
            $response->setHeader('Access-Control-Allow-Origin', implode(', ', $cors['allowedOrigins']));
            $response->setHeader('Access-Control-Allow-Methods', implode(', ', $cors['allowedMethods']));
            $response->setHeader('Access-Control-Allow-Headers', implode(', ', $cors['allowedHeaders']));
            $response->setHeader('Access-Control-Allow-Credentials', $cors['supportsCredentials'] ? 'true' : 'false');
            $response->setHeader('Access-Control-Max-Age', $cors['maxAge']);
            $response->setStatusCode(204);
            return $response;
        }

        // For normal requests, set CORS headers (after filter will also set them)
        $response = Services::response();
        $response->setHeader('Access-Control-Allow-Origin', implode(', ', $cors['allowedOrigins']));
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $cors['allowedMethods']));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $cors['allowedHeaders']));
        $response->setHeader('Access-Control-Allow-Credentials', $cors['supportsCredentials'] ? 'true' : 'false');

        // Continue processing
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Ensure CORS headers are present on the response
        $config = new CorsConfig();
        $cors = $config->default;

        $response->setHeader('Access-Control-Allow-Origin', implode(', ', $cors['allowedOrigins']));
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $cors['allowedMethods']));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $cors['allowedHeaders']));
        $response->setHeader('Access-Control-Allow-Credentials', $cors['supportsCredentials'] ? 'true' : 'false');

        return $response;
    }
}
