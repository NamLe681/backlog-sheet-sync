<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-google', function () {
    $client = new Google_Client();
    $client->setAuthConfig(storage_path('app/google/credentials.json'));
    $client->addScope(Google_Service_Sheets::SPREADSHEETS);

    $service = new Google_Service_Sheets($client);
    $id = env('GOOGLE_SHEET_ID');

    $body = new Google_Service_Sheets_ValueRange([
        'values' => [
            ['Task 1', 'Alice', '2025-11-17 10:00:00'],
            ['Task 12', 'Bob',   '2025-11-17 11:00:00'],
            ['Task 3', 'Bob',   '2025-11-17 11:00:00'],
            ['Task 24', 'Bob',   '2025-11-17 11:00:00'],
            ['bye', 'World', now()->toDateTimeString()]
        ]
    ]);

    $response = $service->spreadsheets_values->update(
        $id,
        'Sheet1!I10:K15',
        $body,
        ['valueInputOption' => 'RAW']
    );

    return response()->json($response);
});


Route::get('/sync/backlog', [App\Http\Controllers\BacklogSyncController::class, 'syncWeeklyTasks']);
