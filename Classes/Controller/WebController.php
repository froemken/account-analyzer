<?php

namespace StefanFroemken\AccountAnalyzer\Controller;

use StefanFroemken\AccountAnalyzer\Service\CsvParser;
use StefanFroemken\AccountAnalyzer\Service\ReportService;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplateView;

class WebController
{
    private TemplateView $view;

    public function __construct()
    {
        $this->view = new TemplateView();

        // Create a new TemplatePaths object
        $paths = new TemplatePaths();
        $root = __DIR__ . '/../../';

        // Set paths on the object
        $paths->setTemplateRootPaths([$root . 'Resources/Private/Templates']);
        $paths->setLayoutRootPaths([$root . 'Resources/Private/Layouts']);
        $paths->setPartialRootPaths([$root . 'Resources/Private/Partials']);

        // Inject the paths object into the view
        $this->view->getRenderingContext()->setTemplatePaths($paths);
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $this->handleUpload();
        } else {
            $this->view
                ->getRenderingContext()
                ->getTemplatePaths()
                ->setTemplatePathAndFilename(__DIR__ . '/../../Resources/Private/Templates/Upload.html');
            echo $this->view->render();
        }
    }

    private function handleUpload(): void
    {
        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $this->view->assign('error', 'Fehler beim Upload.');
            $this->view
                ->getRenderingContext()
                ->getTemplatePaths()
                ->setTemplatePathAndFilename(__DIR__ . '/../../Resources/Private/Templates/Upload.html');

            echo $this->view->render();
            return;
        }

        try {
            $parser = new CsvParser();
            $transactions = $parser->parse($file['tmp_name']);

            // Delete file immediately
            @unlink($file['tmp_name']);

            $reporter = new ReportService();
            $report = $reporter->generateReport($transactions);

            $this->view->assign('report', $report);
            $this->view
                ->getRenderingContext()
                ->getTemplatePaths()
                ->setTemplatePathAndFilename(__DIR__ . '/../../Resources/Private/Templates/Dashboard.html');

            echo $this->view->render();
        } catch (\Exception $e) {
            $this->view->assign('error', $e->getMessage());
            $this->view
                ->getRenderingContext()
                ->getTemplatePaths()
                ->setTemplatePathAndFilename(__DIR__ . '/../../Resources/Private/Templates/Upload.html');
            echo $this->view->render();
        }
    }
}
