<div class="box-small box-small-mnds">
	<span>Monedas</span>
    <?php foreach( $currencies_array as $valor ): ?>
    	<a title="<?php echo $valor['text']; ?>" href="<?php echo tep_href_link(basename($PHP_SELF), tep_get_all_get_params(array('language', 'currency')) . 'currency=' . $valor['id'], $request_type); ?>" id="<?php echo $valor['id'] . ($valor['id'] == $currency ? '-actv' : ''); ?>"><?php echo $valor['text']; ?></a>
    <?php endforeach; ?>
</div>