<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class EmailsExport implements FromArray, WithHeadings, ShouldAutoSize
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        $rows = [];
        
        foreach ($this->data as $url => $data) {
            if (isset($data['emails']) && !empty($data['emails'])) {
                foreach ($data['emails'] as $email) {
                    $rows[] = [
                        'url' => $data['url'],
                        'email' => $email
                    ];
                }
            } else {
                $rows[] = [
                    'url' => $data['url'],
                    'email' => 'No emails found'
                ];
            }
        }
        
        return $rows;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Website URL',
            'Email Address'
        ];
    }
} 