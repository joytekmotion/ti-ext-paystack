+(function ($) {
    "use strict";

    const ProcessDnaPayments = function (element, options) {
        this.$el = $(element);
        this.options = options || {};

        this.paymentInputSelector = 'input[name=paystack]'

        this.init();
    };

    ProcessDnaPayments.prototype.init = function () {
        // find delete button with id delete-payment-profile
        this.$el.find('.delete-payment-profile-btn').on('click', this.deletePaymentProfile);
    };

    ProcessDnaPayments.prototype.deletePaymentProfile = function (e) {
        e.preventDefault();

        const $this = $(this);
        const paymentProfileId = $this.data("payment-profile-id");
        const customerId = $this.data("customer-id");

        if (!paymentProfileId) {
            return;
        }
        $(".checkout-btn").prop("disabled", true);
        $.ajax({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            url: "/ti_payregister/dnapayments_delete_payment_profile/" + paymentProfileId,
            method: "POST",
            success: function (data) {
                if ($.trim(data)) {
                    // delete payment profile div when it is the last one
                    if ($this.closest('.payment-profile-item').siblings().length <= 1) {
                        $this.closest('.payment-profile-item').closest('.payment-form').remove();
                    } else {
                        $this.closest('.payment-profile-item').slideUp(500, function () {
                            $(this).remove();
                        });
                    }
                }
            },
            error: function (xhr, status, error) {
            },
            complete: function () {
                $(".checkout-btn").prop("disabled", false);
            },
        });
    };

    ProcessDnaPayments.prototype.triggerPaymentInputChange = function ($el) {
        const paymentInputSelector = this.paymentInputSelector + '[value=' + $el.data('paymentCode') + ']';
        setTimeout(function () {
            $(paymentInputSelector, document).prop('checked', true).trigger('change')
        }, 1)
    }

    ProcessDnaPayments.DEFAULTS = {};

    // PLUGIN DEFINITION
    // ============================

    var old = $.fn.processDnaPayments;

    $.fn.processDnaPayments = function (option) {
        var $this = $(this).first();
        var options = $.extend(
            true,
            {},
            ProcessDnaPayments.DEFAULTS,
            $this.data(),
            typeof option == "object" && option
        );

        return new ProcessDnaPayments($this, options);
    };

    $.fn.processDnaPayments.Constructor = ProcessDnaPayments;

    $.fn.processDnaPayments.noConflict = function () {
        $.fn.processDnaPayments = old;
        return this;
    };

    $(document).render(function () {
        $("#dnapaymentsForm").processDnaPayments();
    });
})(window.jQuery);
