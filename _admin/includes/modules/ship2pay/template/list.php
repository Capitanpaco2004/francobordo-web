<?php
	if( tep_db_num_rows( $aRows ) <= 0 ) {
		echo $messageStack->show( [ 'text' => SHIP_TO_PAY_MEMBERS_NO_RECORDS, 'class' => 'warning' ] );
	}
?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr ">
		<form method="post" action="<?php echo tep_href_link( $sUrlPage ) ?>" class="oeCntd row ax">
			<table class="xform">
				<thead>
					<tr>
						<th width="17"  class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th ><?php echo SHIP_TO_PAY_TABLE_HEADING_SHIPMENT ?></th>
						<th><?php echo SHIP_TO_PAY_TABLE_HEADING_PAYMENTS ?></th>
						<th width="20" ><?php echo SHIP_TO_PAY_TABLE_HEADING_STATUS ?></th>
						<th width="125"><?php echo SHIP_TO_PAY_TABLE_ACTIONS ?></th>
					</tr>
				</thead>
				<tbody>
				<?php while( $shipToPay = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=s2p_crud&id=' . $shipToPay['s2p_id'] ) ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo$shipToPay['s2p_id'] ?>" name="id[]" value="<?php echo $shipToPay['s2p_id'] ?>"/><label for="id_<?php echo $shipToPay['s2p_id'] ?>"><span></span></label></td>
						<td><?php echo $shipMethodsDictionary[$shipToPay['shipment']]  ?></td>
						<td><?php
							$paymentsAllowed = explode(";",  (string) $shipToPay['payments_allowed']);
							foreach ($paymentsAllowed as $payments){
								echo $payMethodsDictionary[$payments] ;
							}

							?></td>
						<td>
							<div data-href="<?php echo tep_href_link( $sUrlPage, 'action=s2p_status&id=' . $shipToPay['s2p_id'] ) . '" class="grop-stts' . ( $shipToPay['status'] == 1 ? ' actv' : ''); ?>"></div>
						</td>
						<td>
							<div class="drop xfselect">
								<div><?php echo SHIP_TO_PAY_TABLE_ACTIONS ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=s2p_crud&id=' . $shipToPay['s2p_id'] ) ?>" class="hv"><i class="fa fa-pencil"></i><?php echo SHIP_TO_PAY_TEXT_EDIT ?></a></li>
									<li><a data-confirm="<?php echo SHIP_TO_PAY_TEXT_DELETES_CONFIRM ?>" href="<?php echo tep_href_link( $sUrlPage, 'action=s2p_delete&id=' . $shipToPay['s2p_id'] ) ?>" class="hv"><i class="fa fa-trash"></i><?php echo SHIP_TO_PAY_TEXT_DELETES ?></a></li>
								</ul>
							</div>
						</td>
					</tr>
				<?php endwhile; ?>
				</tbody>
			</table>

			<? echo $aRowsSplit->showPaginateTable( tep_get_all_get_params( ['page'] ), 'page', $sHtmlActionMasivo, 'solenopsis' ); ?>

			</div>
		</form>
	</div>
</div>



