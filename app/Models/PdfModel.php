<?php

namespace App\Models;

use CodeIgniter\Model;

class PdfModel extends Model
{
       protected $table = 'pdfs';
    protected $primaryKey = 'pdf_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $allowedFields = [
        'pdf_id', 'user_id', 'file_name', 'file_path', 
        'file_hash', 'file_size', 'page_count', 'uploaded_at', 'processing_status'
    ];
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;


    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
     protected $useTimestamps = true;
    protected $createdField = 'uploaded_at';
    protected $updatedField = '';

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

    public function getUserPdfs($userId){
        return $this->where('user_id', $userId)
                   ->orderBy('uploaded_at', 'DESC')
                   ->findAll();
    }
    
    public function updateProcessingStatus($pdfId, $status, $pageCount = null){
        $data = ['processing_status' => $status];
        if ($pageCount !== null) {
            $data['page_count'] = $pageCount;
        }
        
        return $this->update($pdfId, $data);
    }
    
    public function getPdfById($pdfId, $userId = null){
        $query = $this->where('pdf_id', $pdfId);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        return $query->first();
    }
}
