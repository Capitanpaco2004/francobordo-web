<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/
require('includes/application_top.php');

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_BE"');
$value = tep_db_fetch_array($select_qry);
$dspid_be = $value['configuration_value'];

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_LU"');
$value = tep_db_fetch_array($select_qry);
$dspid_lu = $value['configuration_value'];

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_FR"');
$value = tep_db_fetch_array($select_qry);
$dspid_fr = $value['configuration_value'];

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_NL"');
$value = tep_db_fetch_array($select_qry);
$dspid_nl = $value['configuration_value'];

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_ES"');
$value = tep_db_fetch_array($select_qry);
$dspid_es = $value['configuration_value'];

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_SENDER_ID"');
$value = tep_db_fetch_array($select_qry);
$sid = $value['configuration_value'];

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_SENDER_COUNTRY"');
$value = tep_db_fetch_array($select_qry);
$scountry = $value['configuration_value'];

$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_SENDER_PASSWORD"');
$value = tep_db_fetch_array($select_qry);
$mandatory = $value['configuration_value'];

if (MODULE_SHIPPING_KIALAPOINT_DSPID != '') {
	tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value) VALUES ('MODULE_SHIPPING_KIALAPOINT_DSPID_BE', '')");
	tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value) VALUES ('MODULE_SHIPPING_KIALAPOINT_DSPID_LU', '')");
	tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value) VALUES ('MODULE_SHIPPING_KIALAPOINT_DSPID_FR', '')");
	tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value) VALUES ('MODULE_SHIPPING_KIALAPOINT_DSPID_NL', '')");
	tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value) VALUES ('MODULE_SHIPPING_KIALAPOINT_DSPID_ES', '')");
	tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value) VALUES ('MODULE_SHIPPING_KIALAPOINT_SENDER_ID', '')");
	tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value) VALUES ('MODULE_SHIPPING_KIALAPOINT_SENDER_COUNTRY', '')");
	switch (MODULE_SHIPPING_KIALAPOINT_COUNTRY) {
		case "BE" :
				$dspid_be = MODULE_SHIPPING_KIALAPOINT_DSPID;
				tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_be ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_BE'");
			break;
		case "LU" :
				$dspid_lu = MODULE_SHIPPING_KIALAPOINT_DSPID;
				tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_lu ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_BE'");
			break;
		case "FR" :
				$dspid_fr = MODULE_SHIPPING_KIALAPOINT_DSPID;
				tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_fr ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_FR'");
			break;
		case "NL" :
				$dspid_nl = MODULE_SHIPPING_KIALAPOINT_DSPID;
				tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_ln ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_NL'");
			break;
		case "ES" :
				$dspid_es = MODULE_SHIPPING_KIALAPOINT_DSPID;
				tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_es ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_ES'");
			break;
	}
	tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . "  WHERE configuration_key='MODULE_SHIPPING_KIALAPOINT_DSPID'");
	tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . "  WHERE configuration_key='MODULE_SHIPPING_KIALAPOINT_COUNTRY'");
}

if ((isset($_GET['action']) && ($_GET['action'] == 'edit'))){
	foreach ($_POST AS $key => $val ){
		$$key = $val;
	}
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_be ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_BE'");
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_lu ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_LU'");
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_fr ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_FR'");
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_nl ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_NL'");
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $dspid_es ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_DSPID_ES'");
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $sid ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_SENDER_ID'");
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $mandatory ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_SENDER_PASSWORD'");
	tep_db_query("update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $scountry ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_SENDER_COUNTRY'");
// echo "<h1>update " . TABLE_CONFIGURATION . "  SET configuration_value = '". $scountry ."' WHERE configuration_key = 'MODULE_SHIPPING_KIALAPOINT_SENDER_COUNTRY'</h1>";
}

$country_array[] = array( 'id' => 'BE', 'text' => 'Belgium');
$country_array[] = array( 'id' => 'FR', 'text' => 'France');
$country_array[] = array( 'id' => 'LU', 'text' => 'Luxembourg');
$country_array[] = array( 'id' => 'NL', 'text' => 'Netherlands');
$country_array[] = array( 'id' => 'ES', 'text' => 'Spain');

?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">

<link rel="stylesheet" type="text/css" href="../ext/jquery/ui/redmond/jquery-ui-1.8.22.css">
<script type="text/javascript" src="../ext/jquery/jquery-1.8.0.min.js"></script>
<script type="text/javascript" src="../ext/jquery/ui/jquery-ui-1.8.22.min.js"></script>

<script type="text/javascript">
if ( $.attrFn ) { $.attrFn.text = true; }
</script>

<script type="text/javascript" src="../ext/flot/jquery.flot.js"></script>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<script type="text/javascript" src="includes/general.js"></script>
</head>
<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF">
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
    <td width="<?php echo BOX_WIDTH; ?>" valign="top"><table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
<!-- left_navigation //-->
<?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
<!-- left_navigation_eof //-->
    </table></td>
<!-- body_text //-->
    <td width="100%" valign="top">
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td width="100%">
				<?php echo HEADING_TITLE; ?>
		</td>
      </tr>
      <tr>
        <td><table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td colspan="3"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
		  <form action="kiala_configure.php?action=edit" method="POST">
          <tr>
            <td valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="2">	
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>

              <tr>
                <td class="main" valign="top"><?php echo SCOUNTRY; ?></td>
                <td class="main"><?php echo tep_draw_pull_down_menu('scountry', $country_array, $scountry); ?></td>
              </tr>

              <tr>
                <td class="main" valign="top"><?php echo SID; ?></td>
                <td class="main"><input type="text" name="sid" value="<?php echo $sid; ?>" size="33" maxlength="32"></td>
              </tr>
              <tr>
                <td class="main" valign="top"><?php echo MANDATORY; ?></td>
                <td class="main"><input type="text" name="mandatory" value="<?php echo $mandatory; ?>" size="15" maxlength="15"></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><img src="images/countries/be.png"></td>
              </tr>	  
              <tr>
                <td class="main" valign="top"><?php echo FILL_DSP_BE; ?></td>
                <td class="main"><input type="text" name="dspid_be" value="<?php echo $dspid_be; ?>" size="8" maxlength="8"></td>
              </tr>
              <tr>
                <td  colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><img src="images/countries/lu.png"></td>
              </tr>	  
              <tr>
                <td class="main" valign="top"><?php echo FILL_DSP_LU; ?></td>
                <td class="main"><input type="text" name="dspid_lu" value="<?php echo $dspid_lu; ?>" size="8" maxlength="8"></td>
              </tr>
              <tr>
                <td  colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>  
              <tr>
                <td  colspan="2"><img src="images/countries/fr.png"></td>
              </tr>
              <tr>
                <td class="main" valign="top"><?php echo FILL_DSP_FR; ?></td>
                <td class="main"><input type="text" name="dspid_fr" value="<?php echo $dspid_fr; ?>" size="8" maxlength="8"></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><img src="images/countries/nl.png"></td>
              </tr>	
              <tr>
                <td class="main" valign="top"><?php echo FILL_DSP_NL; ?></td>
                <td class="main"><input type="text" name="dspid_nl" value="<?php echo $dspid_nl; ?>" size="8" maxlength="8"></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
              <tr>
                <td colspan="2"><img src="images/countries/es.png"></td>
              </tr>
              <tr>
                <td class="main" valign="top"><?php echo FILL_DSP_ES; ?></td>
                <td class="main"><input type="text" name="dspid_es" value="<?php echo $dspid_es; ?>" size="8" maxlength="8"></td>
              </tr>
              <tr>
                <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
              </tr>
			  <tr>
                <td colspan="2"><center><?php echo tep_image_submit('button_update.gif'); ?></center></td>
              </tr>
            </table></td>
          </tr>
		  </form>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
    </table>
</td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
<br>
<script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-41006271-3']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>