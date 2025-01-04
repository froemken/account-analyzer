<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\Configuration;

class ViewConfiguration
{
    protected array $templateRootPaths = [
        'Resources/Private/Templates/'
    ];

    protected bool $noCache = false;

    public function __construct(array $mainConfiguration)
    {
        if (
            array_key_exists('view', $mainConfiguration)
            && is_array($mainConfiguration['view'])
        ) {
            foreach ($mainConfiguration['view'] as $key => $value) {
                $setterMethodName = 'set' . ucfirst($key);
                if (method_exists($this, $setterMethodName)) {
                    $this->{$setterMethodName}($value);
                }
            }
        }
    }

    protected function setTemplateRootPaths(array $templateRootPaths): void
    {
        $this->templateRootPaths = $templateRootPaths;
    }

    public function getTemplateRootPaths(): array
    {
        return $this->templateRootPaths;
    }
}
