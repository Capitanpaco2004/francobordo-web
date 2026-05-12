<?php

$news_query="select * from " . TABLE_NEWS . " order by id_news desc LIMIT " . NEWS_SHOW_LIMIT ."";

$erg=tep_db_query($news_query)
or die (tep_db_error());


while($datensatz=tep_db_fetch_array($erg))
     {
	  $ueberschrift=$datensatz[ueberschrift];
	  $autor=$datensatz[autor];
	  $kurztext=$datensatz[kurztext];
	  $id_neu=$datensatz[id_news];
	  $von=$datensatz[von];
	  $bis=$datensatz[bis];
	  $mehr=$datensatz[weiter];
	  $bild=$datensatz[bild];
		
?>

<table width="100%" border="0" cellpadding="4" cellspacing="1" bgcolor="#336699">
<tr bgcolor=FFFFFF>
	<td width="100%" class="main"><b><a href="<?php echo tep_href_link("news_zeigen.php", "id_news=$id_neu", 'NONSSL') . '">' . $ueberschrift . '</a></b></td>'; ?>
	<td align="right" nowrap class="smallText">Author: <?php echo $autor; ?></td>
	<td align="right" nowrap class="smallText"><?php echo tep_date_short($datensatz[bis]); ?></td></tr>


<tr bgcolor="#FFFFFF">
	<td class="smallText" colspan="3"><table width="100%">
<?php
if ($bild !='')
    {
?>
<td><img alt="$ueberschrift" hspace="5" src="includes/modules/news/images/<?php echo $bild; ?>" align="left" vspace="5" border="0"></td>
<?php 
     }
?>

	<td width="100%" valign="top" class="smallText"><?php echo nl2br($kurztext); ?></td></table></td>
</tr>
<tr bgcolor="#FFFFFF">
	<td class="smalltext" colspan="3" align="right"><a href="<?php echo tep_href_link("news_zeigen.php", "id_news=$id_neu", 'NONSSL'); ?> "><b>view complete message</b></a></td>
</tr>
</table>
<br>
<?php
}
?>
