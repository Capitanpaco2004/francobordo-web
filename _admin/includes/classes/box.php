<?php
class box extends tableBlock {
	public array $heading = [];
	public array $contents = [];

	public function infoBox(array $heading, array $contents) {

		$this->table_row_parameters = 'class="infoBoxHeading"';
		$this->table_data_parameters = 'class="infoBoxHeading"';
		$heading = $this->tableBlock($heading);

		$this->table_row_parameters = '';
		$this->table_data_parameters = 'class="infoBoxContent"';
		$content = $this->tableBlock($contents);

		return $heading . $content;
	}

	public function menuBox(array $heading, array $contents) {
		$this->table_data_parameters = 'class="menuBoxHeading"';
		if (isset($heading[0]['link'])) {
			$this->table_data_parameters .= ' onmouseover="this.style.cursor=\'hand\'" onclick="document.location.href=\'' . $heading[0]['link'] . '\'"';
			$heading[0]['text'] = '&nbsp;<a href="' . $heading[0]['link'] . '" class="menuBoxHeadingLink">' . $heading[0]['text'] . '</a>&nbsp;';
		} else {
			$heading[0]['text'] = '&nbsp;' . $heading[0]['text'] . '&nbsp;';
		}
		$this->heading = $this->tableBlock($heading);

		$this->table_data_parameters = 'class="menuBoxContent"';
		$this->contents = $contents === [] ? '' : $this->tableBlock($contents);

		return $this->heading . $this->contents;
	}
}
