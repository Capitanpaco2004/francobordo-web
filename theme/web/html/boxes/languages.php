<div id="idms">
	<?php foreach ($aDatos as $key => $value):?>
		<a class="<?php echo ($value['directory'] == $language ? 'Active' : ''); ?> <?php echo $key; ?>" href="<?php echo tep_href_link(basename($PHP_SELF), tep_get_all_get_params(array('language', 'currency')) . 'language=' . $key, $request_type); ?>">
			<?php echo $value['name']; ?> 
		</a>
	<?php endforeach; ?>
</div>