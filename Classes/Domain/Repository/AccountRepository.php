<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\Domain\Repository;

use StefanFroemken\AccountAnalyzer\Utility\GeneralUtility;
use StefanFroemken\AccountAnalyzer\Utility\StringUtility;

class AccountRepository
{
    protected string $uploadsFolder = '';

    protected string $csvFile = '';

    protected array $columns = [
        0 => 'bookingDate',
        2 => 'receiver',
        5 => 'description',
        6 => 'saldo',
        8 => 'amount'
    ];

    protected array $csvData = [];

    public function __construct()
    {
        $this->uploadsFolder = GeneralUtility::getIndpEnv('TYPO3_DOCUMENT_ROOT') . '/Uploads';
    }

    public function loadCsvData(): bool
    {
        $this->reset();
        $csvFiles = GeneralUtility::getFilesInDir($this->uploadsFolder,'csv', true);
        if (is_array($csvFiles) && $csvFiles !== []) {
            $this->csvFile = current($csvFiles);
            $rows = file($this->csvFile);
            if (is_array($rows)) {
                foreach ($this->getCsvBody($rows) as $row) {
                    $csvData = array_combine(
                        $this->columns,
                        array_intersect_key(
                            str_getcsv($row, ';'),
                            $this->columns
                        )
                    );
                    $csvData['bookingTimestamp'] = \DateTime::createFromFormat(
                        'd.m.Y H:i:s',
                        $csvData['bookingDate'] . ' 00:00:00'
                    )->format('U');
                    $this->csvData[] = $csvData;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Return CVS rows without header rows
     */
    protected function getCsvBody(array $rows): array
    {
        $key = 0;
        foreach ($rows as $key => $row) {
            if (StringUtility::beginsWith($row, 'Buchung;')) {
                break;
            }
        }

        return array_slice($rows, $key + 1);
    }

    public function reset(): void
    {
        $this->csvFile = '';
        $this->csvData = [];
    }

    public function getUploadsFolder(): string
    {
        return $this->uploadsFolder;
    }

    public function hasCsvFile(): bool
    {
        return !empty($this->csvFile);
    }

    public function getCsvFile(): string
    {
        return $this->csvFile;
    }

    public function getAll(): array
    {
        return $this->csvData;
    }

    public function getSorted(array $data, string $column, int $sorting = SORT_ASC): array
    {
        $sortingColumnData = array_column($data, $column);
        array_multisort($sortingColumnData, $sorting, SORT_STRING, $data);
        return $data;
    }

    public function getGroupedByMonths(): array
    {
        $months = [];
        foreach ($this->csvData as $row) {
            $bookingDate = \DateTime::createFromFormat(
                'd.m.Y H:i:s',
                $row['bookingDate'] . ' 00:00:00'
            );
            $months[$bookingDate->format('n')]['month'] = $bookingDate->format('F');
            $months[$bookingDate->format('n')]['rows'][] = $row;
        }

        return $months;
    }

    public function getGroupedByMonth(int $month): array
    {
        return array_key_exists($month, $this->getGroupedByMonths()) ? $this->getGroupedByMonths()[$month]['rows'] : [];
    }
}
