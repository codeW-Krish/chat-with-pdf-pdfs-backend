<?php

namespace App\Models;

use CodeIgniter\Model;

class PdfChunkModel extends Model
{
    protected $table = 'pdf_chunks';
    protected $primaryKey = 'chunk_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $allowedFields = [
        'chunk_id', 'pdf_id', 'user_id', 'chunk_index', 
        'page_number', 'start_char', 'end_char', 'chunk_text', 'created_at'
    ];
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function getPdfChunks($pdfId){
        return $this->where('pdf_id', $pdfId)
                   ->orderBy('page_number', 'ASC')
                   ->orderBy('chunk_index', 'ASC')
                   ->findAll();
    }
    
    public function getChunkById($chunkId){
        return $this->where('chunk_id', $chunkId)->first();
    }
}
