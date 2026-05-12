<div class="box b1">
    <div class="box-top"><?php echo BOX_HEADING_CATEGORIES; ?></div>

	<ul class="box-lsta">
		<?php
			foreach( $aListas as $aLista )
				echo '<li><a ' . ($aLista['ACTIVO'] ? 'class="actv"' : '') . ' title="' . $aLista['TEXT'] . '" href="' . $aLista['HREF'] . '">' . $aLista['TEXT'] . '</a></li>';
		?>
	</ul>
	
    <div class="box-fotr"></div>
</div>