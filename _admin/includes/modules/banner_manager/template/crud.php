<div class="oeBox column a12 row ax">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fas fa-cogs"></i> <?php echo $sSubtitle; ?></div>
		<form method="post" id="saveform-send" enctype="multipart/form-data" action="<?php echo tep_href_link($sUrlPage, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&' : '') . 'action=crud'); ?>" class="oeCntd row ax xform xform-horizontal">
			<?php echo $sGetId !== false ? '<input type="hidden" name="bID" value="' . $sGetId . '" />' : ''; ?>
			<input type="submit" style="display: none;" />

			<!-- Titulo -->
			<label for="banners_title" class="column a02 tright"><?php echo TEXT_BANNERS_TITLE; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists('banners_title', $aMessageError) ? $aMessageError['banners_title'] : ''; ?>
				<input type="text" name="banners_title" id="banners_title" value="<?php echo htmlspecialchars($aRecord['banners_title'] ?? ''); ?>" required/>
				<div class="DFhelp"><?php echo TEXT_BANNERS_TITLE_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- URL -->
			<label for="banners_url" class="column a02 tright"><?php echo TEXT_BANNERS_URL; ?>:</label>
			<div class="column a10">
				<input type="text" name="banners_url" id="banners_url" value="<?php echo htmlspecialchars($aRecord['banners_url'] ?? ''); ?>"/>
				<div class="DFhelp"><?php echo TEXT_BANNERS_URL_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- Grupo -->
			<label for="banners_group" class="column a02 tright"><?php echo TEXT_BANNERS_GROUP; ?>:</label>
			<div class="column a10">
				<?php echo array_key_exists('banners_group', $aMessageError) ? $aMessageError['banners_group'] : ''; ?>
				<?php if (count($groups_array) > 0): ?>
					<select name="banners_group" id="banners_group">
						<?php foreach ($groups_array as $group): ?>
							<option value="<?php echo htmlspecialchars($group['id']); ?>"<?php echo (($aRecord['banners_group'] ?? '') == $group['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($group['text']); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="DFhelp"><?php echo TEXT_BANNERS_GROUP_HELP; ?></div>
				<?php endif; ?>
				<div style="margin-top: 10px;">
					<input type="text" name="new_banners_group" id="new_banners_group" placeholder="<?php echo TEXT_BANNERS_NEW_GROUP; ?>" value=""/>
				</div>
				<div class="DFhelp"><?php echo TEXT_BANNERS_NEW_GROUP_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- Imagenes por idioma -->
			<?php foreach ($aLanguages as $aLanguage): ?>
				<?php $sLangFlag = '<img src="../includes/languages/' . $aLanguage['directory'] . '/images/' . $aLanguage['image'] . '" style="margin-right: 5px;" />'; ?>

				<!-- Imagen Web -->
				<label class="column a02 tright"><?php echo $sLangFlag . ' ' . BANNER_MANAGER_TEXT_IMAGE_WEB; ?>:</label>
				<div class="column a10">
					<input type="file" name="banners_image_web_<?php echo $aLanguage['id']; ?>" accept="image/*"/>
					<?php if ($sGetId != false): ?>
						<?php $aImagenesExist = glob(DIR_FS_CATALOG_IMAGES . 'banners/' . $sGetId . '_' . $aLanguage['id'] . '_w_*'); ?>
						<?php if (count($aImagenesExist) > 0): ?>
							<div style="margin-top: 10px;"><img src="../images/banners/<?php echo basename($aImagenesExist[0]); ?>" style="max-width: 200px; max-height: 100px;" /></div>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<div class="xline xline-dashed"></div>

				<!-- Imagen Tablet -->
				<label class="column a02 tright"><?php echo $sLangFlag . ' ' . BANNER_MANAGER_TEXT_IMAGE_TABLET; ?>:</label>
				<div class="column a10">
					<input type="file" name="banners_image_tablet_<?php echo $aLanguage['id']; ?>" accept="image/*"/>
					<div class="DFhelp"><?php echo BANNER_MANAGER_ONLY_RESPONSIVE; ?></div>
					<?php if ($sGetId != false): ?>
						<?php $aImagenesExist = glob(DIR_FS_CATALOG_IMAGES . 'banners/' . $sGetId . '_' . $aLanguage['id'] . '_t_*'); ?>
						<?php if (count($aImagenesExist) > 0): ?>
							<div style="margin-top: 10px;"><img src="../images/banners/<?php echo basename($aImagenesExist[0]); ?>" style="max-width: 200px; max-height: 100px;" /></div>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<div class="xline xline-dashed"></div>

				<!-- Imagen Movil -->
				<label class="column a02 tright"><?php echo $sLangFlag . ' ' . BANNER_MANAGER_TEXT_IMAGE_MOBILE; ?>:</label>
				<div class="column a10">
					<input type="file" name="banners_image_movil_<?php echo $aLanguage['id']; ?>" accept="image/*"/>
					<div class="DFhelp"><?php echo BANNER_MANAGER_ONLY_RESPONSIVE; ?></div>
					<?php if ($sGetId != false): ?>
						<?php $aImagenesExist = glob(DIR_FS_CATALOG_IMAGES . 'banners/' . $sGetId . '_' . $aLanguage['id'] . '_m_*'); ?>
						<?php if (count($aImagenesExist) > 0): ?>
							<div style="margin-top: 10px;"><img src="../images/banners/<?php echo basename($aImagenesExist[0]); ?>" style="max-width: 200px; max-height: 100px;" /></div>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<div class="xline xline-dashed"></div>
			<?php endforeach; ?>

			<!-- Fecha programada -->
			<label for="date_scheduled" class="column a02 tright"><?php echo TEXT_BANNERS_SCHEDULED_AT; ?>:</label>
			<div class="column a10">
				<input type="text" name="date_scheduled" id="date_scheduled" class="dxdatepicker" value="<?php echo htmlspecialchars($aRecord['date_scheduled'] ?? ''); ?>" placeholder="dd/mm/yyyy" style="width: 120px;"/>
				<div class="DFhelp"><?php echo TEXT_BANNERS_SCHEDULED_AT_HELP; ?></div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- Fecha de expiracion -->
			<label for="expires_date" class="column a02 tright"><?php echo TEXT_BANNERS_EXPIRES_ON; ?>:</label>
			<div class="column a10">
				<input type="text" name="expires_date" id="expires_date" class="dxdatepicker" value="<?php echo htmlspecialchars($aRecord['expires_date'] ?? ''); ?>" placeholder="dd/mm/yyyy" style="width: 120px;"/>
				<div class="DFhelp"><?php echo TEXT_BANNERS_EXPIRES_ON_HELP; ?></div>
			</div>
		</form>
	</div>
</div>
