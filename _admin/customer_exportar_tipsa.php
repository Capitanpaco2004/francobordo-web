<?php
require('includes/application_top.php');
if (!$_POST['submit'])
{
	?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//DE">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
</head>
<body id="customers">
<!-- header //-->
<?php require(DIR_WS_INCLUDES.'header.php'); ?>
<!-- header_eof //-->
<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
        <script>
new Rico.Accordion( $('accordionDiv'), {panelHeight:210, collapsedBg:'#B3BAC5', expandedBg:'#C9C9C9', hoverBg: '#C9C9C9', borderColor:'#C9C9C9', expandedTextColor:'#616060', onLoadShowTab:'5'} );
</script>

    <!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
        <tr>
          <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
        </tr>
        <tr>
          <td><?php echo tep_draw_form(BOX_CUSTOMER_EXPORT, FILENAME_CUSTOMERS_EXPORT_TIPSA, '', 'post'); ?>
            <table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td class="main"><?php echo TABLE_HEADING_CUSTOMER_EXPORT; ?></td>
              </tr>
              <tr>
                <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td class="main"><p><?php echo TEXT_CUSTOMER_EXPORT_ALL; ?></p>
                 

                  </td>
              </tr>
             
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
              <td class="smallText" ><input type="submit" value="<?php echo TEXT_CUSTOMER_EXPORT; ?>" name="submit"></td>
              </tr>
            </table>
            </form>
          </td>
        </tr>
      </table></td>
  </tr>
</table>
<!-- footer //-->
<center>
  <font color="#666666" size="2"></font>
</center>
<!-- footer_eof //-->
<br>
</body>
</html>
<?php
}
else
{

	if($_POST['separator']!="") $sep=stripcslashes($_POST['separator']); else $sep="";
	$sep= str_replace('\t', "\011", $sep);


//	$contents="CODIGO".$sep."NOMBRE".$sep."TELEFONO".$sep."CP".$sep."POBLACION".$sep."PROVINCIA".$sep."DIRECCION\n";

	$customers_query_raw = "select c.customers_id,
                                  c.customers_firstname,
    							  c.customers_lastname,
							      c.customers_telephone,
                                  a.entry_postcode,
                                  a.entry_state,
                                  a.entry_city,
								  a.entry_street_address,
    							  a.entry_suburb,
    							 
    							  
    							  co.countries_name
    							   from " . TABLE_CUSTOMERS . " c left join " . TABLE_ADDRESS_BOOK . " a on c.customers_id = a.customers_id and c.customers_default_address_id = a.address_book_id
    							   left join " . TABLE_COUNTRIES . " co on co.countries_id = a.entry_country_id
    							   order by c.customers_lastname, c.customers_firstname";
    $customers_query = tep_db_query($customers_query_raw);
    while ($row = tep_db_fetch_array($customers_query)) {


        $contents.=str_pad($row['customers_id'], 20);
	    $contents.=str_pad(strtoupper($row['customers_firstname'])." " . strtoupper($row['customers_lastname']), 40);
        $contents.=str_pad($row['customers_telephone'], 15);
		$contents.=str_pad($row['entry_postcode'], 5);
		$contents.=str_pad(strtoupper($row['entry_city']), 60);
		$contents.=str_pad(strtoupper($row['entry_state']), 20);
        $contents.=str_pad(strtoupper($row['entry_street_address'])." " . strtoupper($row['entry_suburb']), 50)."\n\n";

	}
	/*Header("Content-Disposition: attachment; filename=export.txt");
	print $contents;*/

	 header("Content-Type: application/force-download\n");
                header("Content-disposition: attachment; filename=Tipsa.txt");
				header("Pragma: no-cache");
				header("Expires: 0");
				echo $contents;
				die();
}
require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
