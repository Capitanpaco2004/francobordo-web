<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-<?php echo ($sGetId != false ? 'edit' : 'plus'); ?>"></i> <?php echo $sSubtitle; ?></div>
		<form method="post" id="saveform-send" action="<?php echo tep_href_link( $sUrlPage, 'action=options' ); ?>" class="oeCntd row ax xform xform-horizontal">
			<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : ''; ?>
			<input type="submit" style="display: none;" />

			<?php foreach($options as $option): ?>
				<?php
					if ($option['set_function']) {
						global $configurationOption;
						$configurationOption = $option;
						eval('$option[\'configuration_value\'] = ' . str_replace( '-&gt;', '->', $option['set_function'] ) . '"' . htmlspecialchars((string) $option['configuration_value']) . '");');
					} else {
						$option['configuration_value'] = '<input type="text" name="' . $option['configuration_key']. '" id="' . $option['configuration_key']. '" value="' . tep_output_string( $option['configuration_value']) . '">';
					}
				?>
				<label for="<?php echo $option['configuration_key'] ?>" class="column a02 tright"><?php echo $option['configuration_title'] ?><br><small style="font-size: 10px; line-height: 10px; color: #ccc;"><?php echo $option['configuration_key'] ?></small></label>
				<div class="column a10">
					<?php echo $option['configuration_value']; ?>
					<div class="DFhelp"><?php echo $option['configuration_description'] ?></div>
				</div>
				<div class="xline xline-dashed"></div>
			<?php endforeach; ?>
		</form>
	</div>
</div>
