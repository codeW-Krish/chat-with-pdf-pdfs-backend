<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatSessionPdfsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'session_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'pdf_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
        ]);
        
        $this->forge->addKey(['session_id', 'pdf_id']);
        $this->forge->addForeignKey('session_id', 'chat_sessions', 'session_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('pdf_id', 'pdfs', 'pdf_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_session_pdfs', true);
    }

    public function down()
    {
        $this->forge->dropTable('chat_session_pdfs', true);
    }
}
