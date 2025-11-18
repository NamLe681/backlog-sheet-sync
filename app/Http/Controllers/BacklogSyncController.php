<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;

class BacklogSyncController extends Controller
{
    protected $spreadsheetId;
    protected $sheetName = 'Sheet1';
    protected $startCell = 'J10';

    public function __construct()
    {
        $this->spreadsheetId = env('GOOGLE_SHEET_ID');
    }

    // Hàm chính để sync
    public function syncWeeklyTasks()
    {
        $tasks = $this->getBacklogTasks();

        $content = $this->formatTasks($tasks);

        $this->updateSheet($content);

        return "Sync Backlog → Google Sheet thành công!";
    }

    // 1. Lấy task từ Backlog API
    protected function getBacklogTasks()
    {
        $spaceId   = env('BACKLOG_SPACE_ID');
        $apiKey    = env('BACKLOG_API_KEY');
        $projectId = env('BACKLOG_PROJECT_ID');

        // Lấy tuần trước và tuần này
        $startOfWeek = now()->startOfWeek()->format('Y-m-d');
        $endOfWeek   = now()->endOfWeek()->format('Y-m-d');

        $url = "https://{$spaceId}.backlog.com/api/v2/issues";

        $response = \Http::get($url, [
            'apiKey' => $apiKey,
            'projectId[]' => $projectId,
            'createdSince' => $startOfWeek,
            'createdUntil' => $endOfWeek,
            'count' => 100
        ]);

        // Giả sử trả về JSON array các task
        return $response->json();
    }

    // 2. Format task thành 1 chuỗi để ghi vào 1 ô
    protected function formatTasks(array $tasks)
    {
        $content = "▼Tuần trước:\n\n";
        $content .= "- Confirm CSV format error when registering products (after system update) -> deploy prod\n";
        $content .= "https://beetechjsc.backlog.jp/view/MOBAGACHA_INTERNAL-84\n\n";

        // Tuần này (dữ liệu mẫu)
        $content .= "▼Tuần này:\n\n";
        $content .= "- [MOBAGACHA] MOBAGACHA site down \n";
        $content .= "https://beetechjsc.backlog.jp/view/MOBAGACHA_INTERNAL-83\n";

        return $content;
    }

    // 3. Update vào Sheet, tự thêm dòng nếu thiếu
    protected function updateSheet(string $content)
    {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/google/credentials.json'));
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);

        $service = new Google_Service_Sheets($client);

        // Lấy thông tin sheet
        $sheet = $service->spreadsheets->get($this->spreadsheetId);
        $sheetId = $sheet->sheets[0]->properties->sheetId;
        $totalRows = $sheet->sheets[0]->properties->gridProperties->rowCount;

        // Tính row bắt đầu
        preg_match('/\D+(\d+)/', $this->startCell, $matches);
        $startRow = intval($matches[1]);

        // Nếu thiếu dòng → thêm dòng
        if ($startRow > $totalRows) {
            $rowsToAdd = $startRow - $totalRows;
            $requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => [
                    'insertDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $totalRows,
                            'endIndex' => $totalRows + $rowsToAdd
                        ]
                    ]
                ]
            ]);
            $service->spreadsheets->batchUpdate($this->spreadsheetId, $requestBody);
        }

        // Ghi vào 1 ô duy nhất
        $body = new Google_Service_Sheets_ValueRange([
            'values' => [
                [$content]
            ]
        ]);

        $params = ['valueInputOption' => 'RAW'];

        $service->spreadsheets_values->update(
            $this->spreadsheetId,
            $this->sheetName . '!' . $this->startCell,
            $body,
            $params
        );
    }
}
