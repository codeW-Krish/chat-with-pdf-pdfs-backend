<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class Home extends BaseController
{
    public function index(): string
    {
        $db = \Config\Database::connect();
        $tables = $db->listTables();

        $output = "<h3>Tables in Database:</h3><pre>" . print_r($tables, true) . "</pre>";

        return $output;
    }
} 
