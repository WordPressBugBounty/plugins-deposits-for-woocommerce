(function ($) {
    'use strict';
   
    $(document).ready(function () {

        function deposit_variaiton_markup() {
            var variation_form = $('.variations_form'),
                i = 'input.variation_id',
                product_wrap = $('.postid-' + deposits_params.product_id),
                bayna_variation_list = deposits_params.variation_markup,
                DivParent = product_wrap.find('.single_variation_wrap');
            variation_form.on('found_variation', function (event, variation) {
                
                // if bayna_variation_list is null or undefined
                if (bayna_variation_list == null || bayna_variation_list == undefined) {
                    return;
                }
                console.log(bayna_variation_list);
                
                DivParent.find('.deposits-frontend-wrapper').remove();
                if (bayna_variation_list.hasOwnProperty(variation.variation_id)) {
                    console.log(variation.variation_id);
                    
                    DivParent.prepend(bayna_variation_list[variation.variation_id]);



                }


            })
                // On clicking the reset variation button
                .on('reset_data', function (event) {
                    DivParent.find('.deposits-frontend-wrapper').remove();

                });

        }
        
        deposit_variaiton_markup();
       
        console.log('Hello from public.js');


    });


})(jQuery);

// Other code using $ as an alias to the other library