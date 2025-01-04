<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\Controller;

use StefanFroemken\AccountAnalyzer\Configuration;
use StefanFroemken\AccountAnalyzer\Domain\Repository\AccountRepository;
use StefanFroemken\AccountAnalyzer\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplateView;

class AccountController
{
    protected TemplateView $view;

    protected AccountRepository $accountRepository;

    public function __construct(Configuration $mainConfiguration)
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(
            $mainConfiguration->getViewConfiguration()->getTemplateRootPaths()
        );

        $renderingContext = new RenderingContext();
        $renderingContext->setControllerName('Account');
        $renderingContext->setTemplatePaths($templatePaths);

        $this->view = new TemplateView($renderingContext);
        $this->accountRepository = new AccountRepository();
    }

    public function indexAction(): TemplateView
    {
        if ($this->accountRepository->hasCsvFile()) {
            return $this->analyzeAction();
        }

        $this->view->getRenderingContext()->setControllerAction('Index');

        return $this->view;
    }

    public function uploadAction(): TemplateView
    {
        if (
            is_array($_FILES)
            && isset($_FILES['uploadFile'])
            && $_FILES['uploadFile']['error'] === 0
        ) {
            $this->flushAction();
            move_uploaded_file(
                $_FILES['uploadFile']['tmp_name'],
                sprintf(
                    '%s/Uploads/%s',
                    GeneralUtility::getIndpEnv('TYPO3_DOCUMENT_ROOT'),
                    $_FILES['uploadFile']['name']
                )
            );
        }
        return $this->analyzeAction();
    }

    public function flushAction(): TemplateView
    {
        $csvFiles = GeneralUtility::getFilesInDir(
            $this->accountRepository->getUploadsFolder(),
            '',
            true,
            '',
            '.htaccess'
        );

        if (is_array($csvFiles)) {
            foreach ($csvFiles as $csvFile) {
                @unlink($csvFile);
            }
        }

        $this->accountRepository->reset();

        return $this->indexAction();
    }

    public function analyzeAction(): TemplateView
    {
        $this->accountRepository->loadCsvData();
        $currentSortingDirection = (int)($_GET['sortDir'] ?? SORT_DESC);
        $rows = $this->accountRepository->getSorted(
            $this->accountRepository->getAll(),
            $_GET['sortBy'] ?? 'bookingTimestamp',
            $currentSortingDirection
        );

        $this->view->assignMultiple([
            'rows' => $rows,
            'sortDir' => $this->getSortingDirection($currentSortingDirection),
            'sumAmount' => $this->sumAmount($rows),
        ]);

        $this->view->getRenderingContext()->setControllerAction('Analyze');

        return $this->view;
    }

    public function groupedAction(): TemplateView
    {
        $this->accountRepository->loadCsvData();
        $month = (int)($_GET['month'] ?? 1);
        $rows = $this->accountRepository->getSorted(
            $this->accountRepository->getGroupedByMonth($month),
            'receiver'
        );

        $this->view->assignMultiple([
            'rows' => $rows,
            'month' => $month,
            'sumAmount' => $this->sumAmount($rows),
        ]);

        $this->view->getRenderingContext()->setControllerAction('Grouped');

        return $this->view;
    }

    public function yearAction(): TemplateView
    {
        $this->accountRepository->loadCsvData();

        $rows = $this->accountRepository->getGroupedByMonths();
        foreach ($rows as $monthId => $rowsForMonth) {
            $rows[$monthId]['sumAmount'] = $this->sumAmount($rowsForMonth['rows']);
        }

        $this->view->assignMultiple([
            'rows' => $rows,
        ]);

        $this->view->getRenderingContext()->setControllerAction('Year');

        return $this->view;
    }

    protected function getSortingDirection(int $currentSortingDirection): int
    {
        return $currentSortingDirection === SORT_ASC ? SORT_DESC : SORT_ASC;
    }

    protected function sumAmount($rows): string
    {
        $sum = 0;
        foreach ($rows as $row) {
            $amount = (float)str_replace(
                ',',
                '.',
                str_replace(
                    '.',
                    '',
                    $row['amount']
                )
            );
            $cent = (int)($amount * 100);
            $sum += $cent;
        }

        return number_format((float)($sum / 100), 2, ',', '.') . ' EUR';
    }
}
