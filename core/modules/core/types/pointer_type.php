<?php namespace nmvc\core;

/** A very special type that abstracts a pointer. */
class PointerType extends \nmvc\AppType {
    public $target_model;

    /** Returns the model target of this Pointer. */
    public final function getTargetModel() {
        return $this->target_model;
    }

    /** Returns a HTML representation of this pointer.
     * It will try to generate a link for the model if it can. */
    public function __toString() {
        $target = $this->get();
        if (!is_object($target))
            return "—";
        $html =(string) $target;
        if (method_exists($target, "getUrl"))
            $html = "<a href=\"" . $target->getUrl() . "\">$html</a>";
        return $html;
    }

    /** Constructs this typed field with this column name. */
    public function __construct($column_name, $target_model) {
        $this->key = $column_name;
        $target_model = 'nmvc\\' . $target_model;
        if (!class_exists($target_model) || !is_subclass_of($target_model, 'nmvc\Model'))
            trigger_error("Attempted to declare a pointer pointing to a non existing model '$target_model'.");
        $this->target_model = $target_model;
    }

    /** Resolves this pointer by ID. */
    public function getID() {
        return intval($this->value);;
    }

    /** Base pointer does not have an interface. */
    public function getInterface($name) {
        return (string) $this;
    }

    public function readInterface($name) { }

    /** Resolves this pointer and returns the model it points to. */
    public function get() {
        $id = intval($this->value);
        if ($id <= 0)
            return null;
        $target_model = $this->target_model;
        $model = $target_model::selectByID($id);
        if (!is_object($model))
            $model = null;
        return $model;
    }

    public function set($value) {
        if (is_object($value)) {
            // Make sure this is a type of model we are pointing to.
            if (!is_a($value, $this->target_model))
                trigger_error("Attempted to set a pointer to an incorrect object. The pointer expects " . $this->target_model . " objects, it was given a " . get_class($value) . " object.", \E_USER_ERROR);
            $this->value = intval($value->getID());
        } else {
            // Assuming this is an ID.
            $this->value = intval($value);
        }
    }

    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }
}
