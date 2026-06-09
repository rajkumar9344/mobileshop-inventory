@extends('layouts.app')

@section('title', 'Adjustment Details')

@push('page_css')
    @livewireStyles
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('adjustments.index') }}">Adjustments</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Adjustment Details</h3>
                        <a href="{{ route('adjustments.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr>
                                    <th colspan="2">
                                        Date
                                    </th>
                                    <th colspan="2">
                                        Reference
                                    </th>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        {{ $adjustment->date }}
                                    </td>
                                    <td colspan="2">
                                        {{ $adjustment->reference }}
                                    </td>
                                </tr>

                                <tr>
                                    <th>Product Name</th>
                                    <th>Code</th>
                                    <th>Open Qty (Current)</th>
                                    <th>Quantity</th>
                                    <th>Open Qty (After Adj)</th>
                                    <th>Type</th>
                                </tr>

                                @php
                                    $__prod_ids = $adjustment->adjustedProducts->pluck('product_id')->filter()->unique()->values()->all();
                                    $__codes_map = [];
                                    if (!empty($__prod_ids)) {
                                        $__codes = \Modules\Product\Entities\ProductCode::whereIn('product_id', $__prod_ids)
                                            ->orderByDesc('is_primary')
                                            ->get()
                                            ->groupBy('product_id');
                                        foreach ($__codes as $__pid => $__group) {
                                            $__codes_map[$__pid] = $__group->pluck('code')->unique()->values()->toArray();
                                        }
                                    }
                                @endphp

                                @foreach($adjustment->adjustedProducts as $adjustedProduct)
                                    @php
                                        $productOpen = optional($adjustedProduct->product)->open_quantity;
                                        $openNow = $adjustedProduct->open_now ?? $productOpen ?? 0;
                                        $openAfter = $adjustedProduct->open_after ?? ($adjustedProduct->type === 'sub' ? ($openNow - $adjustedProduct->quantity) : ($openNow + $adjustedProduct->quantity));
                                    @endphp
                                    <tr>
                                        <td>{{ optional($adjustedProduct->product)->product_name ?? $adjustedProduct->product_name ?? 'N/A' }}</td>
                                        <td>
                                            @php $pid = $adjustedProduct->product_id; @endphp
                                            @if(!empty($__codes_map[$pid]))
                                                {{ implode(', ', $__codes_map[$pid]) }}
                                            @else
                                                {{ optional($adjustedProduct->product)->product_code ?? $adjustedProduct->product_code ?? 'N/A' }}
                                            @endif
                                        </td>
                                        <td>{{ $openNow }}</td>
                                        <td>{{ $adjustedProduct->quantity }}</td>
                                        <td>{{ $openAfter }}</td>
                                        <td>
                                            @if($adjustedProduct->type == 'add')
                                                (+) Addition
                                            @else
                                                (-) Subtraction
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
