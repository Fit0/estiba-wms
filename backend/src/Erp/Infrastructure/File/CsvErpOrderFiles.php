<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\File;

use App\Erp\Application\ErpOrderFiles;
use App\Erp\Domain\ErpOrder;
use App\Erp\Domain\ErpOrderLine;
use App\Erp\Domain\ErpUrgency;
use App\Erp\Domain\Exception\MalformedOrderFile;

/**
 * Reads the CSV files of the ERP: Latin-1, separated by semicolons, with a header and one row for each line
 * of an order (erp-legacy/README.md has the format). The order data is repeated on every row of the order
 * and the rows must agree.
 *
 * The line number in an error counts rows, header included.
 */
final class CsvErpOrderFiles implements ErpOrderFiles
{
    private const string TIME_ZONE = 'Europe/Madrid';

    private const array HEADER = [
        'NUMERO_PEDIDO', 'FECHA_PEDIDO', 'CLIENTE', 'DIRECCION', 'CP', 'POBLACION', 'PAIS',
        'URGENCIA', 'FECHA_LIMITE', 'LINEA', 'ARTICULO', 'UNIDADES',
    ];

    /** The columns that describe the order and are the same on every row of it. */
    private const int ORDER_COLUMNS = 9;

    #[\Override]
    public function read(string $contents): array
    {
        $rows = $this->rowsOf(mb_convert_encoding($contents, 'UTF-8', 'ISO-8859-1'));

        if ([] === $rows || self::HEADER !== array_map(trim(...), $rows[0]['fields'])) {
            throw MalformedOrderFile::at(1, 'CABECERA');
        }
        array_shift($rows);

        /** @var array<string, array{order: list<string>, lines: array<int, ErpOrderLine>, line: int}> $orders */
        $orders = [];
        foreach ($rows as $row) {
            ['line' => $line, 'fields' => $fields] = $row;
            if (\count($fields) !== \count(self::HEADER)) {
                throw MalformedOrderFile::at($line, 'COLUMNAS');
            }
            $fields = array_map(trim(...), $fields);

            $number = $this->required($fields, 0, $line);
            $orderData = \array_slice($fields, 0, self::ORDER_COLUMNS);
            $orders[$number] ??= ['order' => $orderData, 'lines' => [], 'line' => $line];

            foreach ($orderData as $column => $value) {
                if ($value !== $orders[$number]['order'][$column]) {
                    throw MalformedOrderFile::at($line, self::HEADER[$column]);
                }
            }

            $lineNumber = $this->whole($fields, 9, $line);
            if (isset($orders[$number]['lines'][$lineNumber])) {
                throw MalformedOrderFile::at($line, 'LINEA');
            }
            $orders[$number]['lines'][$lineNumber] = new ErpOrderLine($lineNumber, $this->required($fields, 10, $line), $this->whole($fields, 11, $line));
        }

        return array_values(array_map(fn (array $order): ErpOrder => $this->orderOf($order['order'], $order['lines'], $order['line']), $orders));
    }

    /**
     * @param list<string>             $fields
     * @param array<int, ErpOrderLine> $lines
     */
    private function orderOf(array $fields, array $lines, int $line): ErpOrder
    {
        $country = strtoupper($this->required($fields, 6, $line));
        if (1 !== preg_match('/^[A-Z]{2}$/', $country)) {
            throw MalformedOrderFile::at($line, 'PAIS');
        }
        $urgency = ErpUrgency::tryFrom(strtoupper($fields[7])) ?? throw MalformedOrderFile::at($line, 'URGENCIA');

        ksort($lines);

        return new ErpOrder(
            $this->required($fields, 0, $line),
            $this->date($fields[1], 'd/m/Y', $line, 'FECHA_PEDIDO'),
            $this->required($fields, 2, $line),
            $this->required($fields, 3, $line),
            $this->required($fields, 4, $line),
            $this->required($fields, 5, $line),
            $country,
            $urgency,
            $this->date($fields[8], 'd/m/Y H:i', $line, 'FECHA_LIMITE'),
            array_values($lines),
        );
    }

    /**
     * @return list<array{line: int, fields: list<string>}>
     */
    private function rowsOf(string $text): array
    {
        $stream = fopen('php://memory', 'r+');
        \assert(false !== $stream);
        fwrite($stream, $text);
        rewind($stream);

        $rows = [];
        $line = 0;
        // The escape character is left empty: the ERP doubles its quotes and never uses a backslash.
        while (false !== ($fields = fgetcsv($stream, null, ';', '"', ''))) {
            ++$line;
            if ([null] === $fields) {
                continue;
            }
            $rows[] = ['line' => $line, 'fields' => array_map(static fn (?string $field): string => $field ?? '', $fields)];
        }
        fclose($stream);

        return $rows;
    }

    /**
     * @param list<string> $fields
     */
    private function required(array $fields, int $column, int $line): string
    {
        return '' !== $fields[$column] ? $fields[$column] : throw MalformedOrderFile::at($line, self::HEADER[$column]);
    }

    /**
     * A whole number of one or more.
     *
     * @param list<string> $fields
     */
    private function whole(array $fields, int $column, int $line): int
    {
        $value = $fields[$column];
        if (1 !== preg_match('/^[0-9]{1,9}$/', $value) || (int) $value < 1) {
            throw MalformedOrderFile::at($line, self::HEADER[$column]);
        }

        return (int) $value;
    }

    private function date(string $value, string $format, int $line, string $field): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone(self::TIME_ZONE));

        // 31/02/2026 is read by PHP as the 3rd of March: only a date that reads back the same is a date.
        if (false === $date || $date->format($format) !== $value) {
            throw MalformedOrderFile::at($line, $field);
        }

        return $date;
    }
}
