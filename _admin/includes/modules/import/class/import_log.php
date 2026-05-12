<?php

class import_log
{
	private $total;
	private $row;

	public function __construct()
	{
		$this->total = 0;
		$this->row = 0;

		$this->clean();
	}

	public function log($text, $temporary = 0)
	{
		$this->cleanTemporary();

		$insert = array();
		$insert['import_log_text'] = $text;
		$insert['import_log_temporary'] = $temporary;
		tep_db_perform('import_log', $insert);
	}

	public function clean()
	{
		tep_db_query('TRUNCATE TABLE import_log;');
	}

	public function cleanTemporary()
	{
		tep_db_query('DELETE FROM import_log WHERE import_log_temporary = 1;');
	}

	public function setTotal($total)
	{
		$this->total = $total;
	}

	public function addRow()
	{
		$this->row += 1;

		$this->log('Procesando registros: <b>' . number_format((($this->row * 100) / $this->total), 0, ',', '') . '%</b>.', 1);
	}
}

?>