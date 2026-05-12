<?php

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

include(DIR_WS_CLASSES.'browser.php');

class Notificaciones
{
  public $method;
  public $json;
  public $sandbox = false;
  public $id_user;
  public $claveServidor;
  public $token;
  public $session_id;
  public $mostrar_ofertas;
  public $mostrar_stock_bajo;
  public $segmento;
  private $browser;

  public function __construct()
  {
      $this->claveServidor = NOTIFICATIONS_CLAVE_SERVIDOR;
      $this->token = $_POST['token'];
      $this->method = tep_db_prepare_input($_POST['method']);
      $this->json['enabled'] = (bool)($_COOKIE['notificacion-enabled'] ?? false);
      $this->session_id = tep_session_id();
      $this->json['id_user'] = $this->id_user;
      $this->mostrar_ofertas = (NOTIFICATIONS_OFFER == 'true' ? true : false);
      $this->mostrar_stock_bajo = (NOTIFICATIONS_STOCK == 'true' ? true : false);


      $this->browser = new Browser();
      $this->json['platform'] = $this->browser->getPlatform();
      $this->segmento = $this->browser->getPlatform() . ' ' . $this->browser->getBrowser();
  }

  public function prepare()
  {
      if ($this->method != '') {
          switch ($this->method) {
              case 'text-notifications':
                  $this->json['text'] = NOTIFICACIONES_TEXT;
                  $this->json['buttons'] = '<button id="accept-notifications" class="Button">'.NOTIFICACIONES_BUTTON_YES.'</button> <button id="deny-notifications" class="Button">'.NOTIFICACIONES_BUTTON_NO.'</button>';

                  if (tep_db_prepare_input($_POST['push'])!='granted') {
                      $_COOKIE['notificacion-enabled'] = false;
                      $this->json['enabled'] = $_COOKIE['notificacion-enabled'] ;
                  } else {
                      $_COOKIE['notificacion-enabled'] = true;
                      $this->json['enabled'] = $_COOKIE['notificacion-enabled'] ;
                  }
              break;

              case 'deny-notifications':
                  $_COOKIE['notificacion-enabled'] = false;
              break;
              case 'accept-notifications':
                  $_COOKIE['notificacion-enabled'] = true;
                  $this->json['title'] = 'francobordo.com';
                  $this->json['text'] = 'A partir de ahora, recibiras ofertas y noticias de interés';
              break;

              case 'save-token':
                  $this->saveToken();
              break;
              case 'delete-token':
                  $this->deleteToken();
              break;
              case 'revoke-token':
                 $_COOKIE['notificacion-enabled'] = false;
              break;
          }
      }
  }

  public function execute()
  {
      echo json_encode($this->json);
  }

  public function saveToken() {
      global $languages_id;
        $aDatos = array(
            'token' => $this->token,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'navegador' => $_SERVER['HTTP_USER_AGENT'],
            'date_add' => 'now()',
            'topics' => NOTIFICATIONS_SHOP_ID,
            'session_id' => $this->session_id,
            'segmento' => $this->segmento,
            'languages_id' => $languages_id
        );
        //$this->json['data'][] = $aDatos;
        tep_db_perform(NOTIFICACIONES_TOKENS, $aDatos);

        $this->saveFirebase();

  }

  public function deleteToken() {

    $sSql = 'DELETE FROM ' . NOTIFICACIONES_TOKENS . ' WHERE token = "' . $this->token . '"';
    //$this->json['data'][] = $sSql;
    tep_db_query($sSql);

  }

  public function saveFirebase() {

    $url = 'https://iid.googleapis.com/iid/v1/' . $this->token . '/rel/topics/' . NOTIFICATIONS_SHOP_ID;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: 0',
        'Authorization: key=' . $this->claveServidor)
    );

    $result = curl_exec($ch);

    //$this->json['data'][] = $url;
    //$this->json['data'][] = $result;

  }
}
