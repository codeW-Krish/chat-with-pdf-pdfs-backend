<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserRefreshTokensTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'token_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'user_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
'refresh_token' => [
    'type' => 'VARCHAR',
    'constraint' => 512,
],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45, // Supports IPv6
            ],
            'expires_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('token_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('refresh_token');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
$this->forge->createTable('user_refresh_tokens', true);
    }

    public function down()
    {
$this->forge->dropTable('user_refresh_tokens', true);
    }
}
