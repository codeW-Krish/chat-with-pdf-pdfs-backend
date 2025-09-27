<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePdfChunksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'chunk_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'pdf_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'user_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'chunk_index' => [
                'type' => 'INTEGER',
            ],
            'page_number' => [
                'type' => 'INTEGER',
            ],
            'start_char' => [
                'type' => 'INTEGER',
            ],
            'end_char' => [
                'type' => 'INTEGER',
            ],
            'chunk_text' => [
                'type' => 'TEXT',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('chunk_id', true);
        $this->forge->addKey(['pdf_id', 'chunk_index']);
        $this->forge->addForeignKey('pdf_id', 'pdfs', 'pdf_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pdf_chunks', true);
    }

    public function down()
    {
        $this->forge->dropTable('pdf_chunks', true);
    }
}
