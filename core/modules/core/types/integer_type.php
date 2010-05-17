<?php

namespace nmvc\core;

class IntegerType extends \nmvc\AppType {
    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }

    public function getInterface($name) {
        $value = intval($this->value);
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = intval(@$_POST[$name]);
    }

    public function __toString() {
        return strval(intval($this->value));
    }
}


