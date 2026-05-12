<html>
<!--	
--------------------------------------------------------------------
Admin Comments Toolbar 2.0 by Skeedo.com Enterprises info@skeedo.com
Released under GNU General Public License for use with OSCommerce
--------------------------------------------------------------------
-->
<head>
   <script language="JavaScript">
   <!--

	var usrdate = '';

   function updateComment(obj,statusnum) {
			var textareas = document.getElementsByTagName('textarea');
			var myTextarea = textareas.comments; //			var myTextarea = textareas.item(1);
//			if (myTextarea.value != '') {			
			myTextarea.value += '\n' + obj;
			myTextarea.scrollTop=myTextarea.scrollHeight;
//			}
//			else {
//			myTextarea.value = obj;
//			}

			var selects = document.getElementsByTagName('select');
			var theSelect = selects.item(0);
			theSelect.selectedIndex = statusnum;
			
   }

   function killbox() {
			var box = document.getElementsByTagName('textarea');
			var killbox = box.comments;	//		var killbox = box.item(1);
			killbox.value = '';

	}


   //-->
   </script>

<style type="text/css">
.cbutton { 
width: 100px; 
font-family: Verdana; 
font-size: 9px; 
padding: 0px; 
border-bottom: 1px solid #000000; 
border-right: 1px solid #000000; 
border-top: 1px solid #000099; 
border-left: 1px solid #000099;

cursor: hand;}
</style>

</head>
<body>

<!--
Edit the following buttons to make the buttons
and text inserts of your liking.

e.g. <button class="cbutton" onClick="updateComment('Text for button to insert','Order Status #');">Button Text</button>&nbsp;

Orderstatusnumber is the number of the order status option you would like the button to select, 0 being the first. 

e.g.	Paypal Processing
		Pending
		Backordered
		Processed
		Cancelled
		See Invoice

Paypal Processing would be 0, Cancelled would be 4.

Please note that the 'B. order Dte' button uses two functions. This is to prompt the user for a backorder date to insert.
Editing this and the other buttons should be pretty straight forward! 

To add more buttons just copy the first button code line to the end before the reset button. 


-->

<table cellpadding=0 cellspacing=0 border=0>

	<tr>
		<td>
<!--<button class="cbutton" onClick="updateComment('Your product is on backorder, please allow 1-2 weeks for restock.','3');">B. order Reg</button>&nbsp;-->
<button class="cbutton" onClick="updateComment('<? echo TEXT_SUPPORT_ADDED; ?>','3');">Ticket Registered</button>&nbsp;
<button class="cbutton" onClick="updateComment('<? echo TEXT_SUPPORT_UPDATE; ?>','3');">Ticket Updated</button>&nbsp;
<button class="cbutton" onClick="updateComment('<? echo TEXT_SUPPORT_SOLVED; ?>','6');">Problem Resolved</button>&nbsp;
<button class="cbutton" onClick="updateComment('<? echo TEXT_SUPPORT_ADDED_TO_FAQ; ?>','6');">Added to FAQ</button>&nbsp;
<button class="cbutton" onClick="updateComment('<? echo TEXT_SUPPORT_CLOSED; ?>','5');">Ticket Closed</button>&nbsp;
<button class="cbutton" onClick="killbox();">Reset</button>&nbsp;
		</td>
		</tr>

</table>

</body>
</html>
