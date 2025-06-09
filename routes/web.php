<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailExtractorController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('emailExtractor.index');
});

// Email Extractor Routes
Route::prefix('email-extractor')->group(function () {
    // Show the upload form
    Route::get('/', [EmailExtractorController::class, 'index'])->name('emailExtractor.index');
    
    // Upload a file and start processing
    Route::post('/upload', [EmailExtractorController::class, 'upload'])->name('emailExtractor.upload');
    
    // Process the uploaded file
    Route::get('/process/{jobId}', [EmailExtractorController::class, 'process'])->name('emailExtractor.process');
    
    // Get progress
    Route::get('/progress/{jobId}', [EmailExtractorController::class, 'progress'])->name('emailExtractor.progress');
    
    // Show results
    Route::get('/results/{jobId}', [EmailExtractorController::class, 'results'])->name('emailExtractor.results');
    
    // Download results as CSV
    Route::get('/download/{jobId}', [EmailExtractorController::class, 'download'])->name('emailExtractor.download');
});

// Add any routing changes if needed

