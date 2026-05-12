<div class="oeBox oeTable column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-cogs"></i> <?php echo $sTableHeading ?></div>
		<form method="post" action="<?php echo tep_href_link( $sUrlPage ) ?>" class="oeCntd row ax">
			<table class="xform">
				<thead>
				<tr>
					<th><?php echo HEADING_TABLE_IMAGE ?></th>
					<th><?php echo HEADING_TABLE_CODE ?></th>
					<th><?php echo HEADING_TABLE_NAME ?></th>
					<th><?php echo HEADING_TABLE_SORT_ORDER ?></th>
					<th width="125"><?php echo HEADING_TABLE_ACTIONS ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach($aModules as $module): ?>
					<tr data-dblclick="<?php echo tep_href_link( $sUrlPage, 'action=edit&set=' . $sModuleType . '&module=' . $module->code ) ?>">
						<td>
							<?php
								if(isset($module->icon)){
									$sImageModuleName = $sModuleType . '_' . $module->icon . '.png';
								}else{
									$sImageModuleName = $sModuleType . '_' . $module->code . '.png';
								}
								$src = file_exists($sCheckoutModulePath . '/images/' . $sImageModuleName) ? $sCheckoutModulePath . '/images/' . $sImageModuleName : $sCheckoutModulePath . '/images/' . $sModuleType . '_default.png';
								if (!file_exists($src)) {
                                    $src = '/theme/web/images/general/no_image.jpg';
                                }
								echo tep_image($src, $module->title, 40, 40);
							?>
						</td>
						<td><?php echo $module->code ?></td>
						<td><?php echo $module->title ?></td>
						<td><?php echo $module->sort_order ?></td>
						<td>
							<div class="drop xfselect">
								<div><?php echo HEADING_TABLE_ACTIONS ?></div>
								<ul class="down down-dngt">
									<?php if($module->check() != '1'): ?>
										<li><a href="<?php echo tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType . '&module=' . $module->code . '&action=install') ?>" class="hv"><i class="fa fa-plus"></i><?php echo TEXT_ACTION_INSTALL ?></a></li>
									<?php else:?>
										<li><a href="<?php echo tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType . '&module=' . $module->code . '&action=edit') ?>" class="hv"><i class="fa fa-pencil"></i><?php echo TEXT_ACTION_EDIT ?></a></li>
										<li><a href="<?php echo tep_href_link(FILENAME_MODULES, 'set=' . $sModuleType . '&module=' . $module->code . '&action=remove') ?>" class="hv"><i class="fa fa-trash"></i><?php echo TEXT_ACTION_REMOVE ?></a></li>
									<?php endif; ?>
								</ul>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</form>
	</div>
</div>
