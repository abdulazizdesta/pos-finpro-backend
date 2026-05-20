<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ArrayExport implements FromArray, WithTitle
{
    public function __construct(private array $data) {}

    public function array(): array
    {
        return $this->data;
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }
}