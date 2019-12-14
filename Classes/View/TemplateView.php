<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\View;

use Monotek\MiniTPL\Template;
use StefanFroemken\AccountAnalyzer\Configuration\ViewConfiguration;

class TemplateView extends Template
{
    public function __construct(ViewConfiguration $viewConfiguration)
    {
        $this->_nocache = $viewConfiguration->getNoCache();
        parent::__construct($viewConfiguration->getTemplateRootPaths());
    }
}