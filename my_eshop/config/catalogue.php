<?php
// config/catalogue.php — vocabularies for the product attributes.
//
// The admin form suggests values and the shop builds its filter chips from the
// same source, so the two can't drift. Suggestions are exactly that: the columns
// are free text, so anything can be typed and it becomes a suggestion next time.

/** Scales are a genuinely fixed set, so this one is a closed dropdown. */
const CATALOGUE_SCALES = ['1:12','1:18','1:24','1:32','1:43','1:64','1:87'];

/** Seed suggestions, merged with whatever is already in the catalogue. */
const CATALOGUE_MANUFACTURERS = [
    'Hot Wheels','Matchbox','Tomica','Kyosho','AUTOart','Bburago','Maisto',
    'Minichamps','Greenlight','Majorette','Solido','Welly','Jada','Spark',
];
const CATALOGUE_BRANDS = [
    'Porsche','Ferrari','Lamborghini','Nissan','Toyota','Honda','BMW',
    'Mercedes-Benz','Audi','Ford','Chevrolet','McLaren','Bugatti',
    'Aston Martin','Subaru','Mazda','Mitsubishi','Lancia','Alfa Romeo','Jaguar',
];

/**
 * Distinct non-empty values of one attribute column, as currently used.
 *
 * @param string $column One of brand|manufacturer|scale — validated against a
 *                       whitelist because a column name cannot be bound as a
 *                       parameter.
 */
function catalogue_values(mysqli $conn, string $column): array
{
    $allowed = ['brand', 'manufacturer', 'scale'];
    if (!in_array($column, $allowed, true)) {
        return [];
    }
    $rows = $conn->query(
        "SELECT DISTINCT `$column` AS v FROM products WHERE `$column` <> '' ORDER BY `$column`"
    );
    if (!$rows) {
        return [];
    }
    $out = [];
    while ($r = $rows->fetch_assoc()) {
        $out[] = $r['v'];
    }
    return $out;
}

/** Seed suggestions plus values already in use, de-duplicated and sorted. */
function catalogue_suggestions(mysqli $conn, string $column): array
{
    $seed = match ($column) {
        'brand'        => CATALOGUE_BRANDS,
        'manufacturer' => CATALOGUE_MANUFACTURERS,
        'scale'        => CATALOGUE_SCALES,
        default        => [],
    };
    $all = array_unique(array_merge($seed, catalogue_values($conn, $column)));
    natcasesort($all);
    return array_values($all);
}
