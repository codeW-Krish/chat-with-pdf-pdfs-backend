<?php
namespace Config;

class JWT
{
    public $key;
    public $algorithm = 'HS256';
    public $expireTime = 3600; // 1 hour
    public $refreshExpireTime = 604800; // 7 days

    public function __construct(){
        $this->key = getenv('JWT_SECRET');
    }

}