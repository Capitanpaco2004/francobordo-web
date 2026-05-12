<form id="box-nwlt" class="box" action="<?php echo tep_href_link( 'newsletters_subscribe.php' ) ?>" method="post">
	<input value="<?php echo $sEmail; ?>" name="email">
	<button class="hovr" onclick="return verify(newsletter);" type="submit">OK</button>
</form>