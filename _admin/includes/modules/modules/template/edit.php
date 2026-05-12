<form method="post" id="saveform-send" class="form-modules" href="<?php echo tep_href_link(FILENAME_MODULES, tep_get_all_get_params(['action']) . 'action=edit') ?>">
	<div class="oeBox column a12 row ax">
		<?php if($cModule !== false && !empty($cModule->description)): ?>
			<div class="oeWrpr">
				<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo HEADING_CRUD_DESCRIPTION ?></div>
				<div class="oeCntd ax xform xform-horizontal">
					<?php echo $cModule->description ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if(count($aModuleConfigurations) > 0): ?>
			<div class="oeWrpr" <?php echo $cModule !== false && !empty($cModule->description) ? 'style="margin-top: 40px;"' : '' ?>>
				<div class="oeTitu"><i class="fa fa-gear"></i> <?php echo HEADING_CRUD_CONFIGURATIONS ?></div>
				<div class="oeCntd row ax xform xform-horizontal">
					<?php echo $sModule !== false ? '<input type="hidden" name="id" value="' . $sModule . '" />' : '' ?>
					<input type="submit" style="display: none;" />

					<?php foreach ($aModuleConfigurations as $configurationKey => $aConfiguration): ?>
						<label for="<?php echo $configurationKey ?>" class="column a02 tright"><?php echo $aConfiguration['title'] ?>:</label>
						<div class="column a10">
							<?php echo getInputByConfiguration($aConfiguration, $configurationKey) ?>
							<div class="DFhelp"><?php echo $aConfiguration['description'] ?></div>
						</div>

						<?php if($configurationKey != array_keys($aModuleConfigurations)[count($aModuleConfigurations) - 1]): ?>
							<div class="xline xline-dashed"></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</form>
