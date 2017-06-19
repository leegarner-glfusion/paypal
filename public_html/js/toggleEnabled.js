/**
*   Toggle field for Paypal products
*
*   @param  object  cbox    Checkbox
*   @param  string  id      Sitemap ID, e.g. plugin name
*   @param  string  type    Type of sitemap (XML or HTML)
*/
var PP_toggle = function(cbox, id, type, component) {
    oldval = cbox.checked ? 0 : 1;
    var dataS = {
        "action" : "toggle",
        "id": id,
        "type": type,
        "oldval": oldval,
        "component": component,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/paypal/ajax.php",
        data: data,
        success: function(result) {
            cbox.checked = result.newval == 1 ? true : false;
            try {
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
    return false;
};

var PP_status = {};

function PP_updateOrderStatus(order_id, oldstatus, newstatus, showlog)
{
    var dataS = {
        "action" : "updatestatus",
        "order_id": order_id,
        "newstatus": newstatus,
        "showlog": showlog,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/paypal/ajax.php",
        data: data,
        success: function(jsonObj) {
            try {
                if (jsonObj.showlog == 1) {
                    var tbl = document.getElementById("paypalOrderLog");
                    if (tbl) {
                        var lastRow = tbl.rows.length;
                        var iteration = lastRow;
                        var row = tbl.insertRow(lastRow);

                        var cell0 = row.insertCell(0);
                        var textNode = document.createTextNode(jsonObj.ts);
                        cell0.appendChild(textNode);

                        var cell1 = row.insertCell(1);
                        var textNode = document.createTextNode(jsonObj.username);
                        cell1.appendChild(textNode);
                        var cell2 = row.insertCell(2);
                        var textNode = document.createTextNode(jsonObj.message);
                        cell2.appendChild(textNode);
                    }
                }
                var el = document.getElementById("statSelect_" + jsonObj.order_id);
                el.value = jsonObj.newstatus;

                // Hide the button and update the new status in our array
                document.getElementById("ppSetStat_" + jsonObj.order_id).style.visibility = "hidden";
                PP_setStatus(jsonObj.order_id, jsonObj.newstatus);
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                //alert(result.statusMessage);
            }
        }
    });
    return false;
}


/*  Show the "update status" submit button if the order status selection has
    changed.
*/
function PP_ordShowStatSubmit(order_id, oldvalue, newvalue)
{
    var el = document.getElementById("ppSetStat_" + order_id);
    if (el) {
        if (newvalue != oldvalue) {
            el.style.visibility = '';
        } else {
            el.style.visibility = 'hidden';
        }
    }
}

function PP_setStatus(order_id, newstatus)
{
    PP_status[order_id] = newstatus;
    sel = document.getElementById("statSelect_" + order_id);
    if (sel != null) {
        sel.selected = newstatus;
    }
}

function PP_getStatus(order_id)
{
    return PP_status[order_id];
}
