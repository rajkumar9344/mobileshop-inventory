@extends('layouts.app')

@section('title', 'POS')

@section('third_party_stylesheets')

@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">POS</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                @include('utils.alerts')
                <div id="credit-limit-warning" class="alert alert-danger d-none">
                    <strong>Warning:</strong> Credit limit exceeded for this customer. Please settle outstanding dues before proceeding.
                </div>
            </div>
            <div class="col-lg-7">
                <livewire:search-product :context="'sale'"/>
                <livewire:pos.product-list :categories="$product_categories"/>
            </div>
            <div class="col-lg-5">
                <livewire:pos.checkout :cart-instance="'sale'" :customers="$customers"/>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/jquery-mask-money.js') }}"></script>
    <script>
        $(document).ready(function () {
            window.addEventListener('showCheckoutModal', event => {
                $('#checkoutModal').modal('show');

                $('#paid_amount').maskMoney({
                    prefix:'{{ settings()->currency->symbol }}',
                    thousands:'{{ settings()->currency->thousand_separator }}',
                    decimal:'{{ settings()->currency->decimal_separator }}',
                    allowZero: false,
                });

                $('#total_amount').maskMoney({
                    prefix:'{{ settings()->currency->symbol }}',
                    thousands:'{{ settings()->currency->thousand_separator }}',
                    decimal:'{{ settings()->currency->decimal_separator }}',
                    allowZero: true,
                });

                $('#paid_amount').maskMoney('mask');
                $('#total_amount').maskMoney('mask');

                // Check credit limit when modal opens
                checkCreditLimit();

                // Check credit limit on customer change
                $('#customer_id').on('change', function() {
                    checkCreditLimit();
                });

                // Check credit limit on paid amount change
                $('#paid_amount').on('keyup change blur', function() {
                    checkCreditLimit();
                });

                $('#checkout-form').submit(function (e) {
                    var paid_amount = $('#paid_amount').maskMoney('unmasked')[0];
                    var total_amount = $('#total_amount').maskMoney('unmasked')[0];

                    // Synchronous credit limit check before submission
                    var customerId = $('#customer_id').val();
                    if (customerId) {
                        var creditLimitReached = false;
                        
                        $.ajax({
                            url: '{{ route("app.pos.check-credit-limit") }}',
                            method: 'GET',
                            async: false, // Synchronous to block form submission
                            data: {
                                customer_id: customerId,
                                total_amount: total_amount,
                                paid_amount: paid_amount
                            },
                            success: function(response) {
                                creditLimitReached = response.credit_limit_reached;
                            }
                        });
                        
                        if (creditLimitReached) {
                            $('#credit-limit-warning').removeClass('d-none');
                            alert('Credit Limit reached for this Customer. Please settle outstanding dues before proceeding.');
                            e.preventDefault();
                            return false;
                        }

                        // Prevent overpayment: paid amount must not exceed total/net amount
                        if (paid_amount > total_amount) {
                            alert('Amount Received cannot be more than Net Rate.');
                            // focus paid amount input in modal
                            setTimeout(function(){ $('#paid_amount').focus(); }, 50);
                            e.preventDefault();
                            return false;
                        }
                    }

                    $('#paid_amount').val(paid_amount);
                    $('#total_amount').val(total_amount);
                });
            });

            // Function to check credit limit via AJAX
            function checkCreditLimit() {
                var customerId = $('#customer_id').val();
                if (!customerId) {
                    $('#credit-limit-warning').addClass('d-none');
                    return;
                }
                
                var totalAmount = $('#total_amount').maskMoney('unmasked')[0] || 0;
                var paidAmount = $('#paid_amount').maskMoney('unmasked')[0] || 0;
                
                $.ajax({
                    url: '{{ route("app.pos.check-credit-limit") }}',
                    method: 'GET',
                    data: {
                        customer_id: customerId,
                        total_amount: totalAmount,
                        paid_amount: paidAmount
                    },
                    success: function(response) {
                        if (response.credit_limit_reached) {
                            $('#credit-limit-warning').removeClass('d-none');
                        } else {
                            $('#credit-limit-warning').addClass('d-none');
                        }
                    },
                    error: function() {
                        $('#credit-limit-warning').addClass('d-none');
                    }
                });
            }
        });
    </script>

@endpush
