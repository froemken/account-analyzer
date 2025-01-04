<?php
namespace StefanFroemken\AccountAnalyzer;

use StefanFroemken\AccountAnalyzer\Controller\AccountController;

class Bootstrap
{
    protected Configuration $configuration;

    public function initialize(): void
    {
        $this->configuration = new Configuration();
    }

    /**
     * Start project
     */
    public function run(): void
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

        echo $view->render();
    }
}
