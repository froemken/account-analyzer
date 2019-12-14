<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\Controller;

use Monotek\MiniTPL\Template;
use StefanFroemken\AccountAnalyzer\Configuration;
use StefanFroemken\AccountAnalyzer\Domain\Repository\AccountRepository;
use StefanFroemken\AccountAnalyzer\Utility\GeneralUtility;
use StefanFroemken\AccountAnalyzer\View\TemplateView;

class AccountController
{
    /**
     * @var Template
     */
    protected $view;

    /**
     * @var AccountRepository
     */
    protected $accountRepository;

    public function __construct(Configuration $mainConfiguration)
    {
        $this->view = new TemplateView($mainConfiguration->getViewConfiguration());
        $this->accountRepository = new AccountRepository();
    }

    public function indexAction()
    {
        if ($this->accountRepository->hasCsvFile()) {
            return $this->analyzeAction();
        }

        $this->view->load('Index.html');
        return $this->view;
    }

    public function uploadAction()
    {
        if (
            is_array($_FILES)
            && isset($_FILES['uploadFile'])
            && $_FILES['uploadFile']['error'] === 0
        ) {
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

    public function flushAction()
    {
        GeneralUtility::flushDirectory(
            GeneralUtility::getIndpEnv('TYPO3_DOCUMENT_ROOT') . '/Uploads',
            true
        );
        return $this->indexAction();
    }

    public function analyzeAction()
    {
        $currentSortingDirection = (int)($_GET['sortDir'] ?? SORT_DESC);
        $this->view->load('Analyze.html');
        $this->view->assign(
            'rows',
            $this->accountRepository->getSorted(
                $this->accountRepository->getAll(),
                $_GET['sortBy'] ?? 'bookingTimestamp',
                $currentSortingDirection
            )
        );
        $this->view->assign(
            'sortDir',
            $this->getSortingDirection($currentSortingDirection)
        );

        return $this->view;
    }

    public function groupedAction()
    {
        $month = (int)($_GET['month'] ?? 1);
        $this->view->load('Grouped.html');
        $this->view->assign('month', $month);
        $this->view->assign(
            'rows',
            $this->accountRepository->getSorted(
                $this->accountRepository->getGroupedByMonth($month),
                'receiver'
            )
        );

        return $this->view;
    }

    public function yearAction()
    {
        $this->view->load('Year.html');
        $this->view->assign(
            'rows',
            $this->accountRepository->getGroupedByMonths()
        );

        return $this->view;
    }

    protected function getSortingDirection(int $currentSortingDirection): int
    {
        if ($currentSortingDirection === SORT_ASC) {
            return SORT_DESC;
        } else {
            return  SORT_ASC;
        }
    }
}