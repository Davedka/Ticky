/**
 * @param array<int,array{nev:string,sorok:array<int,array<int,string>>}> $sheets
 */
function ticky_xlsx_write(array $sheets): string
{
    if ($sheets === []) {
        throw new InvalidArgumentException('Legalább egy munkalap szükséges.');
    }

    $files = [
        '[Content_Types].xml'         => ticky_xlsx_writer_content_types(count($sheets)),
        '_rels/.rels'                 => ticky_xlsx_writer_root_rels(),
        'xl/workbook.xml'             => ticky_xlsx_writer_workbook($sheets),
        'xl/_rels/workbook.xml.rels'  => ticky_xlsx_writer_workbook_rels(count($sheets)),
    ];

    foreach ($sheets as $index => $sheet) {
        $files['xl/worksheets/sheet' . ($index + 1) . '.xml'] = ticky_xlsx_writer_sheet($sheet['sorok'] ?? []);
    }

    return ticky_xlsx_writer_zip($files);
}

function ticky_xlsx_writer_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function ticky_xlsx_writer_content_types(int $sheet_count): string
{
    $overrides = '';
    for ($index = 1; $index <= $sheet_count; $index++) {
        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" '
        . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . $overrides
        . '</Types>';
}

function ticky_xlsx_writer_root_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" '
        . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
        . 'Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function ticky_xlsx_writer_workbook(array $sheets): string
{
    $entries = '';
    foreach ($sheets as $index => $sheet) {
        $entries .= '<sheet name="' . ticky_xlsx_writer_escape((string) ($sheet['nev'] ?? ('Munkalap' . ($index + 1))))
            . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $entries . '</sheets>'
        . '</workbook>';
}

function ticky_xlsx_writer_workbook_rels(int $sheet_count): string
{
    $entries = '';
    for ($index = 1; $index <= $sheet_count; $index++) {
        $entries .= '<Relationship Id="rId' . $index . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
            . 'Target="worksheets/sheet' . $index . '.xml"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $entries
        . '</Relationships>';
}

function ticky_xlsx_writer_sheet(array $rows): string
{
    $xml = '';
    $row_number = 0;

    foreach ($rows as $row) {
        $row_number++;
        $cells = '';
        $column_index = 0;

        foreach ($row as $value) {
            $column_index++;
            $text = (string) $value;
            if ($text === '') {
                continue;
            }

            $reference = ticky_xlsx_column_name($column_index) . $row_number;
            $cells .= '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
                . ticky_xlsx_writer_escape($text) . '</t></is></c>';
        }

        $xml .= '<row r="' . $row_number . '">' . $cells . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $xml . '</sheetData>'
        . '</worksheet>';
}

/**
 * ZIP összeállítás tömörítés nélkül (STORE).
 *
 * @param array<string,string> $files
 */
function ticky_xlsx_writer_zip(array $files): string
{
    $local = '';
    $central = '';
    $offset = 0;

    foreach ($files as $name => $contents) {
        $crc = crc32($contents);
        $length = strlen($contents);

        $header = pack('V', 0x04034b50)   // local file header signature
            . pack('v', 20)               // version needed
            . pack('v', 0)                // flags
            . pack('v', 0)                // method: store
            . pack('v', 0)                // mod time
            . pack('v', 0)                // mod date
            . pack('V', $crc)
            . pack('V', $length)          // compressed size
            . pack('V', $length)          // uncompressed size
            . pack('v', strlen($name))
            . pack('v', 0)                // extra length
            . $name;

        $local .= $header . $contents;

        $central .= pack('V', 0x02014b50) // central directory header signature
            . pack('v', 20)               // version made by
            . pack('v', 20)               // version needed
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', $crc)
            . pack('V', $length)
            . pack('V', $length)
            . pack('v', strlen($name))
            . pack('v', 0)                // extra
            . pack('v', 0)                // comment
            . pack('v', 0)                // disk number
            . pack('v', 0)                // internal attributes
            . pack('V', 0)                // external attributes
            . pack('V', $offset)
            . $name;

        $offset += strlen($header) + $length;
    }

    $eocd = pack('V', 0x06054b50)
        . pack('v', 0)
        . pack('v', 0)
        . pack('v', count($files))
        . pack('v', count($files))
        . pack('V', strlen($central))
        . pack('V', $offset)
        . pack('v', 0);

    return $local . $central . $eocd;
}
