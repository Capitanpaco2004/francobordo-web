<?php

// Alias
namespace Checkout\classes;

class Router
{
    public $routes = [];
    public $controler = '';
    public $controller = '';
    public $action = '';
    public $pathRootProject = '';
    public $error404 = false;
    public $class;

    public function __construct($routes)
    {
        // Variables
        $this->pathRootProject = preg_replace('/includes\/.+$/', '', $_SERVER['SCRIPT_NAME']);
        $this->pathRootProject = $this->pathRootProject == '' || $this->pathRootProject == '/' ? '/' : trim($this->pathRootProject, '/');

        // Guardamos
        foreach ($routes as $key => $value) {
        	$this->set($key, $value[0], $value[1]);
        }

        // Obtenemos controlador y action
        $uri = $this->getUri();

        // Server y controlador y accion
        if (array_key_exists($uri, $this->routes)) {
            $aux = explode('@', $this->routes[$uri][0]);
			$this->controller = $aux[0];
			$this->action = $aux[1];
            $_SERVER['SCRIPT_NAME'] = ($this->pathRootProject != '/' ? '/' . $this->pathRootProject : ($this->pathRootProject == '/' ? '' : $this->pathRootProject)) . '/' . $this->routes[$uri][1];
            $_SERVER['SCRIPT_FILENAME'] = $this->routes[$uri][1];
        }
        else {
        	$this->error404 = true;
        }
    }

    public function set($uri, $controller, $scriptFileName)
    {
        $this->routes[$uri] = array($controller, $scriptFileName);
    }

    public function execute()
    {
		return $this->callAction($this->controller, $this->action);
    }

    protected function callAction($controller, $action)
    {
        // Variables
        global $sPathModule;

        // Todas las variables accesibles
        extract($GLOBALS);

        // Incluimos
        include $sPathModule . '/' . $controller . '.php';

        // Clase namespace
        $nameSpace = 'Checkout\\' . $controller;

        // Instancia
        $rcClass = new \ReflectionClass($nameSpace);

        // Creamos instancia de la clase action que estamos cargando
        $class = $rcClass->newInstance();

        // Si no tenemos que realizar redirect
        if ($class->redirect == false) {
	        // Titulo cabecera
	        $title = strtoupper('CHECKOUT_' . $controller . '_TITLE');
	        defined($title) ? define('HEADING_TITLE', constant($title)) : null;

	        // Obtenemos el metodo e invocamos el metodo referiendonos a la clase
	        $methodController = $rcClass->getMethod($action);
	        $html = $methodController->invokeArgs($class, array());
        }

        // Si contiene errores redireccionamos
        if ($class->redirect != false) {
            // Si contiene mensajes
            if ($class->messageError) {
                $messageStack->addSession('message_error', $class->messageError, 'error');
            }

            tep_redirect($class->redirect);
        }

        return $html;
    }

    public function getUri()
    {
        $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        if ($this->pathRootProject != '' && $this->pathRootProject != '/') {
            $uri = str_replace($this->pathRootProject . '/', '', $uri);
        }

        return $uri;
    }
}
