<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PdfModel;
use App\Models\ChatSessionPdf;

class DashboardController extends BaseController
{
    protected $pdfModel;
    protected $chatSessionModel;

    public function __construct()
    {
        $this->pdfModel = new PdfModel();
        $this->chatSessionModel = new ChatSessionPdf();
    }

    public function getStats()
    {
        try {
            $userId = $this->request->user->user_id;

            // Efficiently count PDFs
            $pdfCount = $this->pdfModel->where('user_id', $userId)->countAllResults();

            // Efficiently count Sessions
            $sessionCount = $this->chatSessionModel->where('user_id', $userId)->countAllResults();

            return $this->response->setJSON([
                'status' => 'success',
                'data' => [
                    'pdf_count' => $pdfCount,
                    'session_count' => $sessionCount
                ]
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Get dashboard stats error: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard stats'
            ])->setStatusCode(500);
        }
    }
}
