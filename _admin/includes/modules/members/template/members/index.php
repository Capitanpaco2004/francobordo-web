<?php
	if( tep_db_num_rows( $aRows ) <= 0 ) {
		echo $messageStack->show( [ 'text' => ADMIN_MEMBERS_NO_RECORDS, 'class' => 'warning' ] );
	}
?>

<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-eye"></i> <?php echo ADMIN_MEMBERS_HEADING_SUBTITLE_MEMBERS_LIST ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage ) ?>" class="oeCntd row ax">
			<div class="oeBoxFltr column a12 ax row">
				<div class="column a09 row ax amiddle input-search">
					<label class="column"><?php echo ADMIN_MEMBERS_TEXT_SEARCH ?>: </label> <div class="column"><input type="text" name="filter[search]" placeholder="Introduce búsqueda" value="<?php echo $aFilter['search']; ?>" autofocus/> <input type="submit" style="display: none" /> <i class="fa fa-search"></i></div>
				</div>
				<div class="column a03 tright">
					<?php echo ($sWhere != '' ? '<a title="Quitar filtro" href=" ' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i></a> ' : ''); ?>
					<a href="#fltr-lstd" title="Filtrar registros" class="xbutton small hv9 verde-turquesa mgp-inln"><i class="fa fa-filter"></i></a>
				</div>
			</div>

			<table class="xform">
				<thead>
					<tr>
						<th width="17" class="chck"><input type="checkbox" id="all_check"/><label for="all_check"><span></span></label></th>
						<th width="150"><?php echo ADMIN_MEMBERS_TABLE_HEADING_FULLNAME ?></th>
						<th><?php echo ADMIN_MEMBERS_TABLE_HEADING_EMAIL ?></th>
						<th><?php echo ADMIN_MEMBERS_TABLE_HEADING_GROUPS ?></th>
						<th ><?php echo ADMIN_MEMBERS_TABLE_HEADING_LOG_NUM ?></th>
						<th width="50">2FA</th>
						<th width="125"><?php echo ADMIN_MEMBERS_TABLE_ACTIONS ?></th>
					</tr>
				</thead>
				<tbody>
				<?php while( $admin = tep_db_fetch_array( $aRows ) ): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=members_crud&id=' . $admin['admin_id'] ) ?>">
						<td class="chck" align="center"><input type="checkbox" id="id_<?php echo $admin['admin_id'] ?>" name="id[]" value="<?php echo $admin['admin_id'] ?>"/><label for="id_<?php echo $admin['admin_id'] ?>"><span></span></label></td>
						<td><?php echo $admin['admin_firstname'] . ' ' . $admin['admin_lastname'] ?></td>
						<td><?php echo $admin['admin_email_address'] ?></td>
						<td><?php echo $admin['admin_groups_name'] ?></td>
						<td><?php echo $admin['admin_lognum'] ?></td>
						<td style="text-align:center;">
							<?php if ($admin['admin_2fa_enabled'] == 1): ?>
								<span style="background:#d4edda;color:#155724;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:bold;"><i class="fas fa-check"></i></span>
							<?php else: ?>
								<span style="background:#f8d7da;color:#721c24;border-radius:4px;padding:2px 8px;font-size:11px;"><i class="fas fa-times"></i></span>
							<?php endif; ?>
						</td>
						<td>
							<div class="drop xfselect">
								<div><?php echo ADMIN_MEMBERS_TABLE_ACTIONS ?></div>
								<ul class="down down-dngt">
									<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=members_crud&id=' . $admin['admin_id'] ) ?>" class="hv"><i class="fa fa-pencil"></i><?php echo ADMIN_MEMBERS_TEXT_EDIT ?></a></li>
									<?php
									// Mostrar elemento cambiar contraseña solo si pertenece al grupo de administradores
									if($login_groups_id == 1): ?>
										<li><a href="<?php echo tep_href_link( $sUrlPage, 'action=members_password&id=' . $admin['admin_id'] ) ?>" class="hv"><i class="fa fa-key"></i><?php echo ADMIN_MEMBERS_TEXT_CHANGE_PASSWORD ?></a></li>
									<?php endif; ?>
									<?php if ($login_groups_id == 1 && $admin['admin_2fa_enabled'] == 1 && $admin['admin_id'] != $login_id): ?>
										<li><a data-confirm="Resetear el 2FA de este miembro? Debera configurarlo de nuevo." href="<?php echo tep_href_link($sUrlPage, 'action=members_2fa_reset&id=' . $admin['admin_id']) ?>" class="hv"><i class="fa fa-shield-halved"></i> Resetear 2FA</a></li>
									<?php endif; ?>
									<li><a data-confirm="<?php echo ADMIN_MEMBERS_TEXT_DELETES_CONFIRM ?>" href="<?php echo tep_href_link( $sUrlPage, 'action=members_delete&id=' . $admin['admin_id'] ) ?>" class="hv"><i class="fa fa-trash"></i><?php echo ADMIN_MEMBERS_TEXT_DELETE ?></a></li>
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

<form action="<?php echo tep_href_link( $sUrlPage, 'action=members' ); ?>" method="post" id="fltr-lstd" class="vntn-form mfp-hide zoom-anim-dialog oeBox mfp-white">
	<input type="hidden" name="action" value="list" />
	<div class="oeWrpr">
		<div class="oeTitu"><i class="far fa-user-crown"></i> <?php echo ADMIN_MEMBERS_HEADING_SUBTITLE_MEMBERS_LIST ?></div>
		<div class="oeCntd row ax xform xform-horizontal">
			<label for="search" class="column a02 tright"><?php echo ADMIN_MEMBERS_TEXT_SEARCH ?>:</label>
			<div class="column a10">
				<input type="text" name="filter[search]" placeholder="Introducte búsqueda" value="<?php echo $aFilter['search']; ?>"/>
			</div>
			<div class="xline xline-none"></div>
			<div class="column a12 tright">
				<?php echo ($sWhere != '' ? '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params( [ 'filter' ] ) ) . '" class="xbutton hv9 rojo small"><i class="fas fa-times"></i> Eliminar</a> ' : ''); ?>
				<div class="xbutton verde hv9 small"><input type="submit"/><span class="fa fa-filter"></span> Filtrar</div>
			</div>
		</div>
	</div>
</form>
