<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Email Extractor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .upload-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .upload-title {
            text-align: center;
            margin-bottom: 20px;
        }
        .upload-instructions {
            margin-bottom: 25px;
        }
        .file-upload {
            position: relative;
            overflow: hidden;
            margin: 10px 0;
            text-align: center;
        }
        .file-upload input[type=file] {
            position: absolute;
            top: 0;
            right: 0;
            min-width: 100%;
            min-height: 100%;
            font-size: 100px;
            text-align: right;
            filter: alpha(opacity=0);
            opacity: 0;
            outline: none;
            background: white;
            cursor: pointer;
            display: block;
        }
        .custom-file-label {
            padding: 15px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .custom-file-label:hover {
            border-color: #007bff;
            background-color: #f1f1f1;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="upload-container">
            <div class="logo">
                <h2>Email Extractor</h2>
            </div>
            
            <h3 class="upload-title">Website Email Extractor</h3>
            
            <div id="error-container" class="alert alert-danger hidden"></div>
            
            <div id="upload-section">
                <div class="upload-instructions">
                    <p>Upload a CSV file containing a list of website URLs. The tool will extract visible email addresses from each website.</p>
                    <p>The file should have a column named <strong>"url"</strong> containing the website addresses.</p>
                    
                    <div class="alert alert-info mt-3">
                        <h5>CSV File Format Tips:</h5>
                        <ul>
                            <li>Save your file with a <strong>.csv</strong> extension</li>
                            <li>Your file should have a header row with a column named "url"</li>
                            <li>Each row should contain one website URL</li>
                            <li>Example CSV content:
                                <pre class="bg-light p-2 mt-1">url
example.com
google.com
microsoft.com</pre>
                            </li>
                            <li>If you're using Excel, save as "CSV (Comma delimited) (*.csv)"</li>
                        </ul>
                    </div>
                </div>
                
                @if(session('error'))
                    <div class="alert alert-danger">
                        <h5>Error:</h5>
                        <p>{{ session('error') }}</p>
                        
                        <h5 class="mt-3">Troubleshooting:</h5>
                        <ul>
                            <li>Make sure your file has a .csv extension</li>
                            <li>Check that your CSV file has the correct format as described above</li>
                            <li>If created in Excel, try saving as "CSV (Comma delimited) (*.csv)"</li>
                            <li>Try opening your CSV file in a text editor to confirm the format</li>
                        </ul>
                    </div>
                @endif
                
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('emailExtractor.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        
                        <div class="file-upload">
                            <label for="file" class="custom-file-label">
                                <i class="fa fa-cloud-upload"></i> Click to select a file or drag and drop
                            </label>
                            <input type="file" name="file" id="file" class="form-control">
                        </div>
                        
                        @error('file')
                            <div class="alert alert-danger mt-2">
                                {{ $message }}
                            </div>
                        @enderror
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100" id="submitBtn">Upload and Start Extraction</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="progress-section" class="hidden">
                <h4 class="mb-3">Extraction Progress</h4>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="progressBar"></div>
                </div>
                <p id="progressStatus">Starting extraction...</p>
                
                <div id="result-container" class="hidden mt-4">
                    <div class="alert alert-success">
                        <p>Email extraction completed!</p>
                        <div class="mt-3">
                            <a id="resultsLink" href="#" class="btn btn-info">View Results</a>
                            <a id="downloadBtn" href="#" class="btn btn-success">Download CSV</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Show file name when selected
        document.getElementById('file').addEventListener('change', function(e) {
            const fileName = e.target.files[0].name;
            const label = document.querySelector('.custom-file-label');
            label.textContent = fileName;
        });
        
        // Handle form submission
        $(document).ready(function() {
            $('#uploadForm').on('submit', function(e) {
                e.preventDefault();
                
                // Reset error container
                $('#error-container').addClass('hidden').text('');
                
                // Create form data object
                const formData = new FormData(this);
                
                // Disable submit button
                $('#submitBtn').prop('disabled', true).text('Processing...');
                
                // Submit the form via AJAX
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.error) {
                            showError(response.error);
                            resetForm();
                            return;
                        }
                        
                        if (response.success && response.jobId) {
                            // Hide upload section and show progress section
                            $('#upload-section').addClass('hidden');
                            $('#progress-section').removeClass('hidden');
                            
                            // Start the extraction process
                            startExtraction(response.jobId);
                        } else {
                            showError('Invalid response from server');
                            resetForm();
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = 'Error uploading file';
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                errorMessage = response.error;
                            }
                        } catch (e) {
                            errorMessage += ': ' + error;
                        }
                        
                        showError(errorMessage);
                        resetForm();
                    }
                });
            });
            
            // Function to start the extraction process
            function startExtraction(jobId) {
                $.ajax({
                    url: '{{ route("emailExtractor.process", ["jobId" => "_JOB_ID_"]) }}'.replace('_JOB_ID_', jobId),
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            showError(response.error);
                            resetForm();
                            return;
                        }
                        
                        // Start checking progress
                        checkProgress(jobId);
                    },
                    error: function(xhr, status, error) {
                        showError('Error starting extraction: ' + error);
                        resetForm();
                    }
                });
            }
            
            // Function to check extraction progress
            function checkProgress(jobId) {
                let progressInterval = setInterval(function() {
                    $.ajax({
                        url: '{{ route("emailExtractor.progress", ["jobId" => "_JOB_ID_"]) }}'.replace('_JOB_ID_', jobId),
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.error) {
                                clearInterval(progressInterval);
                                showError(response.error);
                                resetForm();
                                return;
                            }
                            
                            // Update progress bar
                            const percent = response.percentage || 0;
                            $('#progressBar').css('width', percent + '%').text(percent + '%');
                            $('#progressStatus').text(`Processing: ${response.processed}/${response.total} URLs`);
                            
                            // Check if process is complete
                            if (response.processed >= response.total) {
                                clearInterval(progressInterval);
                                
                                // Show completion message
                                $('#progressStatus').text('Processing complete!');
                                $('#progressBar').css('width', '100%').text('100%');
                                
                                // Update result links
                                $('#resultsLink').attr('href', '{{ route("emailExtractor.results", ["jobId" => "_JOB_ID_"]) }}'.replace('_JOB_ID_', jobId));
                                $('#downloadBtn').attr('href', '{{ route("emailExtractor.download", ["jobId" => "_JOB_ID_"]) }}'.replace('_JOB_ID_', jobId));
                                
                                // Show result container
                                $('#result-container').removeClass('hidden');
                            }
                        },
                        error: function(xhr, status, error) {
                            clearInterval(progressInterval);
                            showError('Error checking progress: ' + error);
                        }
                    });
                }, 2000); // Check every 2 seconds
            }
            
            // Function to show error
            function showError(message) {
                $('#error-container').removeClass('hidden').text(message);
            }
            
            // Function to reset form
            function resetForm() {
                $('#submitBtn').prop('disabled', false).text('Upload and Start Extraction');
                $('#progress-section').addClass('hidden');
                $('#upload-section').removeClass('hidden');
            }
        });
    </script>
</body>
</html> 