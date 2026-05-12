<form method="post" id="saveform-send" class="form-admin-members" action="<?php echo tep_href_link( $sUrlPage, 'action=submodules&id='.$sGetId ) ?>">
	<div class="oeBox column a12 row ax">
		<div class="groups-grid-selects">
			<?php $boxes->print();  ?>
		</div>
	</div>
</form>
