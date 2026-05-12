<?
/*





*/
?>

<!-- Start support_track module -->

				<tr>
					<td>

<?php
    if ( (!$_GET['sort']) ) {
$sort = 'ticket_date';
}else{
$sort = $_GET['sort'];
}
$colspan = 7;
if ($_GET['view'] == 'closed') {
$open_closed = " and ticket_status='0'";
$textstatus = 'Tickets Cerrados';
}elseif ($_GET['view'] == 'open') {
$open_closed = " and ticket_status='1'";
$textstatus = 'Tickets Abiertos';
$open = 1;
}
else{
	$printstatus=1;
	$textstatus = 'All Tickets'; 
	$colspan=8;
	}

if ($_GET['sort_order'] == 'asc') {
$up_down = ' ASC';
}else{
$up_down = ' DESC';
}
if($openticket = $_GET['open']){
	$open_query = tep_db_query("SELECT MAX(sh.support_history_id) as id from support_ticket_history sh WHERE ticket_id = '$openticket' GROUP BY sh.ticket_id");
	$max_id = tep_db_fetch_array($open_query);
//	echo 'open =' . $max_id[id] . '<br>';
//	echo "SELECT MAX(sh.support_history_id) as id from support_ticket_history sh WHERE ticket_id = '$openticket' GROUP BY sh.ticket_id";

tep_db_query("UPDATE  `" . TABLE_SUPPORT_TICKETS_HISTORY . "` SET ticket_status='1' where support_history_id='$max_id[id]'");
}
elseif($openticket = $_GET['close']){
	$close_query = tep_db_query("SELECT MAX(sh.support_history_id) as id from support_ticket_history sh WHERE ticket_id = '$openticket' GROUP BY sh.ticket_id");
	$max_id = tep_db_fetch_array($close_query);
//	echo 'open =' . $max_id[id] . '<br>';
//	echo "SELECT MAX(sh.support_history_id) as id from support_ticket_history sh WHERE ticket_id = '$openticket' GROUP BY sh.ticket_id";

tep_db_query("UPDATE  `" . TABLE_SUPPORT_TICKETS_HISTORY . "` SET ticket_status='0' where support_history_id='$max_id[id]'");
}



//	$history_query_raw = "select * from " . TABLE_SUPPORT_TICKETS . " where customers_id = '" . $customer_id . "' and ". $open_closed ." order by " . $sort . $up_down . "";
		
		$group_query = tep_db_query("SELECT MAX(sh.support_history_id) as id from support_ticket_history sh GROUP BY sh.ticket_id");
		$support_history_id = '';
		while ($group = tep_db_fetch_array($group_query)) {
			$support_history_id .= $group[id] . ', ';
		}

    if ($support_history_id == ''){$support_history_id = 0;} else
    {$support_history_id = substr($support_history_id, 0, -2);}


	$history_query_raw = "select t.ticket_id, sh.department_id, sh.ticket_status, sh.priority_id, t.customers_domain, CONCAT(c.customers_firstname, ' ', c.customers_lastname) AS customers_name, t.ticket_date, c.customers_email_address, sh.last_modified, s.support_status_name, p.support_priority_name, d.support_department_name from " . TABLE_SUPPORT_TICKETS . " t, " . TABLE_SUPPORT_TICKETS_HISTORY . " sh, " . TABLE_CUSTOMERS . " c, " . TABLE_SUPPORT_PRIORITY . " p, " . TABLE_SUPPORT_DEPARTMENT . " d, " . TABLE_SUPPORT_STATUS . " s where sh.support_history_id in ($support_history_id) and t.ticket_id = sh.ticket_id and c.customers_id = t.customers_id and sh.ticket_status = s.support_status_id and sh.priority_id = p.support_priority_id and sh.department_id = d.support_department_id and s.language_id = '" . $languages_id . "' and d.language_id = '" . $languages_id . "' and p.language_id = '" . $languages_id . "' and t.customers_id = '" . $customer_id . "' " . $open_closed ." order by " . $sort . $up_down;

  $history_split = new splitPageResults($history_query_raw, MAX_DISPLAY_TICKETS, 't.ticket_id');
  $history_query = tep_db_query($history_query_raw);
  $history_numrows = tep_db_num_rows($history_query);
  $info_box_contents = array();
    if ( (!$_GET['sort_order']) ) {
$sort_order_url = '&sort_order=asc';
}else{
$sort_order_url = '';
}

if ($history_split->number_of_rows > 0) {
echo '<br><table border="0" width="100%" cellspacing="1" cellpadding="3" bgcolor="#336699">' . "\n" .
'<tr bgcolor="#FFFFFF"><td  class="main" colspan=' . $colspan . ' style="font-weight: bold;">' . $textstatus . '</td></tr>' .
                       ' <tr bgcolor="#FFFFFF">' . "\n" .
                       '    <td class="main" align=left nowrap><table cellpadding ="0" cellspacing="0" border="0" width="100%"><td class="main" align="left" nowrap><a href="' .           tep_href_link(FILENAME_SUPPORT_TRACK, 'sort=ticket_id'.          $sort_order_url . '&view='.$_GET['view'], 'NONSSL') . '">' . TEXT_TICKET_NUMBER . '</a>&nbsp;</td><td align="right"><img src="./images/icons/up_down.gif"></td></table></td>' . "\n" .
                       '    <td class="main" align="left" nowrap width=100%><table cellpadding="0" cellspacing="0" border="0" width="100%"><td class="main" align="left" nowrap><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'sort=customers_domain&' . $sort_order_url . '&view='.$_GET['view'], 'NONSSL') . '">' . TEXT_TICKET_SUBJECT . '</a></td><td align="right"><img src="./images/icons/up_down.gif"></td></table></td>' . "\n";

       if ($printstatus)  echo    '    <td class="main" align="left" nowrap><table cellpadding="0" cellspacing="0" border="0" width="100%"><td class="main" align="left" nowrap><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'sort=ticket_status&'. $sort_order_url. '&view='.$_GET['view'], 'NONSSL') . '">Estado</a>&nbsp;</td><td align="right"><img src="./images/icons/up_down.gif"></td></table></td>' . "\n";
           echo        '    <td class="main" align="left" nowrap>Actualizado por</td>' . "\n" .

                       '    <td class="main" align="left" nowrap><table cellpadding="0" cellspacing="0" border="0" width="100%"><td class="main" align="left" nowrap><a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'sort=ticket_date&'. $sort_order_url. '&view='.$_GET['view'], 'NONSSL') . '">Enviado</a>&nbsp;</td><td align="right"><img src="./images/icons/up_down.gif"></td></table></td>' . "\n" .

                //       '    <td class="main" align="center" colspan="3" nowrap>Actions</td>' . "\n" .
                       '  </tr>';
                       
   while ($history = tep_db_fetch_array($history_query)) {
     
	$priority_icon = '&nbsp;';
	$status_desc = $history['support_status_name'];
	if ($history['ticket_status']=='0'){
		$bg_color = '"#cccccc"';
	}
	else{
		if ($history['priority_id'] == '2'){
			$priority_icon = tep_image(DIR_WS_IMAGES . 'icons/support_high.gif', 'High Priority', 12, 13);
		}
		$bg_color = '"#ffffff"';
	}

if ($history['submitted_by'] == 'customer'){$submitted_by = $history['customers_name'];}
else {$submitted_by = $history['support_department_name'];}


      $ticket_heading = "<tr bgcolor=$bg_color>\n" .
                       '    <td class="main" align=right nowrap>' . $priority_icon . '&nbsp;<a href="' . tep_href_link(FILENAME_SUPPORT_TICKET_INFO, 'page=' . $_GET['page'] . '&ticket_id=' . $history['ticket_id'], 'NONSSL') . '" style="color:#0000ff;">' . $history['ticket_id'] . '</a></td>' . "\n" .
                       '    <td class="main" align="left" nowrap><a href="' . tep_href_link(FILENAME_SUPPORT_TICKET_INFO, 'page=' . $_GET['page'] . '&ticket_id=' . $history['ticket_id'], 'NONSSL') . '" style="color:#0000ff;"><em>' . cutstr($history['customers_domain'], 10) . '</em></a></td>' . "\n";
      if ($printstatus) $ticket_heading .= '    <td class="main" align=left nowrap>' . $status_desc . '</td>' . "\n" ;
    $ticket_heading .= '    <td class="main" align=left nowrap>'. $submitted_by . '<span style="font-size=9px"> el ' . tep_date_short($history['last_modified']). ' </span></td>' . "\n" .
                       '    <td class="main" align="left" nowrap>' . tep_date_short($history['ticket_date']) . '</td>' . "\n" .

                       '  </tr>';

      $details = '<table border="0" width="100%" cellspacing="0" cellpadding="2">' . "\n" .
               '  <tr>' . "\n" .
               '    <td class="main" width="50%" valign="top">DETAILS<strong>' . TEXT_TICKET_DATE . '</strong> ' . tep_date_long($history['ticket_date']) . '<br><strong>' . TEXT_SUBMITTED_BY . '</strong> ' . $history['customers_name'] . '</td>' . "\n" .
               '    <td class="main" width="30%" valign="top"><strong>' . TEXT_TICKET_DEPARTMENT . '</strong> ' . $department['support_department_name'] . '<br><strong>' . TEXT_TICKET_PRIORITY . '</strong> ' . $priority['support_priority_name'] . '</td>' . "\n" .
               '    <td class="main" width="20%"><strong><a href="' . tep_href_link(FILENAME_SUPPORT_TICKET_INFO, 'page=' . $_GET['page'] . '&ticket_id=' . $history['ticket_id'], 'NONSSL') . '">' . TEXT_VIEW_TICKET . '</a></td>' . "\n" .
               '  </tr>' . "\n" .
               '</table>';
      echo $ticket_heading;
    }
        echo '</table>';
  } else {
if ($_GET['view'] == 'closed') {
    new infoBox(array(array('text' => TEXT_NO_CLOSED)));
}else{
    new infoBox(array(array('text' => TEXT_NO_PURCHASES)));
}
  }

?>					</td>
				</tr>

    <tr>
    	<td>
			<table border="0" width="100%" cellspacing="0" cellpadding="2">
<?php
  if (tep_db_num_rows($history_query)) {
?>
				<tr>
					<td class="smallText" valign="top"><?php echo $history_split->display_count(TEXT_DISPLAY_NUMBER_OF_TICKETS); ?></td>
					<td class="smallText" align="right"><?php echo TEXT_RESULT_PAGE; echo $history_split->display_links(MAX_DISPLAY_TICKET_PAGE_LINKS, tep_get_all_get_params(array('page', 'info', 'x', 'y'))); ?></td>
				</tr>
<?php
}
?>
	</table></td>
       </tr>
       
       
<!-- end support_track module -->
