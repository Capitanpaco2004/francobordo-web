<?php
	$sMessageStack = $messageStack->output(false);
	$messageStack->reset();

	include('theme/solenopsis/html/header.php');
?>

<div class="oeHead column a12 row ax amiddle">
	<div class="oeTitu column a05 logo" style="padding-left: 55px;"><b><i class="fas fa-image"></i><?php echo $sTitle; ?></b><?php echo ($sSubtitle ? '<small>' . $sSubtitle . '</small>' : ''); ?></div>
	<div class="oeButton column a07 dtright">
		<?php
			if (is_array($aButtons)) {
				foreach ($aButtons as $aButton)
					echo '<a class="xbutton hv8 small' . (array_key_exists('anchor_class', $aButton) ? ' ' . $aButton['anchor_class'] : '') . '" ' . (array_key_exists('extra', $aButton) ? $aButton['extra'] : '') . ' ' . (array_key_exists('title', $aButton) ? 'title="' . $aButton['title'] . '"' : '') . ' href="' . (array_key_exists('href', $aButton) ? $aButton['href'] : 'javascript:void(0);') . '"><i class="fa ' . $aButton['icon'] . '"></i>' . $aButton['title'] . '</a> ';
			}
		?>
	</div>
</div>

<?php
	echo $sMessageStack;
	echo $sHtmlModule;
	include('theme/solenopsis/html/footer.php');
?>
