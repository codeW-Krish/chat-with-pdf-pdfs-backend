<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatMessageModel extends Model
{
    protected $table = 'chat_messages';
    protected $primaryKey = 'message_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $allowedFields = ['message_id', 'session_id', 'sender', 'message_text', 'references_data', 'created_at'];
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

    public function getSessionMessages($sessionId, $limit = 50){
        return $this->where('session_id', $sessionId)
                   ->orderBy('created_at', 'ASC')
                   ->limit($limit)
                   ->findAll();
    }

    public function getLastMessages($sessionId, $limit = 50){
        return $this->where('session_id', $sessionId)
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->findAll();
    }
    
    public function addMessage($sessionId, $sender, $messageText, $references = null){
        $messageId = $this->generateUuid();
        $data = [
            'message_id' => $messageId,
            'session_id' => $sessionId,
            'sender' => $sender,
            'message_text' => $messageText,
            'references_data' => $references ? json_encode($references) : null
        ];
        
        if ($this->insert($data)) {
            return $messageId;
        }
        
        return false;
    }
    
    private function generateUuid(){
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

}
