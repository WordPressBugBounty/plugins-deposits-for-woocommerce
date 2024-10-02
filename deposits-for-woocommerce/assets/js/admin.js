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
    });





})(jQuery);

// Other code using $ as an alias to the other library