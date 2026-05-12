
<!-- start support_menu module -->

<?

// if (tep_session_is_registered('customer_id')) {
  
echo '

			<table border=0 cellpadding=0 cellspacing=1 bgcolor="#336699" align=center>
				<tr>
          <td>
			<table border=0 cellpadding=0 cellspacing=0 bgcolor="#ffffff" align=center width=100%>
				<tr>
          <td>&nbsp;&nbsp;</td>
					<td><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, '', 'NONSSL') . '" style="color:#003366;font-weight:bold;font-size:10px;">  GENERAL | </a></td>
					<td><nobr>&nbsp;&nbsp;</nobr></td>
					<td><a href="' . tep_href_link(FILENAME_SUPPORT, 'action=new', 'NONSSL') . '"  style="color:#003366;font-weight:bold;font-size:10px">  ' . SEND_TICKET . ' | </a></td>
					<td><nobr>&nbsp;&nbsp;</nobr></td>
					<td><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'view=open', 'NONSSL') . '"  style="color:#003366;font-weight:bold;font-size:10px">  ' . OPEN_TICKETS . ' | </a></td>
					<td><nobr>&nbsp;&nbsp;</nobr></td>
					<td><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'view=closed', 'NONSSL') . '"  style="color:#003366;font-weight:bold;font-size:10px">  ' . CLOSE_TICKETS . ' | </a></td>
					<td><nobr>&nbsp;&nbsp;</nobr></td>
					<td><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'view=all', 'NONSSL') . '"  style="color:#003366;font-weight:bold;font-size:10px">  ' . ALL_TICKETS . ' | </a></td>
					<td><nobr>&nbsp;&nbsp;</nobr></td>
          <td><a href="' . tep_href_link(FILENAME_FAQ, '', 'NONSSL') . '"  style="color:#003366;font-weight:bold;font-size:10px">  FAQ | </strong></a></td>
          <td><nobr>&nbsp;&nbsp;</nobr></td>
          <td><a href="' . tep_href_link('news_archiv.php', '', 'NONSSL') . '"  style="color:#003366;font-weight:bold;font-size:10px">  News  </a></td>
          <td><nobr>&nbsp;&nbsp;</nobr></td>
          <td>&nbsp;&nbsp;</td>
				</tr>
			</table>
         </td>
				</tr>
			</table>


	';
// } else {

echo '

<!--			<table border=0 cellpadding=0 cellspacing=1 bgcolor="#336699" align=center>
				<tr>
          <td>
			<table border=0 cellpadding=0 cellspacing=0 bgcolor="#ffffff" align=center width=100%>
				<tr >
          <td class="main">&nbsp;[&nbsp;</td>
					<td nowrap class="infoBoxContents"><p><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, '', 'NONSSL') . '" >MAIN</a></td>
					<td class="infoBoxContents">&nbsp;][&nbsp;</td>
					<td nowrap class="main"><a href="' . tep_href_link(FILENAME_CONTACT_US, '', 'NONSSL') . '" >Contact us</a></td>
					<td class="infoBoxContents">&nbsp;][&nbsp;</td>
          <td nowrap class="main"><a href="' . tep_href_link(FILENAME_FAQ, '', 'NONSSL') . '" >FAQ</strong></a></td>
          <td class="infoBoxContents">&nbsp;][&nbsp;</td>
          <td nowrap class="main"><a href="' . tep_href_link('news_archiv.php', '', 'NONSSL') . '" >News</a></td>
          <td class="infoBoxContents">&nbsp;]&nbsp;</td>
				</tr>
			</table>
         </td>
				</tr>
			</table> -->


	';

//}
 ?>
 
<!--End support_menu module -->