<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatSessionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'session_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'user_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'session_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('session_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_sessions', true);
    }

    public function down()
    {
        $this->forge->dropTable('chat_sessions', true);
    }
}
