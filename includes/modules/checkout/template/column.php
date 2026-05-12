<?php if ($cart->count_contents() > 0): ?>
	<div class="col chkc-left afixed">
		<div id="chkc-left-wrpr">
			<?php if (is_array($aBoxes)): ?>
				<?php foreach ($aBoxes as $sBox): ?>
					<?php echo $sBox; ?>
				<?php endforeach;?>
			<?php endif;?>
		</div>
	</div>
<?php endif;?>
