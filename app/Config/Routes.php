<?php

use CodeIgniter\Router\RouteCollection;
use App\Controllers\Auth;
use App\Controllers\PdfController;
use App\Controllers\ChatController;
use App\Controllers\Home;

/**
 * @var RouteCollection $routes
 */

// Public routes
$routes->get('public/health', function() {
    return service('response')->setJSON(['status' => 'healthy', 'service' => 'PHP Backend']);
});

$routes->get('/', [Home::class, 'index']);
$routes->get('public/test-python-quick', [PdfController::class, 'testPythonQuick']);

/**
 * =========================================
 * CORS PRE-FLIGHT OPTIONS HANDLERS
 * =========================================
 */
$routes->options('auth/(:any)', static function() {
    return service('response')->setStatusCode(204)
        ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->setHeader('Access-Control-Allow-Credentials', 'true')
        ->setBody('');
});

$routes->options('api/(:any)', static function() {
    return service('response')->setStatusCode(204)
        ->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->setHeader('Access-Control-Allow-Credentials', 'true')
        ->setBody('');
});

/**
 * =========================================
 * AUTH ROUTES (public)
 * =========================================
 */
$routes->group('', ['filter' => 'cors'], static function($routes) {
    $routes->post('auth/register', [Auth::class, 'register']);
    $routes->post('auth/login', [Auth::class, 'login']);
    $routes->post('auth/refresh', [Auth::class, 'refresh']);
});

/**
 * =========================================
 * PROTECTED API ROUTES (JWT required)
 * =========================================
 */
$routes->group('api', ['filter' => 'jwtauth'], static function($routes) {
    // PDF routes
    $routes->post('pdfs/upload', [PdfController::class, 'upload']);
    $routes->get('pdfs', [PdfController::class, 'getUserPdfs']);
    $routes->get('pdfs/(:segment)', [PdfController::class, 'getPdfStatus/$1']);
    $routes->get('pdfs/(:segment)/chunks', [PdfController::class, 'getPdfChunks/$1']);
    $routes->delete('pdfs/(:segment)', [PdfController::class, 'deletePdf/$1']);

    // Chat routes
    $routes->post('chat/sessions', [ChatController::class, 'createSession']);
    $routes->get('chat/sessions', [ChatController::class, 'getSessions']);
    $routes->get('chat/sessions/(:segment)/messages', [ChatController::class, 'getSessionMessages/$1']);
    $routes->post('chat/message', [ChatController::class, 'sendMessage']);
});
