<?php namespace nmvc\core;

class TextAreaType extends TextType {
    public function getInterface($name) {
        $value = escape($this->value);
        return "<textarea name=\"$name\" id=\"$name\">$value</textarea>";
    }

    public function __toString() {
        $value = escape($this->value);
        return "<pre>$value</pre>";
    }
}
