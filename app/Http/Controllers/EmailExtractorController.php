<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UploadFileRequest;
use App\Services\EmailExtractionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;

class EmailExtractorController extends Controller
{
    protected $emailExtractionService;

    public function __construct(EmailExtractionService $emailExtractionService)
    {
        $this->emailExtractionService = $emailExtractionService;
    }

    /**
     * Show the form for uploading a file
     */
    public function index()
    {
        return view('email-extractor.index');
    }

    /**
     * Upload a file and prepare for extraction
     */
    public function upload(Request $request)
    {
        try {
            // Validate the file
            $validated = $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            ]);
            
            // Generate a job ID
            $jobId = uniqid('job_');
            
            // Process the file
            $urls = $this->processFile($request->file('file'));
            
            if (empty($urls)) {
                return response()->json(['error' => 'No valid URLs found in the file. Please check the file format.']);
            }
            
            // Store URLs in cache for processing
            Cache::put("extraction_urls_{$jobId}", $urls, now()->addHour());
            Cache::put("extraction_total_{$jobId}", count($urls), now()->addHour());
            Cache::put("extraction_processed_{$jobId}", 0, now()->addHour());
            
            // Return JSON response with job ID
            return response()->json(['success' => true, 'jobId' => $jobId]);
        } catch (\Exception $e) {
            \Log::error("Error uploading file: " . $e->getMessage());
            return response()->json(['error' => 'Error uploading file: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Get the progress of the extraction
     */
    public function progress($jobId)
    {
        try {
            $total = Cache::get("extraction_total_{$jobId}", 0);
            $processed = Cache::get("extraction_processed_{$jobId}", 0);
            $error = Cache::get("extraction_error_{$jobId}");
            
            return response()->json([
                'total' => $total,
                'processed' => $processed,
                'error' => $error,
                'percentage' => $total > 0 ? round(($processed / $total) * 100) : 0
            ]);
        } catch (\Exception $e) {
            \Log::error("Error getting progress: " . $e->getMessage());
            return response()->json(['error' => 'Error getting progress: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Start the extraction process
     */
    public function process($jobId)
    {
        try {
            // Get URLs from cache
            $urls = Cache::get("extraction_urls_{$jobId}", []);
            
            if (empty($urls)) {
                return response()->json(['error' => 'No URLs found for processing. Please upload a file again.'], 400);
            }
            
            // Limit the number of URLs to process to avoid timeouts
            $maxUrlsToProcess = 50; // Reduce for better stability
            if (count($urls) > $maxUrlsToProcess) {
                \Log::warning("Large URL list detected. Processing only first {$maxUrlsToProcess} URLs.");
                $urls = array_slice($urls, 0, $maxUrlsToProcess);
            }
            
            \Log::info("Starting extraction process for job {$jobId} with " . count($urls) . " URLs");
            
            // Set a longer timeout for the process
            set_time_limit(600); // 10 minutes
            ini_set('memory_limit', '512M'); // Increase memory limit
            
            // Process URLs and extract emails - use fallback mode by default
            $results = $this->emailExtractionService->extractEmails($urls, function ($processed, $total) use ($jobId) {
                Cache::put("extraction_processed_{$jobId}", $processed, now()->addHour());
                \Log::info("Progress for job {$jobId}: {$processed}/{$total}");
            });
            
            // Store results in cache
            Cache::put("extraction_results_{$jobId}", $results, now()->addHour());
            
            \Log::info("Completed extraction for job {$jobId}");
            
            return response()->json(['success' => true, 'jobId' => $jobId]);
        } catch (\Throwable $e) {
            // Catch all types of errors
            \Log::error("Error in extraction process for job {$jobId}: " . $e->getMessage());
            \Log::error("Exception File: " . $e->getFile() . " on line " . $e->getLine());
            
            // Store the error in cache
            Cache::put("extraction_error_{$jobId}", $e->getMessage(), now()->addHour());
            
            return response()->json([
                'error' => 'An error occurred during extraction: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Download the extracted emails as CSV
     */
    public function download($jobId)
    {
        try {
            // Get results from cache
            $results = Cache::get("extraction_results_{$jobId}", []);
            
            if (empty($results)) {
                return back()->with('error', 'No results found for download. Please try again.');
            }
            
            // Create a CSV file
            $filename = 'extracted_emails_' . date('Y-m-d_H-i-s') . '.csv';
            
            // Set headers for CSV download
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];
            
            // Create CSV writer
            $csv = \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());
            
            // Add headers
            $csv->insertOne(['Domain', 'Emails']);
            
            // Add data
            foreach ($results as $url => $data) {
                $domain = parse_url($url, PHP_URL_HOST) ?: $url;
                // Join emails with comma so they appear side by side in the same cell
                $emailsString = implode(', ', $data['emails'] ?? []);
                $csv->insertOne([$domain, $emailsString]);
            }
            
            return response($csv->getContent(), 200, $headers);
        } catch (\Exception $e) {
            \Log::error("Error generating CSV: " . $e->getMessage());
            return back()->with('error', 'Error generating CSV: ' . $e->getMessage());
        }
    }

    /**
     * Display the extraction results
     */
    public function results($jobId)
    {
        try {
            // Get results from cache
            $results = Cache::get("extraction_results_{$jobId}", []);
            $error = Cache::get("extraction_error_{$jobId}");
            
            if ($error) {
                return view('results', [
                    'jobId' => $jobId,
                    'results' => $results
                ])->with('error', "An error occurred during extraction: {$error}");
            }
            
            if (empty($results)) {
                return view('results', [
                    'jobId' => $jobId,
                    'results' => []
                ])->with('error', 'No results found. The extraction may still be in progress or has failed.');
            }
            
            return view('results', [
                'jobId' => $jobId,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            \Log::error("Error displaying results: " . $e->getMessage());
            
            return view('results', [
                'jobId' => $jobId,
                'results' => []
            ])->with('error', 'Error displaying results: ' . $e->getMessage());
        }
    }

    /**
     * Process the uploaded CSV file and extract URLs
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    protected function processFile($file)
    {
        $urls = [];
        
        try {
            // Get the file path
            $path = $file->getRealPath();
            
            // Create a CSV reader
            $csv = \League\Csv\Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0); // Assume the first row is headers
            
            // Check if 'url' column exists
            $headers = $csv->getHeader();
            $urlColumnIndex = null;
            
            // Try to find a column containing 'url' (case insensitive)
            foreach ($headers as $index => $header) {
                if (strtolower(trim($header)) === 'url' || 
                    strtolower(trim($header)) === 'website' || 
                    strtolower(trim($header)) === 'domain' ||
                    strtolower(trim($header)) === 'site') {
                    $urlColumnIndex = $index;
                    break;
                }
            }
            
            // If no URL column found, try to use the first column
            if ($urlColumnIndex === null) {
                \Log::warning("No URL column found in CSV. Using first column.");
                $urlColumnIndex = 0;
            }
            
            // Get the column name
            $urlColumnName = $headers[$urlColumnIndex];
            
            // Extract URLs from the column
            foreach ($csv as $record) {
                if (isset($record[$urlColumnName]) && !empty($record[$urlColumnName])) {
                    $url = trim($record[$urlColumnName]);
                    // Normalize URL if needed
                    $url = $this->normalizeUrl($url);
                    if (!empty($url)) {
                        $urls[] = $url;
                    }
                }
            }
            
            // Ensure unique URLs
            $urls = array_unique($urls);
            
            \Log::info("Processed file and found " . count($urls) . " unique URLs");
            
            return $urls;
        } catch (\Exception $e) {
            \Log::error("Error processing CSV file: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Normalize a URL by adding http:// if missing
     *
     * @param string $url
     * @return string
     */
    protected function normalizeUrl($url)
    {
        $url = trim($url);
        
        // If URL doesn't start with http:// or https://, add http://
        if (!empty($url) && !preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'http://' . $url;
        }
        
        return $url;
    }
}
