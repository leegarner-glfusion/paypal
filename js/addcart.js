/**
*   Add an item to the shopping cart.
*/
var ppAddToCart = function() {
    data = $("form").serialize();
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
                    }
                    $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
                } 
            } catch(err) {
            }
        }
    });
    return false;
};
