<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer;

use StefanFroemken\AccountAnalyzer\Configuration\ViewConfiguration;
use Symfony\Component\Yaml\Yaml;

class Configuration
{
    /**
     * @var array
     */
    protected $mainConfiguration = [];

    /**
     * @var ViewConfiguration
     */
    protected $viewConfiguration;

    public function __construct()
    {
        $configuration = Yaml::parseFile('Configuration/Main.yaml');
        if (is_array($configuration) && array_key_exists('main', $configuration)) {
            $this->mainConfiguration = $configuration['main'];
            $this->viewConfiguration = new ViewConfiguration($this->mainConfiguration);
        }
    }

    public function getViewConfiguration(): ViewConfiguration
    {
        return $this->viewConfiguration;
    }
}