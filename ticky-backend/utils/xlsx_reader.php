const TICKY_XLSX_MAX_FILE_BYTES  = 12 * 1024 * 1024;  // feltöltött fájl felső határ
const TICKY_XLSX_MAX_ENTRY_BYTES = 64 * 1024 * 1024;  // egy kicsomagolt XML felső határa (zip bomb védelem)
const TICKY_XLSX_MAX_SHEETS      = 200;
const TICKY_XLSX_MAX_ROWS        = 20000;             // munkalaponként
const TICKY_XLSX_MAIN_NS         = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
const TICKY_XLSX_REL_NS          = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

class TickyXlsxException extends RuntimeException
{
}

// ─────────────────────────────────────────────────────────────────
// ZIP réteg
// ─────────────────────────────────────────────────────────────────

/**
 * Kicsomagolja a ZIP azon bejegyzéseit, amelyekre az XLSX olvasáshoz szükség van.
 * ZipArchive-ot használ, ha elérhető; különben saját central directory parser.
 *
 * @return array<string,string> belső útvonal => tartalom
 */
function ticky_zip_read_entries(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new TickyXlsxException('A feltöltött fájl nem olvasható.');
    }

    if (class_exists('ZipArchive')) {
        return ticky_zip_read_entries_ziparchive($path);
    }

    return ticky_zip_read_entries_manual($path);
}

function ticky_zip_entry_is_wanted(string $name): bool
{
    if ($name === '' || str_ends_with($name, '/')) {
        return false;
    }

    // Csak a ténylegesen feldolgozott XML-ek. A képeket, thumbnaileket,
    // VBA projektet és minden mást szándékosan nem olvassuk be.
    return $name === '[Content_Types].xml'
        || $name === 'xl/workbook.xml'
        || $name === 'xl/sharedStrings.xml'
        || $name === 'xl/_rels/workbook.xml.rels'
        || str_starts_with($name, 'xl/worksheets/');
}

function ticky_zip_read_entries_ziparchive(string $path): array
{
    $zip = new ZipArchive();
    $opened = $zip->open($path);
    if ($opened !== true) {
        throw new TickyXlsxException('A fájl nem nyitható meg ZIP-ként (hibakód: ' . (int) $opened . ').');
    }

    $entries = [];
    $count = $zip->numFiles;
    for ($index = 0; $index < $count; $index++) {
        $stat = $zip->statIndex($index);
        if (!is_array($stat)) {
            continue;
        }

        $name = (string) ($stat['name'] ?? '');
        if (!ticky_zip_entry_is_wanted($name)) {
            continue;
        }

        if ((int) ($stat['size'] ?? 0) > TICKY_XLSX_MAX_ENTRY_BYTES) {
            $zip->close();
            throw new TickyXlsxException('Túl nagy XML a fájlban: ' . $name);
        }

        $contents = $zip->getFromIndex($index);
        if ($contents === false) {
            continue;
        }

        $entries[$name] = $contents;
    }

    $zip->close();
    return $entries;
}

/**
 * Minimál ZIP olvasó: EOCD → central directory → local header → deflate.
 * Csak a "stored" (0) és "deflate" (8) tömörítést támogatja, ami az XLSX-nél
 * minden gyakorlati esetet lefed.
 */
function ticky_zip_read_entries_manual(string $path): array
{
    $data = file_get_contents($path);
    if ($data === false) {
        throw new TickyXlsxException('A fájl nem olvasható be.');
    }

    $size = strlen($data);
    $eocd = ticky_zip_find_eocd($data, $size);
    if ($eocd === null) {
        throw new TickyXlsxException('Nem található ZIP záró rekord – a fájl valószínűleg sérült vagy nem XLSX.');
    }

    $entry_count = ticky_zip_uint16($data, $eocd + 10);
    $cd_offset   = ticky_zip_uint32($data, $eocd + 16);

    if ($cd_offset >= $size) {
        throw new TickyXlsxException('Sérült ZIP szerkezet (érvénytelen central directory eltolás).');
    }

    $entries = [];
    $cursor = $cd_offset;
    for ($index = 0; $index < $entry_count; $index++) {
        if ($cursor + 46 > $size || substr($data, $cursor, 4) !== "PK\x01\x02") {
            break;
        }

        $method        = ticky_zip_uint16($data, $cursor + 10);
        $compressed    = ticky_zip_uint32($data, $cursor + 20);
        $uncompressed  = ticky_zip_uint32($data, $cursor + 24);
        $name_length   = ticky_zip_uint16($data, $cursor + 28);
        $extra_length  = ticky_zip_uint16($data, $cursor + 30);
        $comment_len   = ticky_zip_uint16($data, $cursor + 32);
        $local_offset  = ticky_zip_uint32($data, $cursor + 42);
        $name          = substr($data, $cursor + 46, $name_length);

        $cursor += 46 + $name_length + $extra_length + $comment_len;

        if (!ticky_zip_entry_is_wanted($name)) {
            continue;
        }

        if ($compressed === 0xFFFFFFFF || $uncompressed === 0xFFFFFFFF || $local_offset === 0xFFFFFFFF) {
            throw new TickyXlsxException('ZIP64 formátumú fájl – mentsd újra az Excelben normál XLSX-ként.');
        }

        if ($uncompressed > TICKY_XLSX_MAX_ENTRY_BYTES) {
            throw new TickyXlsxException('Túl nagy XML a fájlban: ' . $name);
        }

        $contents = ticky_zip_inflate_entry($data, $size, $local_offset, $method, $compressed, $name);
        if ($contents !== null) {
            $entries[$name] = $contents;
        }
    }

    return $entries;
}

function ticky_zip_inflate_entry(
    string $data,
    int $size,
    int $local_offset,
    int $method,
    int $compressed,
    string $name
): ?string {
    if ($local_offset + 30 > $size || substr($data, $local_offset, 4) !== "PK\x03\x04") {
        throw new TickyXlsxException('Sérült ZIP bejegyzés: ' . $name);
    }

    $name_length  = ticky_zip_uint16($data, $local_offset + 26);
    $extra_length = ticky_zip_uint16($data, $local_offset + 28);
    $start = $local_offset + 30 + $name_length + $extra_length;

    if ($start + $compressed > $size) {
        throw new TickyXlsxException('Csonka ZIP bejegyzés: ' . $name);
    }

    $raw = substr($data, $start, $compressed);

    if ($method === 0) {
        return $raw;
    }

    if ($method !== 8) {
        throw new TickyXlsxException('Nem támogatott ZIP tömörítés (' . $method . '): ' . $name);
    }

    $inflated = @gzinflate($raw);
    if ($inflated === false) {
        throw new TickyXlsxException('Nem sikerült kicsomagolni: ' . $name);
    }

    return $inflated;
}

function ticky_zip_find_eocd(string $data, int $size): ?int
{
    $min = max(0, $size - 65557); // max ZIP comment (65535) + EOCD fejléc (22)
    for ($offset = $size - 22; $offset >= $min; $offset--) {
        if (substr($data, $offset, 4) === "PK\x05\x06") {
            return $offset;
        }
    }

    return null;
}

function ticky_zip_uint16(string $data, int $offset): int
{
    $parts = unpack('v', substr($data, $offset, 2));
    return is_array($parts) ? (int) $parts[1] : 0;
}

function ticky_zip_uint32(string $data, int $offset): int
{
    $parts = unpack('V', substr($data, $offset, 4));
    return is_array($parts) ? (int) $parts[1] : 0;
}

// ─────────────────────────────────────────────────────────────────
// XML réteg
// ─────────────────────────────────────────────────────────────────

/**
 * Biztonságos XML betöltés: DOCTYPE/ENTITY tiltás (XXE és billion-laughs
 * védelem), hálózati hozzáférés kikapcsolva. Admin által feltöltött fájlt
 * dolgozunk fel, ezért itt nem hagyatkozunk csak az alapértelmezésekre.
 */
function ticky_xlsx_parse_xml(string $contents, string $label): SimpleXMLElement
{
    if (preg_match('/<!(?:DOCTYPE|ENTITY)\b/i', $contents) === 1) {
        throw new TickyXlsxException('Nem engedélyezett XML szerkezet (' . $label . ').');
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();

    $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$xml instanceof SimpleXMLElement) {
        throw new TickyXlsxException('Hibás XML a fájlban (' . $label . ').');
    }

    return $xml;
}

/** A <si>/<is> elem összes <t> szövegének összefűzése (rich text kezelés). */
function ticky_xlsx_collect_text(SimpleXMLElement $node): string
{
    $dom = dom_import_simplexml($node);
    $text = '';

    foreach ($dom->getElementsByTagNameNS(TICKY_XLSX_MAIN_NS, 't') as $element) {
        // A <rPh> (fonetikus) blokkok szövegét nem vesszük át.
        $parent = $element->parentNode;
        if ($parent !== null && $parent->localName === 'rPh') {
            continue;
        }
        $text .= $element->textContent;
    }

    return $text;
}

function ticky_xlsx_shared_strings(array $entries): array
{
    if (!isset($entries['xl/sharedStrings.xml'])) {
        return [];
    }

    $xml = ticky_xlsx_parse_xml($entries['xl/sharedStrings.xml'], 'sharedStrings.xml');

    $strings = [];
    foreach ($xml->si as $item) {
        $strings[] = ticky_xlsx_collect_text($item);
    }

    return $strings;
}

/** "B12" → "B" */
function ticky_xlsx_column_of(string $reference): string
{
    return preg_replace('/\d+/', '', $reference) ?? '';
}

/** "B12" → 12 */
function ticky_xlsx_row_of(string $reference): int
{
    return (int) (preg_replace('/\D+/', '', $reference) ?? '0');
}

/** "A" → 1, "B" → 2, "AA" → 27 */
function ticky_xlsx_column_index(string $column): int
{
    $index = 0;
    $column = strtoupper($column);
    $length = strlen($column);

    for ($position = 0; $position < $length; $position++) {
        $index = ($index * 26) + (ord($column[$position]) - 64);
    }

    return $index;
}

/** 1 → "A", 27 → "AA" */
function ticky_xlsx_column_name(int $index): string
{
    $name = '';
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $index = (int) (($index - $remainder - 1) / 26);
    }

    return $name;
}

function ticky_xlsx_cell_value(SimpleXMLElement $cell, array $shared_strings): string
{
    $type = (string) ($cell['t'] ?? '');

    if ($type === 'inlineStr') {
        $inline = $cell->is;
        return $inline instanceof SimpleXMLElement ? ticky_xlsx_collect_text($inline) : '';
    }

    $value = isset($cell->v) ? (string) $cell->v : '';
    if ($value === '') {
        return '';
    }

    if ($type === 's') {
        $index = (int) $value;
        return $shared_strings[$index] ?? '';
    }

    if ($type === 'b') {
        return $value === '1' ? 'IGAZ' : 'HAMIS';
    }

    // 'str' (képlet eredménye), 'e' (hiba) és a típus nélküli szám is
    // nyers szövegként megy tovább; az idő/dátum értelmezés a normalizáló
    // rétegben történik, mert csak ott tudjuk, melyik oszlopról van szó.
    return $value;
}

/**
 * @return array<int,array<string,string>> sor szám => (oszlop betű => érték)
 */
function ticky_xlsx_sheet_rows(string $contents, array $shared_strings, string $label): array
{
    $xml = ticky_xlsx_parse_xml($contents, $label);
    $rows = [];
    $auto_row = 0;

    foreach ($xml->sheetData->row as $row) {
        $row_number = (int) ($row['r'] ?? 0);
        if ($row_number <= 0) {
            $row_number = ++$auto_row;
        } else {
            $auto_row = $row_number;
        }

        if (count($rows) >= TICKY_XLSX_MAX_ROWS) {
            throw new TickyXlsxException('Túl sok sor a(z) ' . $label . ' munkalapon (max ' . TICKY_XLSX_MAX_ROWS . ').');
        }

        $auto_column = 0;
        $cells = [];
        foreach ($row->c as $cell) {
            $reference = (string) ($cell['r'] ?? '');
            $column = $reference !== '' ? ticky_xlsx_column_of($reference) : '';
            if ($column === '') {
                $column = ticky_xlsx_column_name(++$auto_column);
            } else {
                $auto_column = ticky_xlsx_column_index($column);
            }

            $value = ticky_xlsx_cell_value($cell, $shared_strings);
            if ($value !== '') {
                $cells[$column] = $value;
            }
        }

        if ($cells !== []) {
            $rows[$row_number] = $cells;
        }
    }

    return $rows;
}

/**
 * Beolvassa a teljes munkafüzetet.
 *
 * @return array{munkalapok: array<int,array{nev:string,sorok:array<int,array<string,string>>}>}
 */
function ticky_xlsx_open(string $path): array
{
    $entries = ticky_zip_read_entries($path);

    if (!isset($entries['xl/workbook.xml'])) {
        throw new TickyXlsxException('Hiányzik az xl/workbook.xml – a fájl nem érvényes XLSX.');
    }

    $shared_strings = ticky_xlsx_shared_strings($entries);
    $relations = ticky_xlsx_workbook_relations($entries);
    $workbook = ticky_xlsx_parse_xml($entries['xl/workbook.xml'], 'workbook.xml');

    $sheets = [];
    foreach ($workbook->sheets->sheet as $sheet) {
        if (count($sheets) >= TICKY_XLSX_MAX_SHEETS) {
            throw new TickyXlsxException('Túl sok munkalap (max ' . TICKY_XLSX_MAX_SHEETS . ').');
        }

        $name = trim((string) ($sheet['name'] ?? ''));
        $rel_id = (string) ($sheet->attributes(TICKY_XLSX_REL_NS)['id'] ?? '');
        $target = $relations[$rel_id] ?? null;

        if ($target === null || !isset($entries[$target])) {
            continue;
        }

        $sheets[] = [
            'nev'   => $name,
            'sorok' => ticky_xlsx_sheet_rows($entries[$target], $shared_strings, $name !== '' ? $name : $target),
        ];
    }

    if ($sheets === []) {
        throw new TickyXlsxException('A munkafüzet nem tartalmaz olvasható munkalapot.');
    }

    return ['munkalapok' => $sheets];
}

/** @return array<string,string> rId => normalizált belső útvonal */
function ticky_xlsx_workbook_relations(array $entries): array
{
    if (!isset($entries['xl/_rels/workbook.xml.rels'])) {
        return [];
    }

    $xml = ticky_xlsx_parse_xml($entries['xl/_rels/workbook.xml.rels'], 'workbook.xml.rels');

    $relations = [];
    foreach ($xml->Relationship as $relation) {
        $id = (string) ($relation['Id'] ?? '');
        $target = (string) ($relation['Target'] ?? '');
        if ($id === '' || $target === '') {
            continue;
        }

        $relations[$id] = ticky_xlsx_normalize_target($target);
    }

    return $relations;
}

/** A rels Target lehet "worksheets/sheet1.xml" vagy "/xl/worksheets/sheet1.xml". */
function ticky_xlsx_normalize_target(string $target): string
{
    $target = str_replace('\\', '/', trim($target));

    if (str_starts_with($target, '/')) {
        return ltrim($target, '/');
    }

    return 'xl/' . ltrim($target, './');
}
