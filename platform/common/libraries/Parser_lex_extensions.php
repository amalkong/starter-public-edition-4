<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @author Ivan Tcholakov <ivantcholakov@gmail.com>, 2015
 * @license The MIT License, http://opensource.org/licenses/MIT
 */

require_once dirname(__FILE__).'/Parser_lex_extension.php';

class Parser_lex_extensions {

    public $options; // Used for accessing data from Parser_lex driver.
    protected $loaded = array();

    public function __construct() {

        $this->ci = & get_instance();
    }

    /**
     * A callback to be used by Lex template parser for parsing the so called "callback tags".
     *
     * @param string        $name           The name of the callback tag (it would be "template.partial" for example).
     * @param array         $attributes     An associative array of the attributes set.
     * @param string        $content        If it the tag is a block tag, it will be the content contained, otherwise a blank string
     * @return string|null                  Returns a string, which will replace the tag in the content.
     *
     * @link https://github.com/pyrocms/lex
     */
    public function parser_callback($name, $attributes, $content) {

        $data = $this->locate($name, $attributes, $content);

        if (is_array($data) && $data) {

        }

        return $data ? $data : null;
    }

    protected function is_multi_array($array) {

        return (count($array) != count($array, 1));
    }

    protected function make_multi_array($array, $i = 0) {

        $result = array();

        foreach ($array as $key => $value) {

            if (is_object($value)) {
                $result[$key] = (array) $value;
            } else {
                $result[$i][$key] = $value;
            }
        }

        return $result;
    }

    public function locate($name, $attributes, $content) {

        $scope_glue = $this->options['scope_glue'];

        if (strpos($name, $scope_glue) === false) {
            return false;
        }

        list($class, $method) = explode($scope_glue, $name);

        foreach (array(APPPATH, COMMONPATH) as $directory) {

            if (file_exists($path = $directory.'parser_lex_extensions/'.$class.'.php')) {
                return $this->_process($path, $class, $method, $attributes, $content);
            }
        }
    }

    protected function _process($path, $class, $method, $attributes, $content) {

        $class = strtolower($class);
        $class_name = 'Parser_Lex_Extension_'.ucfirst($class);

        if (!isset($this->loaded[$class])) {

            include_once $path;
            $this->loaded[$class] = true;
        }

        if (!class_exists($class_name, false)) {

            log_message('error', 'Parser_Lex_Extension class "'.$class_name.'" does not exist.');
            return false;
        }

        $object = new $class_name;
        $object->set_path($path);
        $object->set_class($class);
        $object->set_method($method);
        $object->set_data($content, $attributes);

        if (
                $class == 'helper'
                &&
                (
                    $method == 'empty'
                    ||
                    $method == 'isset'
                )
        ) {
            $method = 'func_'.$method;
        }

        if (!is_callable(array($object, $method))) {

            if (property_exists($object, $method)) {
                return true;
            }

            log_message('error', 'Parser_Lex_Extension method "'.$method.'" does not exist on class "'.$class_name.'".');
            return false;
        }

        return call_user_func(array($object, $method));
    }

}