/*  Updates submission form fields based on changes in the category
 *  dropdown.
 */
var xmlHttp;
function PP_toggle(ckbox, id, type, component, base_url)
{
  xmlHttp=PPgetXmlHttpObject();
  if (xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }

  // value is reversed since we send the oldvalue to ajax
  var oldval = ckbox.checked == true ? 0 : 1;
  var url=base_url + "/ajax.php?action=toggle";
  url=url+"&id="+id;
  url=url+"&type="+type;
  url=url+"&component="+component;
  url=url+"&oldval="+oldval;
  url=url+"&sid="+Math.random();
  xmlHttp.onreadystatechange=PPstateChanged;
  xmlHttp.open("GET",url,true);
  xmlHttp.send(null);
}

function PPstateChanged()
{
  var newstate;

  if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete")
  {
    xmlDoc=xmlHttp.responseXML;
    id = xmlDoc.getElementsByTagName("id")[0].childNodes[0].nodeValue;
    //imgurl = xmlDoc.getElementsByTagName("imgurl")[0].childNodes[0].nodeValue;
    baseurl = xmlDoc.getElementsByTagName("baseurl")[0].childNodes[0].nodeValue;
    type = xmlDoc.getElementsByTagName("type")[0].childNodes[0].nodeValue;
    component = xmlDoc.getElementsByTagName("component")[0].childNodes[0].nodeValue;
    if (xmlDoc.getElementsByTagName("newval")[0].childNodes[0].nodeValue == 1) {
        newval = 1;
        document.getElementById("tog"+type+id).checked = true;
    } else {
        newval = 0;
        document.getElementById("tog"+type+id).checked = false;
    }
    /*newHTML = 
        "<img src=\""+imgurl+"\" " +
        "width=\"16\" height=\"16\" " +
        "onclick='PP_toggle("+newval+", \""+id+"\", \""+type+"\", \""+component+"\", \""+baseurl+"\");" +
        "'>";
    document.getElementById("tog"+type+id).innerHTML = newHTML;*/
  }

}

function PPgetXmlHttpObject()
{
  var objXMLHttp=null
  if (window.XMLHttpRequest)
  {
    objXMLHttp=new XMLHttpRequest()
  }
  else if (window.ActiveXObject)
  {
    objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP")
  }
  return objXMLHttp
}


var PP_status = new Array();

function PP_updateOrderStatus(order_id, oldstatus, newstatus, showlog, base_url)
{
  xmlHttp=PPgetXmlHttpObject();
  if (xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }

  var url=base_url + "/ajax.php?action=updatestatus";
  url=url+"&order_id="+order_id;
  url=url+"&newstatus="+newstatus;
  url = url + "&showlog=" + showlog;
  url=url+"&sid="+Math.random();
  xmlHttp.onreadystatechange=PPorderStatusChanged;
  xmlHttp.open("GET",url,true);
  xmlHttp.send(null);
}

// Update the log on the order display if "showlog" is set in the response
function PPorderStatusChanged()
{
    if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete") {
        xmlDoc=xmlHttp.responseXML;
        var showlog = xmlDoc.getElementsByTagName("showlog")[0].childNodes[0].nodeValue;
        var newstatus = xmlDoc.getElementsByTagName("newstatus")[0].childNodes[0].nodeValue;
        var order_id = xmlDoc.getElementsByTagName("order_id")[0].childNodes[0].nodeValue;
        if (showlog == 1) {
            log_user = xmlDoc.getElementsByTagName("log_user")[0].childNodes[0].nodeValue;
            log_ts = xmlDoc.getElementsByTagName("log_ts")[0].childNodes[0].nodeValue;
            log_msg = xmlDoc.getElementsByTagName("log_msg")[0].childNodes[0].nodeValue;

            var tbl = document.getElementById("paypalOrderLog");
            if (tbl) {
                var lastRow = tbl.rows.length;
                var iteration = lastRow;
                var row = tbl.insertRow(lastRow);

                var cell0 = row.insertCell(0);
                var textNode = document.createTextNode(log_ts);
                cell0.appendChild(textNode);

                var cell1 = row.insertCell(1);
                var textNode = document.createTextNode(log_user);
                cell1.appendChild(textNode);

                var cell2 = row.insertCell(2);
                var textNode = document.createTextNode(log_msg);
                cell2.appendChild(textNode);
            }
        } else {
            var el = document.getElementById("statSelect_" + order_id);
            for (index = 0; index < el.length; index++) {
            if (el[index].value == newstatus)
                el.selectedIndex = index;
            }
        }

        // Hide the button and update the new status in our array
        document.getElementById("ppSetStat_" + order_id).style.visibility = "hidden";
        PP_setStatus(order_id, newstatus);
    }

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
    /*var el = document.getElementsByName("upd_orders[]");
    for (index = 0; index < el.length; index++) {
        if (el[index].value == order_id) {
            if (newvalue != oldvalue) {
                el[index].checked = true;
            } else {
                el[index].checked = false;
            }
        }
    }*/
}

function PP_setStatus(order_id, newstatus)
{
    PP_status[order_id] = newstatus;
}

function PP_getStatus(order_id)
{
    return PP_status[order_id];
}

