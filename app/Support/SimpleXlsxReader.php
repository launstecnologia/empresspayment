<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Leitor mínimo de .xlsx (primeira planilha) sem dependências externas.
 */
class SimpleXlsxReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function rowsAssociativos(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Não foi possível abrir o arquivo: {$path}");
        }

        $sharedStrings = self::sharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('Planilha sheet1 não encontrada no XLSX.');
        }

        $zip->close();

        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException('XML da planilha inválido.');
        }

        $headerMap = [];
        $resultado = [];
        $isHeader = true;

        foreach ($sheet->sheetData->row as $row) {
            $cells = self::rowCells($row, $sharedStrings);

            if ($isHeader) {
                foreach ($cells as $col => $value) {
                    $headerMap[$col] = (string) $value;
                }
                $isHeader = false;
                continue;
            }

            $assoc = [];
            foreach ($headerMap as $col => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $cells[$col] ?? null;
            }

            if (self::linhaVazia($assoc)) {
                continue;
            }

            $resultado[] = $assoc;
        }

        return $resultado;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array<string, mixed>
     */
    private static function rowCells(SimpleXMLElement $row, array $sharedStrings): array
    {
        $cells = [];

        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            $col = preg_replace('/\d+/', '', $ref);
            $cells[$col] = self::cellValue($cell, $sharedStrings);
        }

        return $cells;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $strings = [];
        foreach ($doc->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }

            $texto = '';
            foreach ($si->r as $run) {
                $texto .= (string) ($run->t ?? '');
            }
            $strings[] = $texto;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private static function cellValue(SimpleXMLElement $cell, array $sharedStrings): mixed
    {
        $type = (string) ($cell['t'] ?? '');
        $value = isset($cell->v) ? (string) $cell->v : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'inlineStr' && isset($cell->is->t)) {
            return (string) $cell->is->t;
        }

        if ($value !== '' && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private static function linhaVazia(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
