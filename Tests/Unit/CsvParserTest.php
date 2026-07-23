<?php

namespace StefanFroemken\AccountAnalyzer\Tests\Unit;

use StefanFroemken\AccountAnalyzer\Service\CsvParser;
use PHPUnit\Framework\TestCase;

final class CsvParserTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
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

    public function testParseParsesCsvWithMissingLastCurrencyColumn()
    {
        $content = <<<CSV
Umsatzanzeige;Datei erstellt am: 07.01.2026

IBAN;DE07 1234
Sortierung;Datum absteigend

Buchung;Wertstellung;Auftraggeber;Buchungstext;Notiz;Verwendungszweck;Saldo;Währung;Betrag;Währung
30.12.2025;29.12.2025;Sender A;;;Zweck A;1000,00;USD;-100,00
CSV;

        file_put_contents($this->tempFile, $content);

        $parser = new CsvParser();
        $transactions = $parser->parse($this->tempFile);

        $this->assertCount(1, $transactions);
        $this->assertEquals(-100.0, $transactions[0]->getAmount());
        $this->assertEquals('USD', $transactions[0]->getCurrency());
    }

    public function testParseParsesUserActualCsv()
    {
        $content = <<<CSV
Umsatzanzeige;Datei erstellt am: 23.07.2026 21:43

IBAN;DE00 0000 0000 0000 0000 00
Kontoname;Fictional
Bank;TestBank
Kunde;Max Mustermann, Erika Mustermann
Zeitraum;01.01.2026 - 30.06.2026
Saldo;500,00;EUR

Sortierung;Datum absteigend

In der CSV-Datei finden Sie alle bereits gebuchten Umstze. Die vorgemerkten Umstze werden nicht aufgenommen, auch wenn sie in Ihrem Internetbanking angezeigt werden.

Buchung;Wertstellungsdatum;Auftraggeber/Empfnger;Buchungstext;Verwendungszweck;Saldo;Whrung;Betrag;Whrung
30.06.2026;30.06.2026;Max Mustermann;Gehalt/Rente;Fictional Income;1700,50;EUR;1.200,50;EUR
30.06.2026;30.06.2026;Supermarket;Lastschrift;Fictional Expense;1688,16;EUR;-12,34;EUR
CSV;

        file_put_contents($this->tempFile, $content);

        $parser = new CsvParser();
        $transactions = $parser->parse($this->tempFile);

        $this->assertCount(2, $transactions);
        $this->assertEquals(1200.50, $transactions[0]->getAmount());
        $this->assertEquals('Max Mustermann', $transactions[0]->getRecipient());
        $this->assertEquals('Fictional Income', $transactions[0]->getDescription());
        $this->assertEquals('EUR', $transactions[0]->getCurrency());

        $this->assertEquals(-12.34, $transactions[1]->getAmount());
        $this->assertEquals('Supermarket', $transactions[1]->getRecipient());
        $this->assertEquals('EUR', $transactions[1]->getCurrency());
    }
}
