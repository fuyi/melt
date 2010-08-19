<?php namespace nmvc\core;

class TextType extends \nmvc\AppType {
    private $varchar_size = null;

    public function __construct($column_name, $varchar_size = null) {
        if ($varchar_size !== null && (!is_integer($varchar_size) || $varchar_size < 0 || $varchar_size > 65535))
            trigger_error("varchar_size must be a number between 0 and 65535.", \E_USER_ERROR);
        $this->varchar_size = $varchar_size;
        parent::__construct($column_name);
    }

    public function getSQLType() {
        return ($this->varchar_size !== null)? "varchar(" . $this->varchar_size . ")": "text";
    }
    
    public function getSQLValue() {
        return strfy($this->value, $this->varchar_size);
    }
    
    public function getInterface($name) {
        $value = escape($this->value);
        $maxlength = null;
        if ($this->varchar_size !== null)
            $maxlength = "maxlength=\"" . $this->varchar_size . "\"";
        return "<input type=\"text\" $maxlength name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = @$_POST[$name];
        if ($this->varchar_size !== null)
            $this->value = iconv_substr($this->value, 0, $this->varchar_size);
    }

    public function __toString() {
        return escape(strval($this->value));
    }
}
