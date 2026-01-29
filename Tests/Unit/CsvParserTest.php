<?php

namespace StefanFroemken\AccountAnalyzer\Tests\Unit;

use StefanFroemken\AccountAnalyzer\Service\CsvParser;
use PHPUnit\Framework\TestCase;

class CsvParserTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testParseParsesStandardIngCsv()
    {
        $content = <<<CSV
Umsatzanzeige;Datei erstellt am: 07.01.2026

IBAN;DE07 1234
Sortierung;Datum absteigend

Buchung;Wertstellung;Auftraggeber;Buchungstext;Notiz;Verwendungszweck;Saldo;Währung;Betrag;Währung
30.12.2025;29.12.2025;Sender A;;;Zweck A;1000,00;EUR;-100,00;EUR
15.01.2025;15.01.2025;Empfänger B;;;Lohn 123;2000,00;EUR;1.234,56;EUR
CSV;

        file_put_contents($this->tempFile, $content);

        $parser = new CsvParser();
        $transactions = $parser->parse($this->tempFile);

        $this->assertCount(2, $transactions);
        $this->assertEquals(-100.0, $transactions[0]->getAmount());
        $this->assertEquals(1234.56, $transactions[1]->getAmount());
    }
}
