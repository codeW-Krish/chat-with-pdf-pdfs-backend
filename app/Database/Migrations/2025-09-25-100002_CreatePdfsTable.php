<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePdfsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'pdf_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'user_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36, // UUID length
            ],
            'file_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'file_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
            ],
            'file_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 64, // SHA-256 hash length
            ],
            'file_size' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'page_count' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'uploaded_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'processing_status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'pending',
            ],
        ]);
        
        $this->forge->addKey('pdf_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pdfs', true);
    }

    public function down()
    {
        $this->forge->dropTable('pdfs', true);
    }
}
