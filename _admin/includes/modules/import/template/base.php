<?php
	$sMessageStack = $messageStack->output(false);
	$messageStack->reset();
?>

<?php include('theme/solenopsis/html/header.php'); ?>

<div class="oeHead column a12 row ax amiddle">
	<div class="oeTitu column a05 logo" style="padding-left: 55px;"><b><i class="fa fa-cog"></i><?php echo $title; ?></b><?php echo ($subtitle ? '<small>' . $subtitle . '</small>' : ''); ?></div>
	<div class="oeButton column a07 dtright">
		<?php
			if(is_array($buttons)) {
				foreach( $buttons as $button ) {
					echo '<a class="xbutton hv8 small' . (array_key_exists( 'anchor_class', $button ) ? ' ' . $button['anchor_class'] : '') . '" ' . (array_key_exists( 'extra', $button ) ? $button['extra'] : '') . ' ' . (array_key_exists( 'title', $button ) ? 'title="' . $button['title'] . '"' : '') . ' href="' . (array_key_exists( 'href', $button ) ? $button['href'] : 'javascript:void(0);') . '"><i class="fa ' . $button['icon'] . '"></i>' . $button['title'] . '</a> ';
				}
			}
		?>
	</div>
</div>

<?php
	echo $sMessageStack;
	echo $sHtmlModule;
	include( 'theme/solenopsis/html/footer.php' );
?>
	
<script type="text/javascript">

</script>