@extends('layouts.app')

@section('title', 'Edit Expense')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <form id="expense-form" action="{{ route('expenses.update', $expense) }}" method="POST">
            @csrf
            @method('patch')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required value="{{ $expense->reference }}" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ $expense->getAttributes()['date'] }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="category_id">Category <span class="text-danger">*</span></label>
                                        <select name="category_id" id="category_id" class="form-control" required>
                                            @php
                                                $cats = \Modules\Expense\Entities\ExpenseCategory::select('id','category_name')
                                                    ->get()
                                                    ->sortBy(function($c){ return $c->category_name; }, SORT_NATURAL|SORT_FLAG_CASE)
                                                    ->values();
                                            @endphp
                                            @foreach($cats as $category)
                                                <option {{ $category->id == $expense->category_id ? 'selected' : '' }} value="{{ $category->id }}">{{ $category->category_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="amount">Amount <span class="text-danger">*</span></label>
                                        <input id="amount_display" type="text" class="form-control currency-input" maxlength="15" inputmode="decimal" placeholder="0.00" data-target="#amount_raw" aria-label="Amount" value="{{ number_format($expense->amount ?? 0, 2, '.', '') }}">
                                        <input type="hidden" id="amount_raw" name="amount" value="{{ number_format($expense->amount ?? 0, 2, '.', '') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="payment_mode">Payment Mode <span class="text-danger">*</span></label>
                                        <select name="payment_mode" id="payment_mode" class="form-control" required>
                                            <option value="Cash" {{ ($expense->payment_mode ?? 'Cash') == 'Cash' ? 'selected' : '' }}>Cash</option>
                                            <option value="Cheque" {{ ($expense->payment_mode ?? '') == 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                            <option value="Cards" {{ ($expense->payment_mode ?? '') == 'Cards' ? 'selected' : '' }}>Cards</option>
                                            <option value="Bank Transfer" {{ ($expense->payment_mode ?? '') == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                            <option value="UPI Payment" {{ ($expense->payment_mode ?? '') == 'UPI Payment' ? 'selected' : '' }}>UPI Payment</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="details">Details</label>
                                <textarea class="form-control" rows="6" name="details">{{ $expense->details }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-end">
                <a href="{{ route('expenses.index') }}" class="btn btn-secondary mr-2">Back</a>
                <button class="btn btn-primary">Update Expense <i class="bi bi-check"></i></button>
            </div>
        </form>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
    <script>
        $(function(){
            try { if (window.currencyInputInit) window.currencyInputInit(); } catch(e) {}
            // ensure hidden raw value is present before submit
            $('#expense-form').on('submit', function(){
                try { var v = $('#amount_raw').val() || $('#amount_display').val() || ''; $('#amount_raw').val(v); } catch(e) {}
            });
        });
    </script>
@endpush
