<?php

	function getAdminCategoriesPermissions($aRecord){
		global $languages_id;

		$admin_categories = is_null($aRecord['admin_cat_access']) ? [] : explode(",", $aRecord['admin_cat_access']);
		$permissions_categories_query = tep_db_query("SELECT categories.categories_id, categories_description.categories_name
					FROM categories
					LEFT JOIN categories_description
						ON categories_description.categories_id = categories.categories_id
						AND categories_description.language_id=" . $languages_id . "
					ORDER BY sort_order"
		);

		$permissions_categories = ['selected' => [], 'no_selected' => []];
		$categories = [];

		while ($category = tep_db_fetch_array($permissions_categories_query)) {
			$categories[$category['categories_id']] = $category['categories_name'];
		}

		foreach (array_intersect(array_keys($categories), $admin_categories) as $categoryId) {
			$permissions_categories['selected'][] = ['id' => $categoryId, 'text' => $categories[$categoryId] . ' (ID: ' . $categoryId . ')'];
		}

		foreach (array_diff(array_keys($categories), $admin_categories) as $categoryId) {
			$permissions_categories['no_selected'][] = ['id' => $categoryId, 'text' => $categories[$categoryId] . ' (ID: ' . $categoryId . ')'];
		}

		return $permissions_categories;
	}

	function getAdminAccessPermissions($aRecord){
		$admin_rights = is_null($aRecord['admin_right_access']) ? [] : explode(",", $aRecord['admin_right_access']);
		$permissions_access = ['selected' => [], 'no_selected' => []];

		$permissions = ['CNEW', 'CEDIT', 'CMOVE', 'CDELETE', 'PNEW', 'PEDIT', 'PMOVE', 'PCOPY', 'PDELETE'];

		foreach ($permissions as $rightPermission) {
			$rightAccessPermissions[$rightPermission] = ['id' => $rightPermission, 'text' => constant("ADMIN_MEMBERS_TEXT_RIGHTS_" . $rightPermission)];
		}

		foreach (array_intersect($permissions, $admin_rights) as $permission) {
			$permissions_access['selected'][] = ['id' => $rightAccessPermissions[$permission]['id'], 'text' => $rightAccessPermissions[$permission]['text']];
		}

		foreach (array_diff($permissions, $admin_rights) as $permission) {
			$permissions_access['no_selected'][] = ['id' => $rightAccessPermissions[$permission]['id'], 'text' => $rightAccessPermissions[$permission]['text']];
		}

		return $permissions_access;
	}

	function fetchBox(&$boxes, $group_boxes, $group_box_name, $group_box_name_formated, $isBox, $toBox, $sGetId) {
		$group_boxes_files_query = tep_db_query("SELECT admin_files_id, admin_files_name, admin_groups_id
							FROM admin_files
							WHERE admin_files_is_boxes = '" . $isBox . "' AND admin_files_to_boxes = '" . $toBox . "'
							ORDER BY admin_files_name"
		);

		$boxes[$group_box_name]['group']['id'] = $group_boxes != null ? $group_boxes['admin_boxes_id'] : -1;
		$boxes[$group_box_name]['group']['name'] = $group_box_name;
		$boxes[$group_box_name]['group']['name_formatted'] = $group_box_name_formated;

		$boxes[$group_box_name]['subgroups']['selected'] = [];
		$boxes[$group_box_name]['subgroups']['no_selected'] = [];

		if($group_boxes != null) {
			$selectedGroups = $group_boxes['boxes_group_id'];
			$groupsArray = explode(",", (string) $selectedGroups);

			if ($sGetId !== false && in_array($sGetId, $groupsArray)) {
				$boxes[$group_box_name]['subgroups']['selected'][] = ['id' => $group_boxes['admin_boxes_id'], 'text' => $group_boxes['admin_boxes_name'] . ' (Sección del menu)'];
			} else {
				$boxes[$group_box_name]['subgroups']['no_selected'][] = ['id' => $group_boxes['admin_boxes_id'], 'text' => $group_boxes['admin_boxes_name'] . ' (Sección del menu)'];
			}
		}

		while($group_boxes_files = tep_db_fetch_array($group_boxes_files_query)) {
			$selectedGroups = $group_boxes_files['admin_groups_id'];
			$groupsArray = explode(",", (string) $selectedGroups);

			if($sGetId !== false && in_array($sGetId, $groupsArray)) {
				$boxes[$group_box_name]['subgroups']['selected'][] = ['id' => $group_boxes_files['admin_files_id'], 'text' => $group_boxes_files['admin_files_name']];
			} else {
				$boxes[$group_box_name]['subgroups']['no_selected'][] = ['id' => $group_boxes_files['admin_files_id'], 'text' => $group_boxes_files['admin_files_name']];
			}
		}

	}

?>
