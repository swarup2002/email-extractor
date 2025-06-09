<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmailExport;
use Illuminate\Support\Facades\Storage;

class ScrapeController extends Controller
{
    public function index()
    {
        return view('index');
    }

    public function scrape(Request $request)
    {
        $request->validate([
            'websites' => 'required|file|mimes:csv,xlsx'
        ]);

        // Parse uploaded file
        $path = $request->file('websites')->store('temp');
        $data = Excel::toArray([], $request->file('websites'));

        // Flatten rows into array of URLs
        $urls = collect($data)->flatten()->filter()->values()->all();

        // Save to a JSON file for Node.js to read
        Storage::disk('local')->put('urls.json', json_encode($urls));

        // Run the Node scraper
        exec('cd node && node scraper.js');

        // Read results
        $resultPath = base_path('node/results.json');
        if (!file_exists($resultPath)) {
            return back()->with('error', 'Scraping failed.');
        }

        $results = json_decode(file_get_contents($resultPath), true);

        // Export to Excel and return
        return Excel::download(new EmailExport($results), 'email-results.xlsx');
    }
}
