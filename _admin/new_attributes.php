<?
  $adminImages = "includes/languages/espanol/images/buttons/";
  $backLink = "<a href=\"javascript:history.back()\">";

  require('new_attributes_config.php');
  require('includes/application_top.php');
  
if (isset($_POST['current_product_id'])) {
$current_product_id = $_POST['current_product_id'];
}

if (isset($_POST['action'])) {
$action = $_POST['action'];
}

if (isset($_POST['optionValues'])) {
$optionValues = $_POST['optionValues'];
}

if (isset($_POST['x'])) {
$x = $_POST['x'];
}

if (isset($_POST['y'])) {
$y = $_POST['y'];
}

if (isset($_POST['cPathID'])) {
$cPathID = $_POST['cPathID'];
}

if (isset($_GET['current_product_id'])) {
$current_product_id = $_GET['current_product_id'];
}

if (isset($_GET['action'])) {
$action = $_GET['action'];
}

  $cPathID = $cPathID ?? '';
  $action = $action ?? '';
  if ( $cPathID && $action == "change" )
  {
        require('new_attributes_change.php');

        tep_redirect( './' . FILENAME_CATEGORIES . '?cPath=' . $cPathID . '&pID=' . $current_product_id );

  }

?>


<?php require(THEME . 'html/header.php'); ?>


<!-- body //-->
     <table border="0" width="100%" cellspacing="2" cellpadding="2">
     <tr>

<!-- body_text //-->
    <td width="100%" valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
    
<?
function findTitle( $current_product_id, $languageFilter )
{
  $query = "SELECT * FROM products_description where language_id = '$languageFilter' AND products_id = '$current_product_id'";

  $result = tep_db_query($query) or die(tep_db_error());

  $matches = tep_db_num_rows($result);

  if ($matches) {

  while ($line = tep_db_fetch_array($result)) {
                                                          	
        $productName = $line['products_name'];
        
  }
  
  return $productName;
  
  } else { return "Something isn't right...."; }
  
}

function attribRedirect( $cPath )
{

 return '<SCRIPT LANGUAGE="JavaScript"> window.location="./configure.php?cPath=' . $cPath . '"; </script>';
 
}

switch( $action ?? '' )
{
  case 'select':
  $pageTitle = 'Editar Atributos -> ' . findTitle( $current_product_id, $languageFilter );
  require('new_attributes_include.php');
  break;
  
  case 'change':
  $pageTitle = 'Atributos del producto actualizados.';
  require('new_attributes_change.php');
  require('new_attributes_select.php');
  break;

  default:
  $pageTitle = 'Editar Atributos';
  require('new_attributes_select.php');
  break;
  
}

?>

    </table></TD>
    </TR>
<!-- body_eof //-->

<!-- footer //-->

<?php require(THEME . 'html/footer.php'); ?>
<!-- footer_eof //-->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
