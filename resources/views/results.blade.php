@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Extraction Results</h5>
                        <a href="{{ route('emailExtractor.download', ['jobId' => $jobId]) }}" class="btn btn-success">Download CSV</a>
                    </div>
                </div>

                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Emails</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($results as $url => $data)
                                    @php
                                        $domain = parse_url($url, PHP_URL_HOST) ?: $url;
                                    @endphp
                                    <tr>
                                        <td>{{ $domain }}</td>
                                        <td>
                                            @if(isset($data['emails']) && count($data['emails']) > 0)
                                                {{ implode(', ', $data['emails']) }}
                                            @else
                                                <span class="text-muted">No emails found</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center">No results available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="{{ route('emailExtractor.index') }}" class="btn btn-primary">Upload New File</a>
            </div>
        </div>
    </div>
</div>
@endsection 