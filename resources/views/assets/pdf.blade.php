<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assets Export - {{ $propertyName }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; color: #333; }
        .header { margin-bottom: 15px; border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        .title { font-size: 16px; font-weight: bold; margin: 0 0 5px 0; }
        .info { font-size: 12px; margin: 0 0 5px 0; }
        .filters { font-size: 11px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; color: #333; }
        tr:nth-child(even) { background-color: #fdfdfd; }
        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 30px; font-size: 9px; color: #888; text-align: center; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        Generated on {{ date('Y-m-d H:i') }} | Page <span class="page-number"></span>
    </div>

    <div class="header">
        <h1 class="title">Assets Export</h1>
        <p class="info">Property: {{ $propertyName }}</p>
        <div class="filters">
            @if(!empty($filters))
                <strong>Filters Applied:</strong>
                @foreach($filters as $key => $value)
                    {{ ucfirst($key) }}: {{ $value }}@if(!$loop->last) | @endif
                @endforeach
            @else
                <strong>Filters Applied:</strong> None
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Tag</th>
                <th>Name</th>
                <th>Category</th>
                <th>Department</th>
                <th>Status</th>
                <th>Serial Number</th>
                <th>Model</th>
                <th>Purchase<br>Date</th>
                <th>Warranty<br>Date</th>
                <th>Vendor</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assets as $index => $asset)
            <tr>
                <td style="width: 20px;">{{ $index + 1 }}</td>
                <td style="width: 60px;">{{ $asset->tag }}</td>
                <td style="width: 120px;">{{ $asset->name }}</td>
                <td style="width: 70px;">{{ $asset->category?->name ?? 'N/A' }}</td>
                <td style="width: 70px;">{{ $asset->department?->name ?? 'N/A' }}</td>
                <td style="width: 60px;">{{ str_replace('_', ' ', ucfirst($asset->status)) }}</td>
                <td style="width: 70px;">{{ $asset->serial_number }}</td>
                <td style="width: 70px;">{{ $asset->model }}</td>
                <td style="width: 60px;">{{ $asset->purchase_date ? $asset->purchase_date->format('Y-m-d') : 'N/A' }}</td>
                <td style="width: 60px;">{{ $asset->warranty_date ? $asset->warranty_date->format('Y-m-d') : 'N/A' }}</td>
                <td style="width: 80px;">{{ $asset->vendor }}</td>
                <td>{{ $asset->remarks }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
