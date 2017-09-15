/**
*   Add an item to the shopping cart.
*/
var ppAddToCart = function(frm_id) {
    data = $("#"+frm_id).serialize();
    $.ajax({
        type: "POST",
        dataType: "json",
        url: glfusionSiteUrl + "/paypal/ajax.php?action=addcartitem",
        data: data,
        success: function(result) {
            try {
                if (result.content != '') {
                    // Update the shopping cart block if it is displayed
                    divid = document.getElementById("ppCartBlockContents");
                    if (divid != undefined) {
                        divid.innerHTML = result.content;
                        if (result.unique) {
                            var btn_id = frm_id + '_add_cart_btn';
                            document.getElementById(btn_id).disabled = true;
                            document.getElementById(btn_id).className = 'paypalButton grey';
                        }
                    }
                    $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
                    // If a return URL is provided, redirect to that page
                    if (result.ret_url != '') {
                        window.location.href = result.ret_url;
                    }
                } 
            } catch(err) {
            }
            blk_setvis_paypal_cart(result.content == "" ? "none" : "block");
        }
    });
    return false;
};

function blk_setvis_paypal_cart(newvis)
{
    blk = document.getElementById("paypal_cart");
    if (typeof(blk) != 'undefined' && blk != null) {
        blk.style.display = newvis;
    }
}
