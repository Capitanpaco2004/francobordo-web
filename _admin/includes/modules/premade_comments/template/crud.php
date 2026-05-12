<?php
	// Tools
	use util\tools as tools;
?>

<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-comment"></i> <?php echo $sSubtitle; ?></div>
		<form method="post" id="saveform-send" action="<?php echo tep_href_link_pc( $sUrlPage, 'action=crud' ); ?>" class="oeCntd row ax xform xform-horizontal">
			<?php echo $sGetId !== false ? '<input type="hidden" name="id" value="' . $sGetId . '" />' : ''; ?>
			<input type="submit" style="display: none;" />

			<label for="title" class="column a02 tright"><?php echo PREMADE_COMMENTS_TEXT_TITLE; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'title', $aMessageError ) ? $aMessageError['title'] : ''; ?>
				<input type="text" name="title" id="title" value="<?php echo (array_key_exists( 'title', $aRecord ) ? $aRecord['title'] : ''); ?>">
				<div class="DFhelp"><?php echo PREMADE_COMMENTS_TEXT_TITLE_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<label for="text" class="column a02 tright"><?php echo PREMADE_COMMENTS_TEXT_TEXT; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists( 'text', $aMessageError ) ? $aMessageError['text'] : ''; ?>
				<textarea name="text" id="text"><?php echo (array_key_exists( 'text', $aRecord ) ? $aRecord['text'] : ''); ?></textarea>
				<div class="DFhelp"><?php echo PREMADE_COMMENTS_TEXT_TEXT_HELP; ?></div>
			</div>
		</form>
	</div>
</div>
