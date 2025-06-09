<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Str;

class UrlsImport implements ToCollection, WithHeadingRow, WithValidation
{
    /**
     * @param Collection $rows
     *
     * @return void
     */
    public function collection(Collection $rows)
    {
        // We'll extract the URLs in the controller
        return $rows;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string'],
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'url.required' => 'The URL field is required.',
        ];
    }
} 