<?php namespace nmvc;

/**
 * nanoController
 */
abstract class Controller {
    /**
     * @var mixed The name of the layout to render the view inside of.
     * The name specified is the filename of the layout in /app/layouts
     * without the php extension.
     * This is instanced to a Layout when rendering.
     */
    public $layout = null;

    /**
     * Prevent constructor from beeing overriden.
     */
    public final function __construct() {}

    /**
     * This function is executed before any action in the controller.
     * It's a handy place to check for an active session or
     * inspect user permissions.
     * @param string $action_name Action name about to be called.
     * @param array $arguments Arguments that will be passed to action.
     * @return void
     */
    public function beforeFilter($action_name, $arguments) {}

    /**
     * Called after controller action logic, but before the view is rendered.
     * @param string $action_name Action name that was called.
     * @param array $arguments Arguments that was passed to action.
     * @return void
     */
    public function beforeRender($action_name, $arguments) {}

    /**
     * Called after every controller action, and after rendering is complete.
     * This is the last controller method to run.
     * @param string $action_name Action name that was called.
     * @param array $arguments Arguments that was passed to action.
     * @return void
     */
    public function afterRender($action_name, $arguments) {}
    
    /**
     * Override this prototype in AppController to rewrite incomming
     * requests to other paths in your application.
     * The function should return NULL if it does not wish to rewrite the
     * request, otherwise, it should return an array of path tokens
     * which represents the rewritten local URL.
     * @param array $path_tokens The incomming path request in tokens that
     * where separated by "/".
     * @return array
     */
    public static function rewriteRequest($path_tokens) {
        return null;
    }

    /**
     * Returns array of actions that this controller declares.
     * @return array
     */
    public static final function getActions() {
        $controller = get_called_class();
        if (core\is_abstract($controller))
            return array();
        $methods = get_class_methods($controller);
        $actions = array();
        foreach ($methods as $method) {
            $arguments = null;
            if (self::validateAction($controller, $method, $arguments))
                $actions[] = $method;
        }
        return $actions;
    }

    /**
     * Generates a path to this controller action and parameters.
     * This function does not check weather the action actually exists
     * and/or is callable/valid.
     * @param string $action Name of action or NULL for index.
     * @param array $arguments Optional array of arguments.
     * @return string Path.
     */
    public static final function getPath($action = null, $arguments = array()) {
        return self::controllerToPath(get_called_class(), $action, $arguments);
    }

    /**
     * Generates a path from a controller class name,
     * action and parameters array.
     * This function does not check weather the action actually exists
     * and/or is callable/valid.
     * @param string $controller_class_name Controller class name.
     * @param string $action Name of action or NULL for index.
     * @param array $arguments Optional array of arguments.
     * @return string Path.
     */
    public static final function controllerToPath($controller_class_name, $action = null, $arguments = array()) {
        $controller = str_replace("\\", "/", substr(string\cased_to_underline($controller_class_name), 5, -11));
        if ($action == "index")
            $action = null;
        $path = "/";
        if (!preg_match("#/?index$#", $controller)) {
            $path .= $controller;
            if ($action !== null) {
                $path .= "/" . $action;
                foreach ($arguments as $argument)
                    $path .= "/" . $$argument;
            }
        }
        return $path;
    }

    private static function arrayizePath($path) {
        if (is_array($path))
            return $path;
        if ($path[0] == "/")
            $path = substr($path, 1);
        return explode("/", $path);
    }

    /**
     * Validates if the action exists, is valid, and satisfied with the number
     * of arguments. Will by default try to squeeze the last argument together
     * into one argument unless $ignore_argument_overflow is true.
     */
    private static function validateAction($controller_class, $action, &$arguments, $ignore_argument_overflow = false) {
        // Check if controller exists and is not abstract.
        if (!class_exists($controller_class) || core\is_abstract($controller_class))
            return false;
        // Action cannot contain A-Z.
        if (preg_match("#[A-Z]#", $action))
            return false;
        // Action must exist.
        try {
            $method_reflector = new \ReflectionMethod($controller_class, $action);
        } catch (ReflectionException $e) {
            return false;
        }
        // Must be public.
        if (!$method_reflector->isPublic())
            return false;
        // Must not be static.
        if ($method_reflector->isStatic())
            return false;
        // Must not be abstract.
        if ($method_reflector->isAbstract())
            return false;
        // Action cannot be an app controller prototype.
        if (method_exists("nmvc\\AppController", $action))
            return false;
        // Validate arguments.
        $total_req_parameters = $method_reflector->getNumberOfRequiredParameters();
        if (!is_array($arguments)) {
            // Caller is not interested in argument validation,
            // only how many arguments that is required.
            $arguments = $total_req_parameters;
            return true;
        }
        // Must have a satisfactory number of arguments.
        if (count($arguments) < $total_req_parameters)
            return false;
         // If too many arguments are passed, squeeze the last arguments into a single argument.
        $max_parameters = $method_reflector->getNumberOfParameters();
        if (!$ignore_argument_overflow && count($arguments) > $max_parameters) {
            // ..unless the function doesn't even take one argument.
            if ($max_parameters == 0)
                return false;
            $last_argument = implode("/", array_slice($arguments, $max_parameters - count($arguments) - 1));
            $arguments = array_splice($arguments, 0, $max_parameters - 1);
            $arguments[$max_parameters - 1] = $last_argument;
        }
        return true;
    }

    /**
     * Searches for an action that satisfies the supplied path,
     * and returns it's related invoke data.
     * Too few arguments in path for a found action does not satisfy
     * that action. If too many arguments is supplied, the last argument
     * will contain the "remaining" data. If action takes zero arguments,
     * only paths without arguments satisfies it.
     * If the path does not satisfy any action false is returned.
     * @param mixed $path Invoke path. Either an array of path tokens or
     * an unsplit string path.
     * @return core\InvokeData Returns InvokeData or false if path is invalid.
     */
    public static final function pathToInvokeData($path) {
        $path = self::arrayizePath($path);
        // Try to load application controller first, and if
        // that doesn't exist, try to load module controller.
        for ($i = 0; $i < 2; $i++) {
            // Determine controller + action. Empty names maps to "index".
            $controller_path_name = @$path[$i];
            if (strlen($controller_path_name) == 0)
                $controller_path_name = "index";
            else if ($controller_path_name == "index" || $controller_path_name == "app") // "index" and "app" is reserved.
                continue;
            // Controller name cannot contain upper case. (Neither can modules, so break).
            else if ($i == 0 && preg_match("#[A-Z]#", $controller_path_name))
                return false;
            // Convert controller path name to class name.
            $controller_class_name = ucfirst(string\underline_to_cased($controller_path_name));
            if ($i == 0)
                $controller_class_name = "nmvc\\" . $controller_class_name . "Controller";
            else
                $controller_class_name = "nmvc\\" . $path[0] . "\\" . $controller_class_name . "Controller";
            // Read action from path.
            $action_name = strval(@$path[$i + 1]);
            if (strlen($action_name) == 0)
                $action_name = "index";
            else if ($action_name == "index") // "index" is reserved.
                return false;
            else if ($action_name[0] == "_")
                continue;
            // Action name cannot contain upper case. (Neither can controllers or modules, so break.)
            else if (preg_match("#[A-Z]#", $action_name))
                return false;
            // Try to satisfy the action requirements.
            $arguments = array_slice($path, $i + 2);
            if (self::validateAction($controller_class_name, $action_name, $arguments, false))
                return new core\InvokeData($controller_class_name, $action_name, $arguments);
        }
        return false;
    }

    /**
     * Used once by nanoMVC to invoke from the external request.
     * @param mixed $path Path as a string or array.
     */
    public static final function invokeFromExternalRequest($path) {
        $rewritten_path = AppController::rewriteRequest(self::arrayizePath($path));
        if (!is_array($path) && !is_null($path))
            trigger_error("Expected rewriteRequest to return array or null. Instead " . gettype($rewritten_path) . " was returned.", \E_USER_ERROR);
        if ($rewritten_path !== null)
            $path = $rewritten_path;
        $invoke_data = self::pathToInvokeData($path);
        if ($invoke_data === false)
            return false;
        // Cannot invoke actions starting with "_" externally.
        $action_name = $invoke_data->getActionName();
        if ($action_name[0] == "_")
            return false;
        // Invoke action on controller.
        self::internalInvoke($invoke_data, false);
        return true;
    }

    /**
     * Attempts to invoke action on this controller.
     * @param mixed $action_name Action name to invoke.
     * @param array $arguments Arguments to pass to action.
     * @param boolean $use_controller_layout Set to true to render in the
     * controller specifyed layout.
     * @return boolean True if action was found and valid, otherwise false.
     */
    public static final function invoke($action_name, $arguments = array(), $use_controller_layout = false) {
        $controller_class = get_called_class();
        if (!self::validateAction($controller_class, $action_name, $arguments, true))
            return false;
        $invoke_data = new core\InvokeData($controller_class, $action_name, $arguments);
        self::internalInvoke($invoke_data, !$use_controller_layout);
        return true;
    }

    /**
     * Internal invoke. Expects that the given action and arguments
     * has already been verified to be valid by using Controller::validateAction.
     * @param core\InvokeData $data
     */
    private static function internalInvoke(core\InvokeData $data, $ignore_controller_layout) {
        // Put this invoke on stack.
        array_push(self::$invoke_stack, $data);
        if (count(self::$invoke_stack) > 128)
            trigger_error("Invoke depth reached limit of 128 calls. There is most likely an invoke recursion death loop in the application. Aborting to prevent out of memory exception.", \E_USER_ERROR);
        // Create Controller.
        $controller = $data->getControllerClass();
        $controller = new $controller();
        // Compile event arguments.
        $action_name = $data->getActionName();
        $arguments = $data->getArguments();
        // Raise beforeFilter event.
        $controller->beforeFilter($action_name, $arguments);
        // Call the action now. Return values:
        // NULL = Render default view if it exists,
        // FALSE = Skip rendering,
        // STRING = Force rendering of this view or crash,
        // ELSE crash.
        $ret_view = call_user_func_array(array($controller, $action_name), $arguments);
        if ($ret_view === false) {
            array_pop(self::$invoke_stack);
            return;
        }
        // Raise beforeRender event.
        $controller->beforeRender($action_name, $arguments);
        if ($ignore_controller_layout)
            $controller->layout = null;
        if ($ret_view === null) {
            $view_path = "/" . str_replace("\\", "/", internal\cased_to_underline(substr(get_class($controller), 5, -10))) . "/" . $action_name;
            $found_view = View::render($view_path, $controller, false, true, true);
        } else if (is_string($ret_view)) {
            $found_view = View::render($ret_view, $controller, false, true, false);
        } else
            trigger_error("Did not understand what controller action returned (" . var_export($ret_view, true) . ").", \E_USER_ERROR);
        // Raise afterRender event.
        $controller->afterRender($action_name, $arguments);
        array_pop(self::$invoke_stack);
    }

    private static $invoke_stack = array();

    /**
     * Returns the controller that are currently beeing invoked, or NULL
     * if no controller is currently beeing invoked.
     * @return Controller
     */
    public static final function getCurrentlyInvoked() {
        $ret = end(self::$invoke_stack);
        if ($ret === false)
            $ret = null;
        return $ret;
    }
}