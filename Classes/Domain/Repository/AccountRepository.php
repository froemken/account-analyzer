<?php
declare(strict_types = 1);
namespace StefanFroemken\AccountAnalyzer\Domain\Repository;

use StefanFroemken\AccountAnalyzer\Utility\GeneralUtility;

class AccountRepository
{
    /**
     * @var string
     */
    protected $uploadsFolder = '';

    /**
     * @var string
     */
    protected $csvFile = '';

    /**
     * @var array
     */
    protected $columns = [
        0 => 'bookingDate',
        2 => 'receiver',
        4 => 'description',
        5 => 'saldo',
        7 => 'amount'
    ];

    /**
     * @var array
     */
    protected $csvData = [];

    public function __construct()
    {
        $this->uploadsFolder = GeneralUtility::getIndpEnv('TYPO3_DOCUMENT_ROOT') . '/Uploads';
        $csvFiles = GeneralUtility::getFilesInDir($this->uploadsFolder,'csv');
        if (is_array($csvFiles) && !empty($csvFiles)) {
            $this->csvFile = current($csvFiles);
            $rows = file($this->uploadsFolder . '/' . $this->csvFile);
            if (is_array($rows)) {
                $csvBody = array_slice($rows, 15);
                foreach ($csvBody as $position => $row) {
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
            }
        }
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

    public function getGroupedByMonths()
    {
        $months = [];
        foreach ($this->csvData as $column => $row) {
            $bookingDate = \DateTime::createFromFormat(
                'd.m.Y H:i:s',
                $row['bookingDate'] . ' 00:00:00'
            );
            $months[$bookingDate->format('n')][] = $row;
        }
        return $months;
    }

    public function getGroupedByMonth(int $month)
    {
        return $this->getGroupedByMonths()[$month];
    }
}