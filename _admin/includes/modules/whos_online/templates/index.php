<!-- Controles -->
<div class="oeBox column a12" style="margin-bottom: 15px;">
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-cog"></i> <?php echo TEXT_SET_REFRESH_RATE; ?></div>
		<div class="oeCntd">
			<?php echo tep_draw_form('update', FILENAME_WHOS_ONLINE, '', 'get'); ?>
				<?php if ($get_info !== '') echo tep_draw_hidden_field('info', $get_info); ?>
				<?php echo tep_draw_hidden_field(tep_session_name(), tep_session_id()); ?>

				<div class="row ax amiddle">
					<label class="column a02 tright"><?php echo TEXT_SET_REFRESH_RATE; ?>:</label>
					<div class="column a02">
						<?php echo tep_draw_pull_down_menu('refresh', $refresh_values, $get_refresh, 'onChange="this.form.submit();" style="width: 130px;"'); ?>
					</div>

					<label class="column a02 tright"><?php echo TEXT_PROFILE_DISPLAY; ?>:</label>
					<div class="column a02">
						<?php echo tep_draw_pull_down_menu('show', $show_type, $get_show, 'onChange="this.form.submit();" style="width: 130px;"'); ?>
					</div>

					<label class="column a02 tright"><?php echo TEXT_SHOW_BOTS; ?>:</label>
					<div class="column a01" style="padding-top: 5px;">
						<input type="checkbox" name="bots" value="show" id="chk_bots" onclick="this.form.submit()"<?php echo ($get_bots === 'show' ? ' checked="checked"' : ''); ?>>
						<label for="chk_bots" style="cursor: pointer;"><span></span></label>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Estadísticas + Leyenda -->
<div class="row ax" style="margin-bottom: 15px;">

	<!-- Estadísticas -->
	<div class="oeBox column a08">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-bar-chart"></i> <?php echo TEXT_NUMBER_OF_CUSTOMERS; ?></div>
			<div class="oeCntd" style="font-size: 12px;">
				<table>
					<tr>
						<td style="text-align: right; padding: 3px 10px 3px 0; font-weight: bold; width: 30px;"><?php echo $total_sess; ?></td>
						<td style="padding: 3px 0;">Total</td>
					</tr>
					<tr>
						<td style="text-align: right; padding: 3px 10px 3px 0; font-weight: bold;"><?php echo $total_dupes; ?></td>
						<td style="padding: 3px 0;"><?php echo TEXT_DUPLICATE_IP; ?></td>
					</tr>
					<tr>
						<td style="text-align: right; padding: 3px 10px 3px 0; font-weight: bold;"><?php echo $total_bots; ?></td>
						<td style="padding: 3px 0;"><?php echo TEXT_BOTS; ?></td>
					</tr>
					<tr>
						<td style="text-align: right; padding: 3px 10px 3px 0; font-weight: bold;"><?php echo $total_admin; ?></td>
						<td style="padding: 3px 0;"><?php echo TEXT_ME; ?></td>
					</tr>
					<tr style="border-top: 1px solid #eee;">
						<td style="text-align: right; padding: 6px 10px 3px 0; font-weight: bold; color: #0066CC;"><?php echo $total_cust; ?></td>
						<td style="padding: 6px 0 3px;">
							<?php echo TEXT_REAL_CUSTOMERS; ?>
							<?php if (count($ip_addrs_active) > 0): ?>
								<span style="color: <?php echo $fg_color_guest; ?>;">(<?php echo count($ip_addrs_active) . ' ' . TEXT_ACTIVE_CUSTOMERS; ?>)</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<div style="padding: 5px 0 0; color: #888; font-size: 11px; border-top: 1px solid #eee; margin-top: 5px;">
					<b><?php echo TEXT_MY_IP_ADDRESS; ?>:</b> <?php echo htmlspecialchars($admin_ip); ?>
					&nbsp;&nbsp;<?php echo TEXT_NOT_AVAILABLE; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Leyenda -->
	<div class="oeBox column a04">
		<div class="oeWrpr">
			<div class="oeTitu"><i class="fa fa-info-circle"></i> Leyenda</div>
			<div class="oeCntd" style="font-size: 11px; line-height: 2;">
				<div class="row ax">
					<div class="column a06">
						<i class="fa fa-shopping-cart" style="color: green;"></i> <?php echo TEXT_STATUS_ACTIVE_CART; ?><br>
						<i class="fa fa-user" style="color: green;"></i> <?php echo TEXT_STATUS_ACTIVE_NOCART; ?><br>
						<i class="fa fa-circle" style="color: green; font-size: 10px;"></i> <?php echo TEXT_STATUS_ACTIVE_BOT; ?>
					</div>
					<div class="column a06">
						<i class="fa fa-shopping-cart" style="color: red;"></i> <?php echo TEXT_STATUS_INACTIVE_CART; ?><br>
						<i class="fa fa-user" style="color: #ccc;"></i> <?php echo TEXT_STATUS_INACTIVE_NOCART; ?><br>
						<i class="fa fa-circle" style="color: #ccc; font-size: 10px;"></i> <?php echo TEXT_STATUS_INACTIVE_BOT; ?>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Tabla principal (ancho completo) -->
<div class="oeBox oeTable column a12">
	<div class="oeWrpr">
		<div class="oeTitu">
			<i class="fa fa-eye"></i> <?php echo HEADING_TITLE; ?>
			<small style="margin-left: 15px; font-weight: normal; font-size: 11px; color: #888;">
				<script>
					var d = new Date();
					var h = d.getHours();
					var m = d.getMinutes();
					<?php if ($time_format == 12): ?>
					var ap = h >= 12 ? ' pm' : ' am';
					h = h % 12 || 12;
					<?php else: ?>
					var ap = '';
					<?php endif; ?>
					document.write('<?php echo TEXT_LAST_REFRESH; ?> ' + h + ':' + (m < 10 ? '0' : '') + m + ap);
				</script>
			</small>
		</div>
		<div class="oeCntd row ax">
			<table class="xform">
				<thead>
					<tr>
						<th style="white-space: nowrap; text-align: center;" width="55"><?php echo TABLE_HEADING_ONLINE; ?></th>
						<th style="white-space: nowrap;"><?php echo TABLE_HEADING_FULL_NAME; ?></th>
						<th style="white-space: nowrap;"><?php echo TABLE_HEADING_IP_ADDRESS; ?></th>
						<th style="white-space: nowrap;"><?php echo TABLE_HEADING_ENTRY_TIME; ?></th>
						<th style="white-space: nowrap;"><?php echo TABLE_HEADING_LAST_CLICK; ?></th>
						<th width="200"><?php echo TABLE_HEADING_LAST_PAGE_URL; ?></th>
						<th style="text-align: center;"><?php echo TABLE_HEADING_USER_SESSION; ?></th>
						<th style="text-align: center; white-space: nowrap;"><?php echo TABLE_HEADING_HTTP_REFERER; ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($aRows as $entry):
					$row     = $entry['raw'];
					$visitor = $entry['visitor'];
					$color   = $visitor['color'];

					// Ocultar bots si no se solicitan
					if ($visitor['type'] === 'bot' && $get_bots === '') continue;

					$is_selected = $entry['is_selected'];
					$row_link = tep_href_link(FILENAME_WHOS_ONLINE, tep_get_all_get_params(['info', 'action']) . 'info=' . $row['session_id'], 'NONSSL');

					// Icono de estado
					if ($visitor['type'] === 'bot') {
						$status_icon = $entry['is_active'] ? '<i class="fa fa-circle" style="color: green;" title="' . TEXT_STATUS_ACTIVE_BOT . '"></i>' : '<i class="fa fa-circle" style="color: #ccc;" title="' . TEXT_STATUS_INACTIVE_BOT . '"></i>';
					} elseif ($entry['cart_count'] > 0) {
						$status_icon = $entry['is_active']
							? '<i class="fa fa-shopping-cart" style="color: green;" title="' . TEXT_STATUS_ACTIVE_CART . '"></i>'
							: '<i class="fa fa-shopping-cart" style="color: red;" title="' . TEXT_STATUS_INACTIVE_CART . '"></i>';
					} else {
						$status_icon = $entry['is_active']
							? '<i class="fa fa-user" style="color: green;" title="' . TEXT_STATUS_ACTIVE_NOCART . '"></i>'
							: '<i class="fa fa-user" style="color: #ccc;" title="' . TEXT_STATUS_INACTIVE_NOCART . '"></i>';
					}

					// Hostname / IP
					$hostname = $row['hostname'] ?? '';
					if ($visitor['type'] === 'admin') {
						$ip_display = TEXT_ADMIN;
					} elseif ($hostname !== '' && $hostname !== 'unknown') {
						$ip_display = '<a href="http://www.ipinfodb.com/ip_locator.php?ip=' . htmlspecialchars($row['ip_address']) . '" target="_blank" style="color: ' . $color . ';">' . htmlspecialchars($hostname) . '</a>';
					} else {
						$ip_display = htmlspecialchars($row['ip_address']);
					}
				?>
					<tr class="<?php echo $is_selected ? 'dataTableRowSelected' : ''; ?>" onclick="document.location.href='<?php echo $row_link; ?>'" style="cursor: pointer;">
						<td style="text-align: center;">
							<?php echo $status_icon; ?>
							<br>
							<small style="color: <?php echo $color; ?>;"><?php echo gmdate('H:i:s', $entry['time_online']); ?></small>
						</td>
						<td style="color: <?php echo $color; ?>;"><?php echo $entry['display_name']; ?></td>
						<td style="color: <?php echo $color; ?>;"><?php echo $ip_display; ?></td>
						<td style="color: <?php echo $color; ?>;"><?php echo date($format_string, (int)$row['time_entry']); ?></td>
						<td style="color: <?php echo $color; ?>;"><?php echo date($format_string, (int)$row['time_last_click']); ?></td>
						<td>
							<a href="<?php echo (($request_type ?? '') === 'SSL' ? HTTPS_SERVER : HTTP_SERVER) . htmlspecialchars($entry['resolved_url']['link']); ?>" target="_blank" style="color: <?php echo $color; ?>;" onclick="event.stopPropagation();">
								<?php echo $entry['resolved_url']['display']; ?>
							</a>
						</td>
						<td style="text-align: center; color: <?php echo $color; ?>;">
							<?php echo ($row['session_id'] !== $row['ip_address']) ? TEXT_IN_SESSION : TEXT_NO_SESSION; ?>
						</td>
						<td style="text-align: center; color: <?php echo $color; ?>;">
							<?php echo ($row['http_referer'] == '') ? TEXT_HTTP_REFERER_NOT_FOUND : TEXT_HTTP_REFERER_FOUND; ?>
						</td>
					</tr>

					<?php // Carrito del visitante seleccionado (inline)
					if ($is_selected && $visitor['type'] !== 'bot'): ?>
					<tr>
						<td style="background: #f5f9fc; border-left: 3px solid #5d9cec;"></td>
						<td colspan="7" style="background: #f5f9fc; padding: 10px 15px; border-left: none;">
							<div class="row ax atop">
								<!-- Detalles del visitante -->
								<div class="column a07" style="font-size: 12px; color: <?php echo $color; ?>;">
									<b><?php echo TABLE_HEADING_FULL_NAME; ?>:</b> <?php echo htmlspecialchars($row['full_name']); ?><br>
									<b><?php echo TABLE_HEADING_CUSTOMER_ID; ?>:</b> <?php echo (int)$row['customer_id']; ?>&nbsp;&nbsp;
									<b><?php echo TABLE_HEADING_IP_ADDRESS; ?>:</b>
									<a href="http://www.ipinfodb.com/ip_locator.php?ip=<?php echo htmlspecialchars($row['ip_address']); ?>" target="_blank" onclick="event.stopPropagation();"><?php echo htmlspecialchars($row['ip_address']); ?></a><br>
									<b><?php echo TEXT_USER_AGENT; ?>:</b> <?php echo htmlspecialchars($row['user_agent'] ?? ''); ?><br>
									<?php if (!empty($row['country_name']) || !empty($row['city'])): ?>
										<?php
											$geo_parts = array_filter([
												!empty($row['city']) ? htmlspecialchars($row['city']) : '',
												!empty($row['region_name']) ? htmlspecialchars($row['region_name']) : '',
												!empty($row['country_name']) ? htmlspecialchars($row['country_name']) : '',
											]);
										?>
										<b><?php echo TEXT_COUNTRY; ?>:</b> <?php echo implode(', ', $geo_parts); ?><br>
									<?php endif; ?>
									<?php if ($row['http_referer'] != ''): ?>
										<b><?php echo TABLE_HEADING_HTTP_REFERER; ?>:</b>
										<a href="<?php echo htmlspecialchars($row['http_referer']); ?>" target="_blank" onclick="event.stopPropagation();"><?php echo wordwrap(htmlspecialchars($row['http_referer']), $referrer_wordwrap_chars, '<br>', true); ?></a><br>
									<?php endif; ?>
								</div>

								<!-- Carrito -->
								<div class="column a05" style="font-size: 12px; border-left: 1px solid #dde5ed; padding-left: 15px;">
									<b><i class="fa fa-shopping-cart"></i> <?php echo TABLE_HEADING_SHOPPING_CART; ?></b>
									<div style="margin-top: 5px;">
										<?php if (!empty($contents)): ?>
											<?php foreach ($contents as $item): ?>
												<div style="<?php echo isset($item['align']) ? 'text-align: ' . $item['align'] . ';' : ''; ?> padding: 2px 0;">
													<?php echo $item['text']; ?>
												</div>
											<?php endforeach; ?>
										<?php else: ?>
											<i><?php echo TEXT_EMPTY; ?></i>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<?php endif; ?>

					<?php // Detalles expandidos para bots (sin carrito)
					if ($is_selected && $visitor['type'] === 'bot'): ?>
					<tr>
						<td style="background: #f5f9fc; border-left: 3px solid #800000;"></td>
						<td colspan="7" style="background: #f5f9fc; padding: 10px 15px; font-size: 12px; color: <?php echo $color; ?>;">
							<b><?php echo TABLE_HEADING_FULL_NAME; ?>:</b> <?php echo htmlspecialchars($row['full_name']); ?><br>
							<b><?php echo TABLE_HEADING_IP_ADDRESS; ?>:</b>
							<a href="http://www.ipinfodb.com/ip_locator.php?ip=<?php echo htmlspecialchars($row['ip_address']); ?>" target="_blank" onclick="event.stopPropagation();"><?php echo htmlspecialchars($row['ip_address']); ?></a><br>
							<b><?php echo TEXT_USER_AGENT; ?>:</b> <?php echo htmlspecialchars($row['user_agent'] ?? ''); ?><br>
							<?php if (!empty($row['country_name'])): ?>
								<b><?php echo TEXT_COUNTRY; ?>:</b> <?php echo htmlspecialchars($row['country_name']); ?><br>
							<?php endif; ?>
						</td>
					</tr>
					<?php endif; ?>

					<?php
					// Detalles expandidos (perfil display: all/bots/cust) - solo si NO es el seleccionado (que ya tiene su panel)
					$show = $get_show;
					if (!$is_selected && ($show === 'all' || ($show === 'bots' && $visitor['type'] === 'bot') || ($show === 'cust' && in_array($visitor['type'], ['guest', 'account', 'admin'])))): ?>
					<tr>
						<td></td>
						<td colspan="7" style="color: <?php echo $color; ?>; padding: 8px 12px; background: #f9f9f9; border-top: 1px dashed #ddd; font-size: 12px;">
							<b><?php echo TABLE_HEADING_FULL_NAME; ?>:</b> <?php echo htmlspecialchars($row['full_name']); ?>&nbsp;&nbsp;
							<?php if ($visitor['type'] !== 'bot'): ?>
								<b><?php echo TABLE_HEADING_CUSTOMER_ID; ?>:</b> <?php echo (int)$row['customer_id']; ?>&nbsp;&nbsp;
							<?php endif; ?>
							<b><?php echo TABLE_HEADING_IP_ADDRESS; ?>:</b> <?php echo htmlspecialchars($row['ip_address']); ?>&nbsp;&nbsp;
							<b><?php echo TEXT_USER_AGENT; ?>:</b> <?php echo htmlspecialchars($row['user_agent'] ?? ''); ?>
						</td>
					</tr>
					<?php endif; ?>

				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ($http_referer_url !== ''): ?>
			<div style="padding: 8px 12px; font-size: 12px;">
				<strong><?php echo TEXT_HTTP_REFERER_URL; ?>:</strong>
				<a href="<?php echo htmlspecialchars($http_referer_url); ?>" target="_blank"><?php echo wordwrap(htmlspecialchars($http_referer_url), $referrer_wordwrap_chars, '<br>', true); ?></a>
			</div>
			<?php endif; ?>

		</div>
	</div>
</div>
