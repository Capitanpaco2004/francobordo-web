<?php

class messageStack extends tableBlock
{
	public $errors = [];
	public $messages = [];
	public $style = 'default';
	public $size = 0;

	public function __construct()
	{
		global $messageToStack;

		if (tep_session_is_registered('messageToStack')) {
			for ($i = 0, $n = count($messageToStack); $i < $n; $i++) {
				$this->add($messageToStack[$i]['text'], $messageToStack[$i]['type']);
			}
			tep_session_unregister('messageToStack');
		}
	}

	public function add($message, $type = 'error')
	{
		$error = [];
		switch ($type) {
			case 'warning':
				$error['params'] = 'msje-wrng';
				$error['class'] = 'warning';
				break;
			case 'success':
				$error['params'] = 'msje-crrt';
				$error['class'] = 'success';
				break;
			case 'info':
				$error['params'] = 'msje-info';
				$error['class'] = 'info';
				break;
			case 'error':
				$error['params'] = 'msje-eror';
				$error['class'] = 'error';
				break;
			default:
				// Valor no válido para $type
				// Puedes mostrar un mensaje de error o tomar otra acción aquí
				$error['params'] = 'msje-unknown';
				$error['class'] = 'error';
				$message = 'Tipo de mensaje no válido: ' . $type;
				break;
		}
		$error['text'] = $message;

		$this->errors[] = $error;
		$this->size++;
	}


	public function addSession($id, $message, $type = 'error')
	{
		global $messageToStack;

		if (!tep_session_is_registered('messageToStack')) {
			tep_session_register('messageToStack');
			$messageToStack = [];
		}

		$messageToStack[] = ['id' => $id, 'text' => htmlentities((string) $message), 'type' => $type, 'nuevo' => true];
		$this->errors[] = [];
		$this->size++;
	}

	public function add_session($message, $type = 'error')
	{
		global $messageToStack;

		if (!tep_session_is_registered('messageToStack')) {
			tep_session_register('messageToStack');
			$messageToStack = [];
		}

		$messageToStack[] = ['text' => $message, 'type' => $type];
		$this->size++;
	}

	public function check($id)
	{
		return isset($this->errors[$id]) && count($this->errors[$id]) > 0;
	}

	public function reset()
	{
		$this->errors = [];
		$this->size = 0;
	}

	public function output($bShow = true)
	{
		$html = '';
		foreach ($this->errors as $error) {
			if (!isset($error['text']) || $error['text'] == '') {
				continue;
			}

			switch ($this->style) {
				case 'default':
					$htmlAux = '<div class="msje ' . $error['params'] . '"><div class="msje-icon"></div>' . html_entity_decode((string) $error['text']) . '</div>';
					break;

				case 'solenopsis':
					$icons = [
						'success' => 'fa-check-circle',
						'info' => 'fa-info-circle',
						'warning' => 'fa-exclamation-triangle',
						'error' => 'fa-exclamation-circle'
					];

					$htmlAux = '<div class="xmessage xmessage-' . $error['class'] . '">
                    <input type="checkbox"/><i class="fa fa-times"></i>
                    <div>
                        <i class="fa ' . $icons[$error['class']] . '"></i>
                        ' . html_entity_decode((string) $error['text']) . '
                    </div>
                </div>';
					break;
			}

			if ($bShow) {
				echo $htmlAux;
			} else {
				$html .= $htmlAux;
			}
		}

		return $html;
	}

	public function show($id)
	{
		$messages = $this->messages;
		if (is_array($id)) {
			$messages['aux'] = ['class' => $id['class'], 'text' => $id['text']];
			$id = 'aux';
		}

		$sHtmlAux = '';

		if (isset($messages[$id]) && isset($messages[$id]['class']) && isset($messages[$id]['text'])) {
			switch ($this->style) {
				case 'default':
					$sHtmlAux = '<div class="msje msje-' . $messages[$id]['class'] . '"><div class="msje-icon"></div>' . $messages[$id]['text'] . '</div>';
					break;

				case 'solenopsis':
					$aIcons = [
						'success' => 'fa-check-circle',
						'info' => 'fa-info-circle',
						'warning' => 'fa-exclamation-triangle',
						'error' => 'fa-exclamation-circle'
					];

					$sHtmlAux = '<div class="xmessage xmessage-' . $messages[$id]['class'] . '">
                <input type="checkbox"/><i class="fa fa-times"></i>
                <div>
                    <i class="fa ' . ($aIcons[$messages[$id]['class']] ?? '') . '"></i>
                    ' . $messages[$id]['text'] . '
                </div>
            </div>';
					break;
			}
		}

		return $sHtmlAux;
	}

}
