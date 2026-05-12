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
<script language=javascript>
function check_length(theForm,max)
{
   if(theForm.value.length > max)
   {
      theForm.value = theForm.value.substring(0,max);
      alert("(maximum " + max + " characters)");
   }
   return true;
}
</script>
<?php 
if ($edit[v_order]) {$no=$edit[v_order];}
?>

	<tr><td>
<table border="0" cellpadding=0 cellspacing=2">
<tr><td><?php echo QUEUE_FAQ;?> </td>
      <td><?echo textbox("text", "v_order", 4, 3, $no, 0);?></td>
</tr>
<tr>
      <td><?php echo VISIBLE_FAQ;?> </td>
      <td><input type="checkbox" name="visible" value="1" <?if ($edit[visible]) {echo "checked";}?>></td>
</tr>
<tr>
      <td><?php echo QUESTION_FAQ;?> </td>
      <td><?php echo textbox("text", "question", 60, 128, $edit[question], 0)?></td>
</tr>
<tr>
      <td valign=top><?php echo ANSWER_FAQ;?> </td>
      <td><textarea rows="7" name="answer" cols="60" onChange="check_length(this, <?php echo $faq_max_char?>)" onKeyUp="check_length(this, <?php echo $faq_max_char?>)"><?php echo $edit[answer]?></textarea></td>
</tr>
<tr>
      <td>&nbsp;</td>
      <td align=right>
<?php
echo image_submit("button_insert.gif",INSERT_FAQ); 
$ims=img("button_cancel.gif",ADMINISTRATOR_FAQ);
echo href("$PHP_SELF", "$ims");
 ?>
     </td>
</tr>
</table>
</form>
	</td></tr>
