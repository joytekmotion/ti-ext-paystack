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
        $.ajax({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            url: "/ti_payregister/paystack_initialize_transaction/handle",
            method: "POST",
            success: function (authData) {
                const popup = new PaystackPop();
                popup.resumeTransaction(authData.access_code, {
                    onCancel: function() {
                        window.location.reload();
                    },
                    onSuccess: function(transaction) {
                        self.paymentSuccess(authData.order_hash, transaction);
                    },
                    onError: function (error) {
                        console.error('An error occurred: ', error);
                    }
                });
            },
            error: function (xhr, status, error) {
                console.error('error', error);
            },
            completed: function () {
                $(".checkout-btn").prop("disabled", false);
            }
        });
    }

    ProcessPaystack.prototype.paymentSuccess = function (orderHash, transaction) {
        $.ajax({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            url: "/ti_payregister/paystack_payment_successful/" + orderHash,
            method: "POST",
            data: transaction,
            success: function() {
                window.location.reload();
            },
            error: function (xhr, status, error) {
                console.error('error', error);
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
