/**
*   Add an item to the shopping cart.
*
*   @param  object  cbox    Checkbox
*   @param  string  id      Sitemap ID, e.g. plugin name
*   @param  string  type    Type of sitemap (XML or HTML)
*/
var ppAddToCart = function() {
    data = $("form").serialize();
    $.ajax({
        type: "POST",
        dataType: "text",
        url: glfusionSiteUrl + "/paypal/ajax.php?action=addcartitem",
        data: data,
        success: function(result) {
            // Set the ID of the shopping cart div
            divid = document.getElementById("ppCartBlockContents");
            if (result != '' && typeof(divid) != "undefined") {
                divid.innerHTML = result;
            } 
        }
    });
    return false;
};

