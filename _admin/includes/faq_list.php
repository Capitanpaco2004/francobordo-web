<?php
/*
  $Id: faq.php v.0.1 26.05.2002 
  povered by Adgrafics-Ukraine http://adgrafics.net 
  victor@zolochevsky.com

  The Exchange Project - Community Made Shopping!
  http://www.theexchangeproject.org 

  Copyright (c) 2000,2001 The Exchange Project

  Released under the GNU General Public License
*/
?>

<tr class=pageHeading><td><?php echo $title ?></td></tr>
<tr><td>
<table border="0" cellpadding=4 cellspacing=1 bgcolor="#ffffff">
<tr class="dataTableHeadingRow">
	<td align=center class="dataTableHeadingContent"><?php echo NO_FAQ;?></td>
	<td align=center class="dataTableHeadingContent"><?php echo QUEUE_FAQ;?></td>
	<td align=center class="dataTableHeadingContent"><?php echo DATE_FAQ;?></td>
	<td align=center class="dataTableHeadingContent"><?php echo QUESTION_FAQ;?></td>
	<td align=center class="dataTableHeadingContent"><?php echo ID_FAQ;?></td>
	<td align=center class="dataTableHeadingContent"><?php echo PUBLIC_FAQ;?></td>
	<td align=center class="dataTableHeadingContent" colspan=2><?php echo ACTION_FAQ;?></td>

</tr>
<?
 $no=1;
 if (sizeof($data) > 0) {
  foreach( $data as $key => $val ) {
  $no % 2 ? $bgcolor="#DEE4E8" : $bgcolor="#F0F1F1";
?>
   <tr bgcolor="<?php echo $bgcolor?>">

    <td align="right" class="dataTableContent"><?php echo $no;?></td>
    <td align="center" class="dataTableContent"><?php echo $val['v_order'];?></td>
    <td align=right nowrap  class="dataTableContent"><?php echo $val['d']?></td>
    <td class="dataTableContent"><?php echo $val['question'];?></td>
    <td align="center" class="dataTableContent"><?php echo $val['faq_id'];?></td>
    <td nowrap  class="dataTableContent">
<?php 
if ($val['visible']==1) {
$ims=img("icon_status_green.gif",DEACTIVATION_ID_FAQ . " $val[faq_id]");
echo href("$PHP_SELF?adgrafics_faq=Deactivation&faq_id=$val[faq_id]&visible=$val[visible]&v_order=$val[v_order]&question=$val[question]&answer=$val[answer]&date=$val[date]", "$ims yes");} 
else {
$ims=img("icon_status_red.gif",ACTIVATION_ID_FAQ . " $val[faq_id]");
echo href("$PHP_SELF?adgrafics_faq=Activation&faq_id=$val[faq_id]&visible=$val[visible]&v_order=$val[v_order]&question=$val[question]&answer=$val[answer]&date=$val[date]", "$ims no");
};
?>
</td>
    <td align=center class="dataTableContent"><?php echo href("$PHP_SELF?adgrafics_faq=Edit&faq_id=$val[faq_id]", "" . EDIT_FAQ . "")?></td>
    <td align=center class="dataTableContent"><?php echo href("$PHP_SELF?adgrafics_faq=Delete&faq_id=$val[faq_id]", "" . DELETE_FAQ . "")?></td>
   </tr>
<?$no++;
  }} else {?>
   <tr bgcolor="#DEE4E8">
    <td colspan=7><?php echo ALERT_FAQ;?></td>
   </tr>
<?}?>
</table>
</td></tr>
<tr><td align=right>
<?php 
$ims=img("button_new_faq.gif",ADD_FAQ);
echo href("$PHP_SELF?adgrafics_faq=Added", "$ims"); 
$ims=img("button_cancel.gif",ADMINISTRATOR_FAQ);
echo href("$PHP_SELF", "$ims");
 ?>
</td></tr>


<tr class=pageHeading><td><?php echo VIEW_FAQ;?></td></tr>
<tr><td>
<ol>
<?php 
while ($faq=faq_toc()) {
?>
<li class=faq_q><?php echo $faq['toc'];?>
<?php }
?>
</ol>
<hr size="1" color="#808080">
<ul>
<?php while ($faq=read_faq()) {
?>
<li class=faq_ans><a name=<?php echo $faq['faq_id']?>><span class=faq_g><?php echo $faq['question'];?></span></a><br>
<?php echo $faq['answer'];?>
<?php }
?>
</ul>
</td></tr>