<TR>
<TD class="pageHeading" colspan="3"><?=$pageTitle?></TD>
</TR>
<FORM ACTION="<?=$PHP_SELF?>" NAME="SELECT_PRODUCT" METHOD="POST">
<INPUT TYPE="HIDDEN" NAME="action" VALUE="select">
<?
echo "<TR>";
echo "<TD class=\"main\"><BR><B>Seleccione el producto al que deseas asignarle las opciones:<BR></TD>";
echo "</TR>";
echo "<TR>";
echo "<TD class=\"main\"><SELECT NAME=\"current_product_id\">";

$query = "SELECT * FROM products_description where products_id LIKE '%' AND language_id = '$languageFilter' ORDER BY products_name ASC";

$result = tep_db_query($query) or die(tep_db_error());

$matches = tep_db_num_rows($result);

if ($matches) {

   while ($line = tep_db_fetch_array($result)) {
                                                           	
        $title = $line['products_name'];
        $current_product_id = $line['products_id'];
        
        echo "<OPTION VALUE=\"" . $current_product_id . "\">" . $title;
        
   }
} else { echo "You have no products at this time."; }
   
echo "</SELECT>";
echo "</TD></TR>";

echo "<TR>";
echo "<TD class=\"main\"><input type=\"image\" src=\"" . $adminImages . "button_edit.png\"></TD>";
echo "</TR>";

?>
</FORM>

