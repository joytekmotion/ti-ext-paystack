@if(count($paymentMethod->getPaymentProfiles($order->customer)))
    <div id="dnapaymentsForm" class="payment-form w-100">
        <div class="form-group">
            <label class="fw-bold text-uppercase text-decoration-underline">@lang('foodninjas.paystack::default.text_saved_cards')</label>
            @foreach($paymentMethod->getPaymentProfiles($order->customer) as $paymentProfile)
                <div class="payment-profile-item">
                    <i class="fab fa-fw fa-cc-{{ strtolower($paymentProfile->card_brand) }}"></i>&nbsp;&nbsp;
                    <b>&bull;&bull;&bull;&bull;&nbsp;&bull;&bull;&bull;&bull;&nbsp;&bull;&bull;&bull;&bull;&nbsp;{{ $paymentProfile->card_last4 }}</b>
                    &nbsp;&nbsp;-&nbsp;&nbsp;
                    <a
                        class="text-danger delete-payment-profile-btn"
                        href="javascript:;"
                        data-payment-profile-id="{{ $paymentProfile->payment_profile_id }}"
                    >@lang('foodninjas.paystack::default.text_delete')</a>
                </div>
            @endforeach
        </div>
    </div>
@endif
