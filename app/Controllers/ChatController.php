<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ChatSessionModel;
use App\Models\ChatMessageModel;
use App\Models\PdfModel;
use Config\Services;

class ChatController extends BaseController
{
    protected $chatSessionModel;
    protected $chatMessageModel;
    protected $pdfModel;
    
    public function __construct()
    {
        $this->chatSessionModel = new ChatSessionModel();
        $this->chatMessageModel = new ChatMessageModel();
        $this->pdfModel = new PdfModel();
        helper('text');
    }
    
    public function createSession()
    {
        try {
            $data = $this->request->getJSON(true);
            $userId = $this->request->user->user_id;
            
            $sessionName = $data['session_name'] ?? 'New Chat Session';
            $pdfIds = $data['pdf_ids'] ?? [];
            
            // Validate PDFs belong to user
            foreach ($pdfIds as $pdfId) {
                $pdf = $this->pdfModel->getPdfById($pdfId, $userId);
                if (!$pdf) {
                    return $this->response->setJSON([
                        'status' => 'error',
                        'message' => 'Invalid PDF ID or access denied'
                    ])->setStatusCode(400);
                }
            }
            
            // Create session
            $sessionId = $this->chatSessionModel->createSession($userId, $sessionName);
            if (!$sessionId) {
                throw new \Exception('Failed to create chat session');
            }
            
            // Add PDFs to session
            if (!empty($pdfIds)) {
                $this->chatSessionModel->addPdfsToSession($sessionId, $pdfIds);
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Chat session created successfully',
                'data' => [
                    'session_id' => $sessionId,
                    'session_name' => $sessionName
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Create session error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to create chat session'
            ])->setStatusCode(500);
        }
    }
    
    public function getSessions()
    {
        try {
            $userId = $this->request->user->user_id;
            $sessions = $this->chatSessionModel->getUserSessions($userId);
            
            // Get PDF count for each session
            foreach ($sessions as $session) {
                $session->pdf_count = count($this->chatSessionModel->getSessionPdfs($session->session_id));
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $sessions
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Get sessions error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to retrieve chat sessions'
            ])->setStatusCode(500);
        }
    }
    
    public function sendMessage()
    {
        try {
            $data = $this->request->getJSON(true);
            $userId = $this->request->user->user_id;
            
            $sessionId = $data['session_id'] ?? null;
            $message = $data['message'] ?? '';
            
            if (!$sessionId || !$message) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Session ID and message are required'
                ])->setStatusCode(400);
            }
            
            // Verify session belongs to user
            $session = $this->chatSessionModel->where('session_id', $sessionId)
                                             ->where('user_id', $userId)
                                             ->first();
            if (!$session) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid session or access denied'
                ])->setStatusCode(403);
            }
            
            // Get PDFs in this session
            $sessionPdfs = $this->chatSessionModel->getSessionPdfs($sessionId);
            $pdfIds = array_column($sessionPdfs, 'pdf_id');
            
            if (empty($pdfIds)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'No PDFs in this session'
                ])->setStatusCode(400);
            }
            
            // Save user message
            $this->chatMessageModel->addMessage($sessionId, 'user', $message);
            
            // Send to Python AI server for processing
            $aiResponse = $this->sendToAI($message, $pdfIds, $userId, $sessionId);
            
            // Save AI response
            $references = $aiResponse['references'] ?? [];
            $this->chatMessageModel->addMessage(
                $sessionId, 
                'ai', 
                $aiResponse['answer'], 
                $references
            );
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => [
                    'user_message' => $message,
                    'ai_response' => $aiResponse['answer'],
                    'references' => $references
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Send message error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to send message: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }
    
    public function getSessionMessages($sessionId)
    {
        try {
            $userId = $this->request->user->user_id;
            
            // Verify session belongs to user
            $session = $this->chatSessionModel->where('session_id', $sessionId)
                                             ->where('user_id', $userId)
                                             ->first();
            if (!$session) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid session or access denied'
                ])->setStatusCode(403);
            }
            
            $messages = $this->chatMessageModel->getSessionMessages($sessionId);
            
            // Decode references JSON
            foreach ($messages as $message) {
                if ($message->references) {
                    $message->references = json_decode($message->references, true);
                }
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => [
                    'session' => $session,
                    'messages' => $messages
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Get messages error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to retrieve messages'
            ])->setStatusCode(500);
        }
    }
    
    private function sendToAI($question, $pdfIds, $userId, $sessionId)
    {
        $pythonServerUrl = getenv('PYTHON_SERVER_URL') ?: 'http://localhost:5000';
        
        $postData = json_encode([
            'question' => $question,
            'pdf_ids' => $pdfIds,
            'user_id' => $userId,
            'session_id' => $sessionId
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $pythonServerUrl . '/chat',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('AI server error: ' . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($result['status'] !== 'success') {
            throw new \Exception('AI processing failed: ' . ($result['message'] ?? 'Unknown error'));
        }
        
        return $result;
    }
}