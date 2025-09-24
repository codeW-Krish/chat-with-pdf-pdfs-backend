<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatSessionPdf extends Model
{ 
    protected $table = 'chat_sessions';
    protected $primaryKey = 'session_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $allowedFields = ['session_id', 'user_id', 'session_name', 'created_at'];
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

        public function getUserSessions($userId)
    {
        return $this->where('user_id', $userId)
                   ->orderBy('created_at', 'DESC')
                   ->findAll();
    }
    
    public function createSession($userId, $sessionName)
    {
        $sessionId = $this->generateUuid();
        $data = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'session_name' => $sessionName
        ];
        
        if ($this->insert($data)) {
            return $sessionId;
        }
        
        return false;
    }
    
    public function addPdfsToSession($sessionId, $pdfIds)
    {
        $db = \Config\Database::connect();
        $batchData = [];
        
        foreach ($pdfIds as $pdfId) {
            $batchData[] = [
                'session_id' => $sessionId,
                'pdf_id' => $pdfId
            ];
        }
        
        if (!empty($batchData)) {
            return $db->table('chat_session_pdfs')->insertBatch($batchData);
        }
        
        return true;
    }
    
    public function getSessionPdfs($sessionId)
    {
        $db = \Config\Database::connect();
        return $db->table('chat_session_pdfs csp')
                 ->select('p.*')
                 ->join('pdfs p', 'p.pdf_id = csp.pdf_id')
                 ->where('csp.session_id', $sessionId)
                 ->get()
                 ->getResult();
    }
    
    private function generateUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
