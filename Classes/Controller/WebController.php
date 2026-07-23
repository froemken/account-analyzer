<?php

namespace StefanFroemken\AccountAnalyzer\Controller;

use StefanFroemken\AccountAnalyzer\Service\CsvParser;
use StefanFroemken\AccountAnalyzer\Service\ReportService;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplateView;

final class WebController
{
    private TemplateView $view;

    public function __construct()
    {
        $this->view = new TemplateView();
        $paths = new TemplatePaths();
        $root = __DIR__ . '/../../';

        $paths->setTemplateRootPaths([$root . 'Resources/Private/Templates']);
        $paths->setLayoutRootPaths([$root . 'Resources/Private/Layouts']);
        $paths->setPartialRootPaths([$root . 'Resources/Private/Partials']);

        $this->view->getRenderingContext()->setTemplatePaths($paths);
    }

    public function handleRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_FILES['csv_file'])) {
            $this->handleUpload();
        } else {
            $this->renderView('Upload');
        }
    }

    private function handleUpload(): void
    {
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $this->renderView('Upload', ['error' => 'Fehler beim Upload.']);
            return;
        }

        try {
            $parser = new CsvParser();
            $transactions = $parser->parse($file['tmp_name']);
            @unlink($file['tmp_name']);

            $reporter = new ReportService();
            $report = $reporter->generateReport($transactions);

            $this->renderView('Dashboard', ['report' => $report]);
        } catch (\Exception $e) {
            $this->renderView('Upload', ['error' => $e->getMessage()]);
        }
    }

    private function renderView(string $templateName, array $variables = []): void
    {
        foreach ($variables as $key => $value) {
            $this->view->assign($key, $value);
        }
        $this->view->getRenderingContext()
            ->getTemplatePaths()
            ->setTemplatePathAndFilename(__DIR__ . '/../../Resources/Private/Templates/' . $templateName . '.html');
        echo $this->view->render();
    }
}
