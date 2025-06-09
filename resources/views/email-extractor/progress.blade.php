<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Email Extraction Progress</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .progress-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .progress-title {
            text-align: center;
            margin-bottom: 30px;
        }
        .progress {
            height: 25px;
        }
        .progress-bar {
            font-size: 14px;
            line-height: 25px;
        }
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }
        .stat-box {
            text-align: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            flex: 1;
            margin: 0 10px;
        }
        .actions {
            margin-top: 30px;
            text-align: center;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="progress-container">
            <div class="logo">
                <h2>Email Extractor</h2>
            </div>
            
            <h3 class="progress-title">Extraction Progress</h3>
            
            <div id="error-container" class="alert alert-danger mb-4 hidden">
                <h5>Error:</h5>
                <p id="error-message"></p>
                <div class="mt-3">
                    <button id="retry-btn" class="btn btn-warning btn-sm">Retry</button>
                    <a href="{{ route('emailExtractor.upload') }}" class="btn btn-primary btn-sm">Upload New File</a>
                </div>
            </div>
            
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">0%</div>
            </div>
            
            <div class="stats-container mt-4">
                <div class="stat-box">
                    <h5>Total Websites</h5>
                    <p id="total-count">{{ $total }}</p>
                </div>
                <div class="stat-box">
                    <h5>Processed</h5>
                    <p id="processed-count">{{ $processed }}</p>
                </div>
                <div class="stat-box">
                    <h5>Remaining</h5>
                    <p id="remaining-count">{{ $total - $processed }}</p>
                </div>
            </div>
            
            <div class="actions">
                <button id="start-btn" class="btn btn-primary">Start Extraction</button>
                <a id="export-btn" href="{{ route('emailExtractor.download', ['jobId' => $jobId]) }}" class="btn btn-success hidden">Download Results</a>
                <a href="{{ route('emailExtractor.upload') }}" class="btn btn-secondary">Upload Another File</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            const jobId = '{{ $jobId }}';
            let checkProgressInterval;
            
            // Start extraction process
            $('#start-btn').click(function() {
                startExtraction();
            });
            
            // Retry button
            $('#retry-btn').click(function() {
                $('#error-container').addClass('hidden');
                startExtraction();
            });
            
            function startExtraction() {
                $('#start-btn').prop('disabled', true).text('Processing...');
                $('#error-container').addClass('hidden');
                
                $.ajax({
                    url: '{{ route('emailExtractor.process', ['jobId' => $jobId]) }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        checkProgressInterval = setInterval(checkProgress, 2000);
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = 'Error starting extraction process';
                        
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                        } else if (error) {
                            errorMessage = error;
                        }
                        
                        $('#error-message').text(errorMessage);
                        $('#error-container').removeClass('hidden');
                        console.error('AJAX Error:', xhr, status, error);
                        $('#start-btn').prop('disabled', false).text('Try Again');
                    }
                });
            }
            
            // Check progress
            function checkProgress() {
                $.ajax({
                    url: '{{ route('emailExtractor.progress', ['jobId' => $jobId]) }}',
                    type: 'GET',
                    success: function(response) {
                        // Check if there's an error
                        if (response.has_error) {
                            $('#error-message').text(response.error || 'An unknown error occurred during extraction.');
                            $('#error-container').removeClass('hidden');
                            $('#start-btn').addClass('hidden');
                            clearInterval(checkProgressInterval);
                            return;
                        }
                        
                        updateProgressBar(response);
                        
                        if (response.completed) {
                            clearInterval(checkProgressInterval);
                            $('#start-btn').addClass('hidden');
                            $('#export-btn').removeClass('hidden');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error checking progress:', xhr, status, error);
                        
                        // If we can't check progress, show an error
                        $('#error-message').text('Error checking progress: ' + (error || 'Unknown error'));
                        $('#error-container').removeClass('hidden');
                    }
                });
            }
            
            // Update progress bar and stats
            function updateProgressBar(data) {
                const progressBar = $('.progress-bar');
                const percentage = data.percentage;
                
                progressBar.css('width', percentage + '%')
                           .attr('aria-valuenow', percentage)
                           .text(percentage + '%');
                
                $('#processed-count').text(data.processed);
                $('#remaining-count').text(data.total - data.processed);
            }
            
            // If already processing, start checking progress
            if ({{ $processed }} > 0) {
                $('#start-btn').prop('disabled', true).text('Processing...');
                checkProgressInterval = setInterval(checkProgress, 2000);
            }
        });
    </script>
</body>
</html> 