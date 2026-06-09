@extends('layouts.app')

@section('title', 'Edit Payment')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->reference }}</a></li>
        <li class="breadcrumb-item active">Edit Payment</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <form id="payment-form" action="{{ route('purchase-payments.update', $purchasePayment) }}" method="POST">
            @csrf
            @method('patch')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                    <div class="form-group">
                        <button class="btn btn-primary">Update Payment <i class="bi bi-check"></i></button>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required readonly value="{{ $purchasePayment->reference }}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ $purchasePayment->getAttributes()['date'] }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="due_amount">Due Amount <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="due_amount" required value="{{ format_currency($purchase->due_amount) }}" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="amount">Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <x-currency-input
                                                id="amount"
                                                name="amount_display"
                                                hiddenName="amount"
                                                hiddenId="amount_hidden"
                                                :value="old('amount') ?? number_format($purchasePayment->amount, 2, '.', '')"
                                                required
                                                symbol="{{ settings()->currency->symbol }}"
                                                position="{{ settings()->default_currency_position }}"
                                            />
                                            <div class="input-group-append">
                                                <button id="getTotalAmount" class="btn btn-primary" type="button">
                                                    <i class="bi bi-check-square"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                            <select class="form-control" name="payment_method" id="payment_method" required>
                                                <option {{ $purchasePayment->payment_method == 'Cash' ? 'selected' : '' }} value="Cash">Cash</option>
                                                <option {{ $purchasePayment->payment_method == 'Credit Card' ? 'selected' : '' }} value="Credit Card">Credit Card</option>
                                                <option {{ $purchasePayment->payment_method == 'Bank Transfer' ? 'selected' : '' }} value="Bank Transfer">Bank Transfer</option>
                                                <option {{ $purchasePayment->payment_method == 'Cheque' ? 'selected' : '' }} value="Cheque">Cheque</option>
                                                <option {{ $purchasePayment->payment_method == 'Other' ? 'selected' : '' }} value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="note">Note</label>
                                <textarea class="form-control" rows="4" name="note">{{ old('note') ?? $purchasePayment->note }}</textarea>
                            </div>

                            <input type="hidden" value="{{ $purchase->id }}" name="purchase_id">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            if (window.currencyInputInit) window.currencyInputInit();

            // Initialize displayed value from dataset.raw if present
            var amtEl = document.getElementById('amount');
            if (amtEl && amtEl.dataset && amtEl.dataset.raw) {
                amtEl.value = amtEl.dataset.raw;
            }

            $('#getTotalAmount').click(function () {
                var due = {{ $purchase->due_amount }} || 0;
                var displayEl = document.getElementById('amount');
                if (displayEl) {
                    var formatted = parseFloat(String(due)).toFixed(2);
                    displayEl.value = formatted;
                    displayEl.dataset.raw = formatted;
                    displayEl.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            $('#payment-form').submit(function () {
                // x-currency-input syncs the hidden numeric field automatically
                return true;
            });
        });
    </script>
@endpush

