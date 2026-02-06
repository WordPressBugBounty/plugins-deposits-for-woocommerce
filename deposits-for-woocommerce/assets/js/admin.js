(function ($) {
    'use strict';


    $(document).ready(function () {

        var select_val = $('select#product-type').val();
        console.log(select_val);
        if ('variable' === select_val) {
            $('#woo_desposits_options').find('.deposit-simple-options').addClass('hide-if-variable');
            $('#woo_desposits_options').find('.deposit-variation-option').show();
        } else {
            $('#woo_desposits_options').find('.deposit-simple-options').removeClass('hide-if-variable');
            $('#woo_desposits_options').find('.deposit-variation-option').hide();
        }
        // Product type specific options.
        $('select#product-type').on('change', function () {

            // Get value.
            var select_val = $(this).val();

            if ('variable' === select_val) {
                $('#woo_desposits_options').find('.deposit-simple-options').addClass('hide-if-variable');
                $('#woo_desposits_options').find('.deposit-variation-option').show();
            } else {
                $('#woo_desposits_options').find('.deposit-simple-options').removeClass('hide-if-variable');
                $('#woo_desposits_options').find('.deposit-variation-option').hide();
            }

        });
        $(".calculate-deposit-action").on("click", function () {

            $(this).modal({
                clickClose: false,
            });
        });
        $('#deposit-edit-wrapper').on($.modal.OPEN, function (event, modal) {
            console.log('Modal opened');
        });
        $("#update-deposit-order").on("click", function () {

            var $wrapper = $('#deposit-edit-wrapper');
            var deposit_type = $wrapper.find('select[name="order_deposits_type"]');
            var deposit_value = $wrapper.find('input[name="order_deposit_value"]');
            var deposit_plan = $wrapper.find('select[name="order_deposit_plan"]');
            $(this).prop('disabled', true);

            jQuery.ajax({
                url: dfwc_admin_vars.ajax_url,
                type: "POST",
                data: {
                    //action name (must be consistent with your php callback)
                    action: 'bayna_update_deposit_order',
                    order_id: $(this).data('order-id'),
                    deposit_type: deposit_type.val(),
                    deposit_value: deposit_value.val(),
                    deposit_plan: deposit_plan.val(),
                    nonce: dfwc_admin_vars.ajax_nonce
                },
                async: false,
                success: function (data) {
                    $("#update-deposit-order").prop('disabled', false);
                    console.log(data);
                    $("#deposit-edit-wrapper .csf-notice").remove();
                    if (data.success) {
                        $("#deposit-edit-wrapper").prepend('<div class="csf-notice csf-notice-success">' + data.data + '</div>');
                        // Reload the page to reflect changes
                        setTimeout(function () {
                            location.reload();
                        }, 3000);
                    } else {
                        // Handle error
                        $("#deposit-edit-wrapper").prepend('<div class="csf-notice csf-notice-danger">' + data.data + '</div>');

                    }

                }
            });
        });
        $(".remove-deposit-action").on("click", function () {
            var answer = confirm(dfwc_admin_vars.i18n.confirm);
            if (answer) {
                /*Ajax request URL being stored*/
                jQuery.ajax({
                    url: dfwc_admin_vars.ajax_url,
                    type: "POST",
                    data: {
                        //action name (must be consistent with your php callback)
                        action: 'bayna_remove_deposit',
                        order_id: $(this).data('order-id'),
                        nonce: dfwc_admin_vars.ajax_nonce
                    },
                    async: false,
                    success: function (data) {
                        location.reload();
                    }
                });

                return true;
            }
            return false;
        });
    });





})(jQuery);

// Other code using $ as an alias to the other library