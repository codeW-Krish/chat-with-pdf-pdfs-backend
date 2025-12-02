<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ChatSessionPdf;
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
        $this->chatSessionModel = new ChatSessionPdf();
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
        log_message('info', 'Getting sessions for user: ' . $userId);
        
$sessions = $this->chatSessionModel->getUserSessions($userId) ?: [];
log_message('info', 'Retrieved ' . count($sessions) . ' sessions');
        
        // Get PDF count for each session
        foreach ($sessions as $session) {
            try {
                if (empty($session->session_id)) {
                    log_message('error', 'Session ID is empty for session: ' . json_encode($session));
                    $session->pdf_count = 0;
                    continue;
                }
                
                $pdfs = $this->chatSessionModel->getSessionPdfs($session->session_id);
                $session->pdf_count = count($pdfs);
                log_message('info', 'Session ' . $session->session_id . ' has ' . $session->pdf_count . ' PDFs');
            } catch (\Exception $e) {
                log_message('error', 'Error getting PDFs for session ' . $session->session_id . ': ' . $e->getMessage());
                $session->pdf_count = 0;
            }
        }
        
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $sessions
        ]);
        
    } catch (\Exception $e) {
        log_message('error', 'Get sessions error: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in ' . $e->getFile());
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Failed to retrieve chat sessions: ' . $e->getMessage()
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
            $requestedPdfIds = $data['pdf_ids'] ?? null; // Optional: specific PDFs to use
            
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
            $allSessionPdfIds = array_column($sessionPdfs, 'pdf_id');
            
            if (empty($allSessionPdfIds)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'No PDFs in this session'
                ])->setStatusCode(400);
            }

            // Filter PDFs if specific IDs requested
            $pdfIds = [];
            if ($requestedPdfIds !== null && is_array($requestedPdfIds)) {
                // Only use requested IDs that are actually in the session
                $pdfIds = array_intersect($requestedPdfIds, $allSessionPdfIds);
                
                // If intersection is empty but user requested IDs, it means invalid IDs were sent
                // However, for better UX, if they deselected everything, we might want to handle that.
                // But usually "chat with 0 docs" is just general chat or error. 
                // Let's allow it but the AI might complain or just answer from general knowledge if supported.
                // For now, if result is empty, we can either error or proceed with empty list.
                // Let's proceed with empty list so AI knows no context is provided.
            } else {
                // Default to all session PDFs
                $pdfIds = $allSessionPdfIds;
            }
            
            // Save user message
            $this->chatMessageModel->addMessage($sessionId, 'user', $message);
            
            // Get conversation history for context
            $conversationHistory = $this->getConversationHistory($sessionId);
            log_message('info', 'Conversation history length: ' . count($conversationHistory));
            
            // Send to Python AI server for processing
            $aiResponse = $this->sendToAI($message, $pdfIds, $userId, $sessionId, $conversationHistory);
            log_message('info', 'AI Response received: ' . json_encode($aiResponse));
            
            // Save AI response
            $aiData = $aiResponse['data'] ?? $aiResponse; // Handle both response formats
            $references = $aiData['references'] ?? [];
            $aiAnswer = $aiData['answer'] ?? 'No response generated';
            $suggestedQuestions = $aiData['suggested_questions'] ?? [];
            
            log_message('info', 'AI Answer: ' . $aiAnswer);
            log_message('info', 'References count: ' . count($references));
            
            $this->chatMessageModel->addMessage(
                $sessionId, 
                'ai', 
                $aiAnswer, 
                $references
            );
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => [
                    'user_message' => $message,
                    'ai_response' => $aiAnswer,
                    'references' => $references,
                    'suggested_questions' => $suggestedQuestions
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

    public function generateSummary()
    {
        try {
            $data = $this->request->getJSON(true);
            $userId = $this->request->user->user_id;
            $sessionId = $data['session_id'] ?? null;
            
            if (!$sessionId) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Session ID required'
                ])->setStatusCode(400);
            }
            
            // Verify session
            $session = $this->chatSessionModel->where('session_id', $sessionId)
                                             ->where('user_id', $userId)
                                             ->first();
            if (!$session) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid session'
                ])->setStatusCode(403);
            }
            
            // Get PDFs
            $sessionPdfs = $this->chatSessionModel->getSessionPdfs($sessionId);
            $pdfIds = array_column($sessionPdfs, 'pdf_id');
            
            if (empty($pdfIds)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'No PDFs to summarize'
                ])->setStatusCode(400);
            }
            
            // Call Python service for summary
            $pythonServerUrl = getenv('PYTHON_SERVER_URL') ?: 'http://localhost:5000';
            
            $postData = json_encode([
                'pdf_ids' => $pdfIds,
                'user_id' => $userId
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $pythonServerUrl . '/summarize',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new \Exception('AI server error during summary generation');
            }
            
            $result = json_decode($response, true);
            $summary = $result['summary'] ?? 'Could not generate summary.';
            
            // Save summary as AI message
            $this->chatMessageModel->addMessage(
                $sessionId,
                'ai',
                "**Document Summary:**\n\n" . $summary
            );
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => [
                    'summary' => $summary
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Generate summary error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to generate summary'
            ])->setStatusCode(500);
        }
    }
    
    public function getSession($sessionId)
    {
        try {
            $userId = $this->request->user->user_id;
            log_message('info', 'Getting session ' . $sessionId . ' for user ' . $userId);
            
            // Verify session belongs to user
            $session = $this->chatSessionModel->where('session_id', $sessionId)
                                             ->where('user_id', $userId)
                                             ->first();
            if (!$session) {
                log_message('error', 'Session not found or access denied: ' . $sessionId);
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid session or access denied'
                ])->setStatusCode(403);
            }
            
            log_message('info', 'Session found, getting PDFs for session: ' . $sessionId);
            
            // Get PDFs in this session
            $sessionPdfs = $this->chatSessionModel->getSessionPdfs($sessionId);
            log_message('info', 'Found ' . count($sessionPdfs) . ' PDFs for session: ' . $sessionId);
            
            return $this->response->setJSON([
                'status' => 'success',
                'data' => [
                    'session' => $session,
                    'pdfs' => $sessionPdfs
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Get session error: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in ' . $e->getFile());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to retrieve session: ' . $e->getMessage()
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
                if ($message->references_data) {
                    $message->references = json_decode($message->references_data, true);
                } else {
                    $message->references = [];
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
    
    public function testDatabase()
    {
        try {
            $db = \Config\Database::connect();
            
            // List all tables
            $tables = $db->listTables();
            
            // Check specific tables
            $hasChatMessages = in_array('chat_messages', $tables);
            $hasChatSessions = in_array('chat_sessions', $tables);
            $hasChatSessionPdfs = in_array('chat_session_pdfs', $tables);
            
            // Test queries
            $chatMessagesCount = 0;
            $chatSessionsCount = 0;
            $chatSessionPdfsCount = 0;
            
            if ($hasChatMessages) {
                $chatMessagesCount = $db->table('chat_messages')->countAllResults();
            }
            
            if ($hasChatSessions) {
                $chatSessionsCount = $db->table('chat_sessions')->countAllResults();
            }
            
            if ($hasChatSessionPdfs) {
                $chatSessionPdfsCount = $db->table('chat_session_pdfs')->countAllResults();
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Database connection successful',
                'tables' => $tables,
                'table_checks' => [
                    'chat_messages' => $hasChatMessages,
                    'chat_sessions' => $hasChatSessions,
                    'chat_session_pdfs' => $hasChatSessionPdfs
                ],
                'counts' => [
                    'chat_messages' => $chatMessagesCount,
                    'chat_sessions' => $chatSessionsCount,
                    'chat_session_pdfs' => $chatSessionPdfsCount
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ])->setStatusCode(500);
        }
    }
    
    private function getConversationHistory($sessionId, $limit = 10)
    {
        try {
            $messages = $this->chatMessageModel->getLastMessages($sessionId, $limit);
            
            // Reverse to chronological order (oldest -> newest) for the AI context
            $messages = array_reverse($messages);
            
            $history = [];
            
            foreach ($messages as $message) {
                $history[] = [
                    'sender' => $message->sender,
                    'message_text' => $message->message_text,
                    'created_at' => $message->created_at
                ];
            }
            
            return $history;
        } catch (\Exception $e) {
            log_message('error', 'Error getting conversation history: ' . $e->getMessage());
            return [];
        }
    }
    
    private function sendToAI($question, $pdfIds, $userId, $sessionId, $conversationHistory = [])
    {
        $pythonServerUrl = getenv('PYTHON_SERVER_URL') ?: 'http://localhost:5000';
        
        $postData = json_encode([
            'question' => $question,
            'pdf_ids' => $pdfIds,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'conversation_history' => $conversationHistory
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $pythonServerUrl . '/chat',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120, // Increased timeout for Groq API retries
            CURLOPT_CONNECTTIMEOUT => 30,
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
    public function addPdfs($sessionId)
    {
        try {
            $data = $this->request->getJSON(true);
            $userId = $this->request->user->user_id;
            $pdfIds = $data['pdf_ids'] ?? [];

            if (empty($pdfIds)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'No PDFs provided'
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

            // Validate PDFs belong to user
            foreach ($pdfIds as $pdfId) {
                $pdf = $this->pdfModel->getPdfById($pdfId, $userId);
                if (!$pdf) {
                    return $this->response->setJSON([
                        'status' => 'error',
                        'message' => 'Invalid PDF ID or access denied: ' . $pdfId
                    ])->setStatusCode(400);
                }
            }

            // Add PDFs to session
            $this->chatSessionModel->addPdfsToSession($sessionId, $pdfIds);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'PDFs added to session successfully'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Add PDFs to session error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to add PDFs to session'
            ])->setStatusCode(500);
        }
    }

    public function removePdf($sessionId, $pdfId)
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

            // Remove PDF from session
            $this->chatSessionModel->removePdfFromSession($sessionId, $pdfId);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'PDF removed from session successfully'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Remove PDF from session error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to remove PDF from session'
            ])->setStatusCode(500);
        }
    }
}
