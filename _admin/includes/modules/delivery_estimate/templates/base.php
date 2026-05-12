<?php
	$sMessageStack = $messageStack->output( false );
	$messageStack->reset();

	include( 'theme/solenopsis/html/header.php' );
?>

<div class="oeHead column a12 row ax amiddle aflex">
	<div class="oeTitu column logo afixed" style="padding-left: 52px;">
		<b><i class="fa fa-truck"></i> <?php echo $sTitle; ?></b>
		<?php echo ( $sSubtitle !== '' && $sSubtitle !== '0' ? '<small>' . $sSubtitle . '</small>' : '' ); ?>
	</div>
	<div class="oeButton column dtright">
		<?php
			if( is_array( $aButtons ) ) {
				foreach( $aButtons as $aButton ) {
					echo '<a class="xbutton hv8 small' . ( array_key_exists( 'anchor_class', $aButton ) ? ' ' . $aButton['anchor_class'] : '' ) . '" ' . ( array_key_exists( 'extra', $aButton ) ? $aButton['extra'] : '' ) . ' ' . ( array_key_exists( 'title', $aButton ) ? 'title="' . $aButton['title'] . '"' : '' ) . ' href="' . ( array_key_exists( 'href', $aButton ) ? $aButton['href'] : 'javascript:void(0);' ) . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
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
