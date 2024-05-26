<?php

declare(strict_types=1);

/* Helpers */
function dd(...$vars): void
{
    foreach ($vars as $var) {
        var_dump($var);
        echo PHP_EOL;
    }

    die;
}

function get_documents_with_terms(string $path): array
{
    $documents = array_filter(scandir($path), fn($name) => strpos($name, '.txt') !== false);
    $doc_terms = [];

    foreach ($documents as $document_name) {
        $file_path = "$path/$document_name";
        $content = file_get_contents($file_path);
        $terms = preg_split("/[\s,]+/", $content);

        $terms = array_map(fn (string $val) => strtolower($val), $terms);

        $doc_terms[$document_name] = $terms;
    }

    return $doc_terms;
}

function get_tf_table(array $documents_with_terms): array
{
    return array_map(fn ($el) => array_count_values($el), $documents_with_terms);
}

function get_idf_table(array $documents_with_terms, array $tf_table): array
{
    $terms_table = [];
    $documents_list = array_keys($documents_with_terms);
    foreach ($documents_with_terms as $document_with_terms) {
        $terms_table = array_merge($terms_table, $document_with_terms);
    }

    $terms_in_documents = [];

    foreach ($terms_table as $term) {
        $term = strtolower($term);

        if (empty($terms_in_documents[$term])) {
            $terms_in_documents[$term] = [];
        }

        foreach ($tf_table as $document_name => $terms) {
            if (!in_array($document_name, $terms_in_documents[$term])
                && in_array($term, array_keys($terms))) {
                array_push($terms_in_documents[$term], $document_name);
                break;
            }
        }
    }

    //IDF: obtained by dividing the total number of documents
    //by the number of documents containing the term
    //and then taking the logarithm of that quotient

    $idf_table = [];
    $documents_count = count($documents_with_terms);

    foreach ($terms_table as $term) {
        $idf_table[$term] = log($documents_count / count($terms_in_documents[$term]));
    }

    return $idf_table;
}

function get_ranking(array $search, array $tf_table, array $idf_table): array
{
    $ranking = [];

    foreach ($search as $s) {
        foreach ($tf_table as $document => $tf) {
            if (!isset($ranking[$document])) {
                $ranking[$document] = [];
            }

            if (!isset($tf[$s]) || !isset($idf_table[$s])) {
                $ranking[$document][] = 0;
                continue;
            }

            $x = $tf[$s] * $idf_table[$s];
            $ranking[$document][] = $x;
        }
    }

    $ranking = array_map(fn ($r) => array_reduce(
        $r,
        fn ($carry, $item) => $carry += $item) / count($search), $ranking
    );

    asort($ranking);

    return array_reverse($ranking);
}

function parse_search(string $search): array
{
    $search = preg_split("/[\s,]+/", $search);
    return array_map(fn ($val) => strtolower($val), $search);
}

function main(int $argc, array $argv): void
{
    if ($argc < 2) {
        echo 'No term to search';
        exit(0);
    }

    $input = parse_search($argv[1]);

    $documents_with_terms = get_documents_with_terms('./dataset');

    $tf_table = get_tf_table($documents_with_terms);
    $idf_table = get_idf_table($documents_with_terms, $tf_table);
    $ranking = get_ranking($input, $tf_table, $idf_table);

    dd($ranking);

    /**
     * $ php index.php 'plays childhood market'
     *
     * Output:
     *
     * array(10) {
     *   ["Finance.txt"]=>
     *   float(3.0701134573253945)
     *   ["Education.txt"]=>
     *   float(2.839064397138746)
     *   ["Literature.txt"]=>
     *   float(0.5364793041447001)
     *   ["Travel.txt"]=>
     *   int(0)
     *   ["Technology.txt"]=>
     *   int(0)
     *   ["Sports.txt"]=>
     *   int(0)
     *   ["Science.txt"]=>
     *   int(0)
     *   ["History.txt"]=>
     *   int(0)
     *   ["Health.txt"]=>
     *   int(0)
     *   ["Environment.txt"]=>
     *   int(0)
     * }
    */
}

main($argc, $argv);
