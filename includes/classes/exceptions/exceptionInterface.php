<?php
// Alias
namespace util\exceptions;

interface exceptionInterface
{
    /**
     * Mensaje de exception
     *
     * @return void
     */
    public function getMessage();

    /**
     * Código de excepción definido por el usuario
     *
     * @return void
     */
    public function getCode();

    /**
     * Nombre de archivo
     *
     * @return void
     */
    public function getFile();

    /**
     * Linea
     *
     * @return void
     */
    public function getLine();

    /**
     * Conjunto backtrace()
     *
     * @return void
     */
    public function getTrace();

    /**
     * Formatea el conjunto a string
     *
     * @return void
     */
    public function getTraceAsString();

    /**
     * Metodo reemplazable heredados de la clase Exception
     *
     * @return string
     */
    public function __toString();
}
