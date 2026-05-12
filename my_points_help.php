<?php
/*
  $Id: my_points_help.php, V2.1rc2a 2008/OCT/01 16:04:22 dsa_ Exp $
  created by Ben Zukrel, Deep Silver Accessories
  http://www.deep-silver.com

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_MY_POINTS_HELP);

  $breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_MY_POINTS_HELP, '', 'NONSSL'));
?>

<?php require(DIR_THEME. 'html/header.php'); ?>

<script language="javascript"><!--
window.onload=show;

function show(id) {
var d = document.getElementById(id);
	for (var i = 1; i<=20; i++) {
		if (document.getElementById('answer_q'+i)) {document.getElementById('answer_q'+i).style.display='none';}
	}
	
    if (d) {
	    d.style.display='block';
	    d.className='pointFaq';
    }
}
//--></script>


<?php require(DIR_THEME. 'html/column_left.php'); ?>


<h1 class="pageHeading"><?php echo HEADING_TITLE; ?></h1>
<div class="informacion">
<?php echo TEXT_INFORMATION2; ?>
<ol class="lsta-my-point">
	<?php
		$nCont = 1;
		$information_query = tep_db_query( 'SELECT information_id, information_title, information_description 
											FROM ' . TABLE_INFORMATION . '
											WHERE information_group_id = 2
											AND visible = "1" 
											AND language_id = ' . (int)$languages_id . '
											ORDER BY sort_order' );
        while( $information = tep_db_fetch_array($information_query) ) {
			echo '
			<li>
				<a class="titular" href="javascript:show(\'answer_q' . $nCont . '\');">' . $information['information_title'] . '</a>
				<div id="answer_q' . $nCont . '" class="pointFaq">' . $information['information_description'] . '</div>
			</li>';
			++$nCont;
		}
	?>
</ol>
</div>
<div class="botonera"><a href="javascript:history.go(-1)"><?php echo tep_image_button('button_back.gif', IMAGE_BUTTON_BACK); ?></a> <?php echo '<a href="' . tep_href_link(FILENAME_DEFAULT, '', 'NONSSL') . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a>'; ?></div>

<?php require(DIR_THEME. 'html/column_right.php'); ?>
<?php require(DIR_THEME. 'html/footer.php'); ?>
<!-- footer_eof //-->

</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>