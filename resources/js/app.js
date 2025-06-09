import './bootstrap';

// When the extraction form is present on the page
document.addEventListener('DOMContentLoaded', function() {
    const progressBar = document.getElementById('progress-bar');
    const progressStatus = document.getElementById('progress-status');
    const extractionForm = document.getElementById('extraction-form');
    const resultContainer = document.getElementById('result-container');
    const downloadBtn = document.getElementById('download-btn');
    
    if (extractionForm) {
        extractionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(extractionForm);
            
            // Show progress container
            document.getElementById('progress-container').classList.remove('d-none');
            
            // Disable form submission
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            // Upload the file
            fetch(extractionForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showError(data.error);
                    resetForm();
                    return;
                }
                
                // Start the extraction process
                startExtraction(data.jobId);
            })
            .catch(error => {
                showError('Error uploading file: ' + error.message);
                resetForm();
            });
        });
    }
    
    // Function to start the extraction process
    function startExtraction(jobId) {
        fetch(`/email-extractor/process/${jobId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showError(data.error);
                    resetForm();
                    return;
                }
                
                // Start checking progress
                checkProgress(jobId);
            })
            .catch(error => {
                showError('Error starting extraction: ' + error.message);
                resetForm();
            });
    }
    
    // Function to check extraction progress
    function checkProgress(jobId) {
        let progressInterval = setInterval(() => {
            fetch(`/email-extractor/progress/${jobId}`)
                .then(response => response.json())
                .then(data => {
                    const percent = Math.round((data.processed / data.total) * 100);
                    updateProgress(percent, `Processing: ${data.processed}/${data.total}`);
                    
                    if (data.processed >= data.total || data.error) {
                        clearInterval(progressInterval);
                        
                        if (data.error) {
                            showError(data.error);
                            resetForm();
                            return;
                        }
                        
                        updateProgress(100, 'Processing complete!');
                        
                        // Show results link
                        if (resultContainer) {
                            resultContainer.classList.remove('d-none');
                            const resultsLink = document.getElementById('results-link');
                            if (resultsLink) {
                                resultsLink.href = `/email-extractor/results/${jobId}`;
                            }
                        }
                        
                        // Update download button
                        if (downloadBtn) {
                            downloadBtn.classList.remove('d-none');
                            downloadBtn.href = `/email-extractor/download/${jobId}`;
                        }
                    }
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    showError('Error checking progress: ' + error.message);
                    resetForm();
                });
        }, 2000); // Check every 2 seconds
    }
    
    // Function to update progress bar
    function updateProgress(percent, statusText) {
        if (progressBar) {
            progressBar.style.width = percent + '%';
            progressBar.setAttribute('aria-valuenow', percent);
            progressBar.textContent = percent + '%';
        }
        
        if (progressStatus) {
            progressStatus.textContent = statusText;
        }
    }
    
    // Function to show error
    function showError(message) {
        const errorContainer = document.getElementById('error-container');
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.classList.remove('d-none');
        } else {
            alert(message);
        }
    }
    
    // Function to reset form
    function resetForm() {
        if (extractionForm) {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Extract Emails';
        }
    }
});
