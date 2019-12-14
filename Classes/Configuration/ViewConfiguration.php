<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\Configuration;

class ViewConfiguration
{
    /**
     * @var array
     */
    protected $templateRootPaths = [
        'Resources/Private/Templates/'
    ];

    /**
     * @var bool
     */
    protected $noCache = false;

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

    protected function setTemplateRootPaths(array $templateRootPaths)
    {
        $this->templateRootPaths = $templateRootPaths;
    }

    public function getTemplateRootPaths(): array
    {
        return $this->templateRootPaths;
    }

    public function setNoCache(bool $noCache)
    {
        $this->noCache = $noCache;
    }

    public function getNoCache(): bool
    {
        return $this->noCache;
    }
}