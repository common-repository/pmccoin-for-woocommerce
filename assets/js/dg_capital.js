let confirm = false;
let updating_php_vars = false;
if (php_vars.ga_tag_id) {
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag("js", new Date());

    gtag("config", php_vars.ga_tag_id);
}
jQuery('form.checkout').on('checkout_place_order',function() {
    if (jQuery('#payment_method_' + php_vars.id).is(':checked') && !confirm) {
        let swalOptions = {};

        if ('redirect' == php_vars.plugin_payment_mode) {
            if (php_vars.ga_tag_id) {
                gtag('event', 'click', { event_category: 'plugin-actions', event_label: 'Place Order with ' + php_vars.short_name });
            }
            swalOptions = {
              html:
                    '<p class="font-13">' +
                        'You will now be redirected to ' + php_vars.short_name + 
                        ' to either use your ' + php_vars.short_name + 
                        ' balance or Purchase additional ' + php_vars.short_name + 
                        ' to hold or spend.' +
                    '</p>'+
                    '<p class="pt-1 font-13">Simple checkout with ' + php_vars.short_name + ' </p>',
              showCancelButton: true,
              confirmButtonColor: "#3085D6",
              cancelButtonColor: "#029ed0",
              confirmButtonText: "Yes, Proceed to " + php_vars.short_name,
              cancelButtonText: 'Return to Shopping Cart' ,
              imageUrl: php_vars.plugin_icon,
              imageWidth: 140,
              imageHeight: 35,
              width: "450px"
            };
        }

        Swal.fire(swalOptions).then((result) => {
            if (result.value) {
                if (php_vars.ga_tag_id) {
                    gtag('event', 'click', { event_category: 'plugin-actions', event_label: 'Popup - Yes, Proceed with ' + php_vars.short_name });
                }
                confirm = true;
                jQuery('form.checkout').submit();
            } else {
                if (php_vars.ga_tag_id) {
                    gtag('event', 'click', { event_category: 'plugin-actions ', event_label: 'Popup - Cancel' });
                }
            }
        });
        return false;
    }else{
        return true;
    }
});

function applyPostCodeField() {
    setTimeout(() => {
        if (jQuery('#billing_postcode_field').css('display') == 'none' && jQuery('#payment_method_' + php_vars.id).is(':checked')) {
            jQuery('#billing_postcode_field').css('display', 'block');
        }
    }, 300);
}

function get_dg_capital_php_vars() {
    jQuery.get(window.location.origin + '?wc-ajax=get_dg_capital_php_vars', function(data) {
        try {
            php_vars = JSON.parse(data);
            
            let total_el             = jQuery('.order-total >');
            php_vars.total_amount    = parseFloat(parseFloat((total_el.text().replace(/\D+/g, '')) / 100)).toFixed(2);
            php_vars.token_quantity  = ((php_vars.total_amount * 100) / php_vars.gold_price).toFixed(9);
        } catch (error) {
            console.error(error);
        }
    });
}

jQuery("#billing_country").blur(function() {
    applyPostCodeField();
});

jQuery('body').bind('checkout_error', function() {
    confirm = false;
});

jQuery('body').bind('update_checkout', function() {
    if ('redirect' == php_vars.plugin_payment_mode) return;
    
    setTimeout(() => {
        if (!updating_php_vars) {
            updating_php_vars = true;
            get_dg_capital_php_vars();
        }
    }, 2000);
    updating_php_vars = false;
});

jQuery('form.checkout').on('change', 'input[name^="payment_method"], #billing_country', function() {
    jQuery('body').trigger('update_checkout');
});

this.applyPostCodeField();
