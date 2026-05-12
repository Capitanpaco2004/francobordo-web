<?php

$news_query="select * from " . TABLE_NEWS . " order by id_news desc LIMIT " . 10 ."";

$erg=tep_db_query($news_query)
or die (tep_db_error());
?>

<table width="100%" border="0" cellpadding="2" cellspacing="1" class="smallText" bgcolor="#336699">
	<tr bgcolor="#FFFFFF">
		<td width="100%" class="main" colspan="3"><b><a href="<?php echo tep_href_link('news_home.php', '', 'NONSSL') .     '">' . TEXT_PREVIEW; ?> </a></b></td>
</tr>
<?php

while($datensatz=tep_db_fetch_array($erg))
     {
	  $title=$datensatz[ueberschrift];
	  $author=$datensatz[autor];
	  $shorttext=$datensatz[kurztext];
	  $id_neu=$datensatz[id_news];
	  $von=$datensatz[von];
	  $bis=tep_date_short($datensatz[bis]);
	  $mehr=$datensatz[weiter];
	  $bild=$datensatz[bild];
		

echo '
	<tr bgcolor="#FFFFFF">
		<td class="infoBoxContents" rowspan="2" valign="top">&gt;&gt;</td>
		<td width="100%" class="infoBoxContents"><b><a href="' . tep_href_link('news_zeigen.php', 'id_news=' . $id_neu, 'NONSSL') . '">' . $title . '</a></b></td>
		<td class="infoBoxContents" nowrap align="right">' . $bis . '</td>
	</tr>
	<tr bgcolor="#FFFFFF">
		<td class="smallText" colspan="2">' . $shorttext . '</td>
	</tr>';
}
echo "</table>";

?>
