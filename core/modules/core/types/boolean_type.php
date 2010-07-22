<?php namespace nmvc\core;

class BooleanType extends \nmvc\AppType {
    public $true_str = "yes";
    public $false_str = "no";

    public function get() {
        return $this->value == true;
    }

    public function getSQLType() {
        return "boolean";
    }
    public function getSQLValue() {
        return $this->value? "TRUE": "FALSE";
    }
    public function getInterface($name) {
        $value = ($this->value == true)? "checked=\"checked\"": "";
        return "<input type=\"checkbox\" name=\"$name\" id=\"$name\" $value value=\"checked\" />";
    }
    public function readInterface($name) {
        $this->value = (@$_POST[$name] == "checked");
    }
    public function __toString() {
        return $this->value? $this->true_str: $this->false_str;
    }
}


