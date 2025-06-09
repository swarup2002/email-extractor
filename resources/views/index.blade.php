@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Email Extractor</h5>
                </div>

                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div id="error-container" class="alert alert-danger d-none"></div>

                    <form id="extraction-form" method="POST" action="{{ route('emailExtractor.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="file">Upload CSV file with website URLs</label>
                            <input type="file" class="form-control" id="file" name="file" accept=".csv" required>
                            <small class="form-text text-muted">The CSV file should have one website URL per row.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Extract Emails</button>
                    </form>

                    <div id="progress-container" class="mt-4 d-none">
                        <h5>Extraction Progress</h5>
                        <div class="progress mb-2">
                            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <p id="progress-status">Starting extraction...</p>
                    </div>

                    <div id="result-container" class="mt-4 d-none">
                        <div class="alert alert-success">
                            <p>Email extraction completed!</p>
                            <div class="mt-2">
                                <a id="results-link" href="#" class="btn btn-info">View Results</a>
                                <a id="download-btn" href="#" class="btn btn-success d-none">Download CSV</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 