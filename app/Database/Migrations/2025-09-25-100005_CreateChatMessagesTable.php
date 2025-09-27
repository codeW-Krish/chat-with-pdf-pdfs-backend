<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatMessagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'message_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'session_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'sender' => [
                'type' => 'VARCHAR',
                'constraint' => 10, // 'user' or 'ai'
            ],
            'message_text' => [
                'type' => 'TEXT',
            ],
            'references' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('message_id', true);
        $this->forge->addKey('session_id');
        $this->forge->addForeignKey('session_id', 'chat_sessions', 'session_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_messages', true);
    }

    public function down()
    {
        $this->forge->dropTable('chat_messages', true);
    }
}
