<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $assets;

    protected $rowIndex = 0; // Property to keep track of the row number

    // Accept the collection via the constructor
    protected $filters;
    protected $property;

    public function __construct(Collection $assets, array $filters = [], string $property = '')
    {
        $this->assets = $assets;
        $this->filters = $filters;
        $this->property = $property;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Simply return the collection we received
        return $this->assets;
    }

    public function headings(): array
    {
        $headers = [];
        $headers[] = ['Property: ' . $this->property];
        
        if (!empty($this->filters)) {
            $filtersText = 'Filters Applied: ';
            foreach ($this->filters as $key => $value) {
                $filtersText .= ucfirst((string) $key) . ': ' . $value . ' | ';
            }
            $headers[] = [rtrim($filtersText, ' | ')];
        } else {
            $headers[] = ['Filters Applied: None'];
        }
        $headers[] = ['']; // Empty row

        // Add the 'No.' column for our sequential number
        $headers[] = [
            'No.',
            'Tag',
            'Asset Name',
            'Category',
            'Department',
            'Status',
            'Serial Number',
            'Model',
            'Purchase Date',
            'Warranty Date',
            'Purchase Cost',
            'Vendor',
            'Remarks',
        ];

        return $headers;
    }

    /**
     * @param  mixed  $asset
     */
    public function map($asset): array
    {
        // Map the data and add the incrementing row number
        return [
            ++$this->rowIndex, // This is our sequential number
            $asset->tag,
            $asset->name,
            $asset->category?->name ?? 'N/A',
            $asset->department?->name ?? 'N/A',
            $asset->status,
            $asset->serial_number,
            $asset->model,
            $asset->purchase_date ? $asset->purchase_date->format('Y-m-d') : 'N/A',
            $asset->warranty_date ? $asset->warranty_date->format('Y-m-d') : 'N/A',
            $asset->purchase_cost,
            $asset->vendor,
            $asset->remarks,
        ];
    }
}
