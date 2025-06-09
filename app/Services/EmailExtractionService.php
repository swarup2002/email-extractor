<?php

namespace App\Services;

use Laravel\Dusk\Browser;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Laravel\Dusk\Chrome\ChromeProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailExtractionService
{
    protected $commonPages = [
        '',                  // Homepage
        'about',            // About page
        'about-us',
        'aboutus',
        'contact',          // Contact page
        'contact-us',
        'contactus',
        'services',         // Services page
        'our-services',
        'ourservices'
    ];

    /**
     * Extract emails from a list of URLs
     *
     * @param array $urls
     * @param callable $progressCallback
     * @return array
     */
    public function extractEmails(array $urls, callable $progressCallback = null): array
    {
        $results = [];
        $totalUrls = count($urls);
        $processed = 0;
        $chromeProcess = null;

        try {
            // Check if Chrome can be started
            try {
                $chromeProcess = $this->startChromeProcess();
                // Wait a bit for ChromeDriver to start
                sleep(2);
                \Log::info("Chrome process started successfully");
            } catch (\Exception $e) {
                // If Chrome can't be started, log the error and continue without it
                \Log::error("Failed to start Chrome process: " . $e->getMessage());
                \Log::error("Will attempt to use a non-browser method for extraction");
                $chromeProcess = null;
            }
            
            foreach ($urls as $url) {
                // Clean the URL
                $url = $this->normalizeUrl($url);
                $results[$url] = ['url' => $url, 'emails' => []];
                
                try {
                    $emails = [];
                    
                    if ($chromeProcess && $chromeProcess->isRunning()) {
                        // Try browser-based extraction
                        try {
                            $emails = $this->crawlWebsiteForEmails($url);
                        } catch (\Exception $e) {
                            \Log::warning("Browser extraction failed for {$url}: " . $e->getMessage());
                            // Fallback to non-browser extraction
                            $emails = $this->fallbackExtractEmails($url);
                        }
                    } else {
                        // Use non-browser extraction directly
                        $emails = $this->fallbackExtractEmails($url);
                    }
                    
                    $results[$url]['emails'] = $emails;
                    \Log::info("Extracted " . count($emails) . " emails from {$url}");
                } catch (\Exception $e) {
                    \Log::error("Error extracting emails from {$url}: " . $e->getMessage());
                    $results[$url]['error'] = $e->getMessage();
                }

                $processed++;
                if ($progressCallback) {
                    call_user_func($progressCallback, $processed, $totalUrls);
                }
            }
        } finally {
            // Ensure Chrome process is properly stopped
            if ($chromeProcess && $chromeProcess->isRunning()) {
                try {
                    $chromeProcess->stop();
                    \Log::info("Chrome process stopped successfully");
                } catch (\Exception $e) {
                    \Log::warning("Error stopping Chrome process: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Use a headless browser to crawl a website and extract emails
     * 
     * @param string $url
     * @return array
     */
    protected function crawlWebsiteForEmails(string $url): array
    {
        $allEmails = [];
        
        try {
            // Set up Chrome options
            $options = new \Facebook\WebDriver\Chrome\ChromeOptions();
            $options->addArguments([
                '--headless',
                '--disable-gpu',
                '--window-size=1920,1080',
                '--no-sandbox',
                '--disable-dev-shm-usage'
            ]);
            
            // Start a driver
            $driver = $this->driver($options);
            
            try {
                // Visit the main page
                \Log::info("Browsing to {$url}");
                $driver->get($url);
                
                // Wait for page to load
                sleep(2);
                
                // Get the page source
                $pageSource = $driver->getPageSource();
                
                // Extract emails from the page source
                $emails = $this->findEmailsInText($pageSource);
                $allEmails = array_merge($allEmails, $emails);
                \Log::info("Found " . count($emails) . " emails on main page of {$url}");
                
                // Try to visit common pages
                foreach ($this->commonPages as $page) {
                    if (empty($page)) continue; // Skip empty pages
                    
                    try {
                        $pageUrl = rtrim($url, '/') . '/' . $page;
                        \Log::info("Browsing to {$pageUrl}");
                        $driver->get($pageUrl);
                        
                        // Wait for page to load
                        sleep(2);
                        
                        // Get the page source
                        $pageSource = $driver->getPageSource();
                        
                        // Extract emails from the page source
                        $emails = $this->findEmailsInText($pageSource);
                        $allEmails = array_merge($allEmails, $emails);
                        \Log::info("Found " . count($emails) . " emails on {$pageUrl}");
                    } catch (\Exception $e) {
                        \Log::info("Could not visit {$pageUrl} in browser mode: " . $e->getMessage());
                        continue;
                    }
                }
            } finally {
                // Close the browser
                $driver->quit();
            }
        } catch (\Exception $e) {
            \Log::error("Error in browser-based extraction: " . $e->getMessage());
            throw $e;
        }
        
        return array_unique($allEmails);
    }

    /**
     * Extract emails from visible content using JS
     *
     * @param Browser $browser
     * @return array
     */
    protected function extractEmailsFromVisibleContent(Browser $browser): array
    {
        // Use JavaScript to extract text from visible elements only
        $visibleText = $browser->driver->executeScript("
            return Array.from(document.querySelectorAll('body *'))
                .filter(element => {
                    const style = window.getComputedStyle(element);
                    return style.display !== 'none' && 
                           style.visibility !== 'hidden' && 
                           style.opacity !== '0' &&
                           element.offsetWidth > 0 &&
                           element.offsetHeight > 0;
                })
                .map(element => element.textContent)
                .join(' ');
        ");

        return $this->findEmailsInText($visibleText);
    }

    /**
     * Find emails in text using regex
     *
     * @param string $text
     * @return array
     */
    protected function findEmailsInText(string $text): array
    {
        $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        preg_match_all($pattern, $text, $matches);
        
        $emails = [];
        if (!empty($matches[0])) {
            $emails = $matches[0];
        }
        
        return $emails;
    }

    /**
     * Normalize URL to ensure it has http/https prefix
     *
     * @param string $url
     * @return string
     */
    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        
        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }
        
        return $url;
    }

    /**
     * Fallback method to extract emails without using a browser
     * 
     * @param string $url
     * @return array
     */
    protected function fallbackExtractEmails(string $url): array
    {
        $allEmails = [];
        
        try {
            // Create a HTTP client with a timeout
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'connect_timeout' => 5,
                'verify' => false, // Skip SSL verification for simplicity
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
                'http_errors' => false, // Don't throw exceptions for HTTP errors
            ]);
            
            // Try to get the main page content
            try {
                $response = $client->get($url);
                if ($response->getStatusCode() == 200) {
                    $html = (string) $response->getBody();
                    $emails = $this->findEmailsInText($html);
                    $allEmails = array_merge($allEmails, $emails);
                }
            } catch (\Exception $e) {
                \Log::warning("Could not access {$url}: " . $e->getMessage());
            }
            
            // Try common pages
            foreach ($this->commonPages as $page) {
                if (empty($page)) continue; // Skip the homepage as we already got it
                
                try {
                    $pageUrl = rtrim($url, '/') . '/' . $page;
                    $response = $client->get($pageUrl, ['timeout' => 5]);
                    
                    if ($response->getStatusCode() == 200) {
                        $html = (string) $response->getBody();
                        $emails = $this->findEmailsInText($html);
                        $allEmails = array_merge($allEmails, $emails);
                    }
                } catch (\Exception $e) {
                    \Log::info("Could not visit {$pageUrl} in fallback mode: " . $e->getMessage());
                    continue;
                }
            }
        } catch (\Exception $e) {
            \Log::error("Fallback extraction failed for {$url}: " . $e->getMessage());
            // Return an empty array if everything fails
            return [];
        }
        
        return array_unique($allEmails);
    }

    /**
     * Start a Chrome process
     *
     * @return \Laravel\Dusk\Chrome\ChromeProcess
     */
    protected function startChromeProcess()
    {
        if (PHP_OS === 'WINNT') {
            // For Windows
            $chromeDriverPath = base_path('vendor/laravel/dusk/bin/chromedriver-win.exe');
        } else {
            // For Linux/Mac
            $chromeDriverPath = base_path('vendor/laravel/dusk/bin/chromedriver-linux');
            if (PHP_OS === 'Darwin') {
                $chromeDriverPath = base_path('vendor/laravel/dusk/bin/chromedriver-mac');
            }
        }

        $process = new \Symfony\Component\Process\Process([
            realpath($chromeDriverPath)
        ]);
        
        $process->start();
        
        return $process;
    }
    
    /**
     * Create a browser driver instance
     *
     * @param  \Facebook\WebDriver\Chrome\ChromeOptions  $options
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function driver(\Facebook\WebDriver\Chrome\ChromeOptions $options)
    {
        $capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
        $capabilities->setCapability(\Facebook\WebDriver\Chrome\ChromeOptions::CAPABILITY, $options);
        
        return \Facebook\WebDriver\Remote\RemoteWebDriver::create(
            'http://localhost:9515',
            $capabilities,
            60000, // Connection timeout in milliseconds
            60000  // Request timeout in milliseconds
        );
    }
} 