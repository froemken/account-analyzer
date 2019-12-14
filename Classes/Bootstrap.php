<?php
namespace StefanFroemken\AccountAnalyzer;

use StefanFroemken\AccountAnalyzer\Controller\AccountController;

class Bootstrap
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Initialize this project
     */
    public function initialize()
    {
        $this->configuration = new Configuration();
    }

    /**
     * Start project
     */
    public function run()
    {
        $this->initialize();

        $controller = new AccountController($this->configuration);
        $actionName = htmlspecialchars(strip_tags(
            ($_GET['action'] ?? 'index') . 'Action'
        ));

        if (method_exists($controller, $actionName)) {
            $view = $controller->{$actionName}();
        } else {
            $view = $controller->indexAction();
        }

        $view->render();
    }
}