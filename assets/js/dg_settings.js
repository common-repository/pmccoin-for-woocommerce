var id = settings_php_vars.id;
var ach=jQuery('input[id="woocommerce_'+ id +'_ach"]').parent('label');
var zelle=jQuery('input[id="woocommerce_'+ id +'_zelle"]').parent('label');
var ach_zelle = (
    "<label for='woocommerce_"+ id +"_ach' style='padding-right: 15px;'>"+ach.html()+"</label>"+
    "<label for='woocommerce_"+ id +"_zelle'>"+zelle.html()+"</label>"+
    "<p class='description'>This controls the description which the user sees during checkout</p>"
);
ach.parent('fieldset').html(ach_zelle);
zelle.closest('tr').remove();
