+(function ($) {
    "use strict";

    const ProcessPaystack = function (element, options) {
        this.$el = $(element);
        this.options = options || {};
        this.$checkoutForm = this.$el.closest("#checkout-form");

        this.init();
    };

    ProcessPaystack.prototype.init = function () {
        this.$checkoutForm.on("submitCheckoutForm", this.processPayment.bind(this));
    };

    ProcessPaystack.prototype.processPayment = function (e) {
        const payFromProfile = this.$checkoutForm.find('input[name="pay_from_profile"]:checked').val();
        if(payFromProfile > 0) return;

        if(this.options.integrationType === 'redirect') return;

        e.preventDefault();
        const self = this;
        if (!self.options.orderCreated) {
            self.$checkoutForm.request(self.$checkoutForm.data('handler'))
                .done(() => {
                    self.handlePayment();
                });
        } else {
            self.handlePayment();
        }
    };

    ProcessPaystack.prototype.handlePayment = function () {
        const self = this;
        const createPaymentProfile =
            self.$checkoutForm.find('input[name="create_payment_profile"]').is(':checked') ? 1 : 0;

        $.ajax({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            url: "/ti_payregister/paystack_initialize_transaction/handle",
            data: {
                'create_payment_profile': createPaymentProfile,
            },
            method: "POST",
            success: function (authData) {
                const popup = new PaystackPop();
                popup.resumeTransaction(authData.access_code, {
                    onCancel: function() {
                        window.location.reload();
                    },
                    onSuccess: function(transaction) {
                        self.paymentSuccess(transaction);
                    },
                    onError: function (error) {
                        console.error('An error occurred: ', error);
                    }
                });
            },
            error: function (xhr, status, error) {
                console.error('error', error);
                $(".checkout-btn").prop("disabled", false);
            }
        });
    }

    ProcessPaystack.prototype.paymentSuccess = function (transaction) {
        $.ajax({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            url: "/ti_payregister/paystack_payment_successful/handle",
            method: "POST",
            data: transaction,
            complete: function() {
                window.location.reload();
            }
        });
    }

    ProcessPaystack.DEFAULTS = {};

    // PLUGIN DEFINITION
    // ============================

    var old = $.fn.processPaystack;

    $.fn.processPaystack = function (option) {
        var $this = $(this).first();
        var options = $.extend(
            true,
            {},
            ProcessPaystack.DEFAULTS,
            $this.data(),
            typeof option == "object" && option
        );

        return new ProcessPaystack($this, options);
    };

    $.fn.processPaystack.Constructor = ProcessPaystack;

    $.fn.processPaystack.noConflict = function () {
        $.fn.processPaystack = old;
        return this;
    };

    $(document).render(function () {
        $("#paystackForm").processPaystack();
    });
})(window.jQuery);
