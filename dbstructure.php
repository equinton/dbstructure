<?php

/**
 * Software used to retrieve the structure of a Postgresql database
 * Copyright © 2019, Eric Quinton
 * Distributed under license MIT (https://mit-license.org/)
 *
 * Usage:
 * rename param.ini.dist in param.ini
 * change params in param.ini to specify the parameters to connect the database,
 * and specify the list of schemas to analyze, separated by a comma
 */

error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once "lib/ObjetBDD.php";
require_once 'lib/structure.class.php';
require_once 'lib/Message.php';

$message = new Message();

if ($argv[1] == "-h" || $argv[1] == "--help") {
    $message->set("DbStructure : display the structure of a Postgresql database");
    $message->set("Licence : MIT. Copyright © 2019-2022 - Eric Quinton");
    $message->set("Options :");
    $message->set("-h ou --help: this help file");
    $message->set("--param=param.ini: name of the param.ini file");
    $message->set("--export=filename: name of export file (default: dbstructure-YYYYMMDDHHmm.html");
    $message->set("--format=tex|html|csv: export format (html by default)");
    $message->set("--summary=y: display a list of all tables at the top of the html file");
    $message->set("--schemas=public: list of schemas to export, separated by a comma");
    $message->set("--section=database: name of the section of the ini file which describe the connection to the database");
    $message->set("--csvtype=columns|tables: extract the list of columns or the list of tables (in conjunction with csv export)");
    $message->set("--cssfile=lib/dbstructure.css: file used to format the html export");
    $message->set("Change params in param.ini to specify the parameters to connect the database, and specify the list of schemas to analyze, separated by a comma");
} else {

    /**
     * Processing args
     */
    $arguments = array();
    for ($i = 1; $i < count($argv); $i++) {
        $arg = explode("=", $argv[$i]);
        if (strlen($arg[1]) > 0) {
            $a = substr($arg[0], 2);
            $arguments[$a] = $arg[1];
        }
    }
    $error = false;
    $paramfile = "param.ini";
    if (isset($arguments["param"])) {
        if (file_exists($arguments["param"])) {
            $paramfile = $arguments["param"];
        } else {
            $message->set(sprintf("The file %s don't exists or is not readable", $arguments["param"]));
            $error = true;
        }
    }
    if (!$error) {
        $isConnected = false;
        $section = "database";
        $cssfile = "lib/dbstructure.css";
        $format = "html";
        $export = "";
        $summary = "y";
        $csvtype = "columns";
        $dbparam = parse_ini_file($paramfile, true);
        array_key_exists ("schema", $dbparam[$section]) ?  $schemas = $dbparam[$section]["schema"] : $schemas = "public" ;
        /**
         * integrate command line arguments
         */
        foreach ($arguments as $k => $v) {
            $$k = $v;
        }

        /**
         * Database connection
         */
        try {
            $bdd = new PDO($dbparam[$section]["dsn"], $dbparam[$section]["login"], $dbparam[$section]["passwd"]);
            $isConnected = true;
        } catch (PDOException $e) {
            $message->set($e->getMessage());
        }

        if ($isConnected) {
            $structure = new Structure($bdd);

            if (strlen($export) == 0) {
                $export = "dbstructure-" . date('YmdHi') . "." . $format;
            }
            /**
             * Generation of code
             */
            try {
                $handle = fopen($export, "w");
                if ($format != "html") {
                    $structure->codageHtml = false;
                }
                $dbname = $structure->getDatabaseName();
                $dbnamecomment = $structure->getDatabaseComment();
                $structure->extractData($schemas);
                /**
                 * HTML formatting
                 */
                if ($format == "html") {
                    /**
                     * CSS file reading
                     */
                    $css = fopen($cssfile, "r");
                    $cssdata = fread($css, filesize($cssfile));
                    $header = '<html>
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <title>' . $dbname . '</title>
            <style type="text/css">
                ' . $cssdata . '
            </style>
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
            <script type="text/javascript" charset="utf-8" src="https://code.jquery.com/jquery-3.3.1.js"></script>
            <script type="text/javascript" charset="utf-8" src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
            </head>
            <body>
            <script>
            $(document).ready( function () {
                $(".datatable").DataTable( {
                    "paging": false,
                    "searching": false,
                    "ordering": false,
                    "info": false
                });
            } );
            </script>
            <h1>Structure of ' . $dbname . ' database</h1>';
                    if (strlen($dbnamecomment) > 0) {
                        $header .= "<p class='tablecomment'>$dbnamecomment</p>";
                    }
                    if ($dbparam["html"]["showdate"] == 1) {
                        $header .= '<p>Date generation: ' . date($dbparam["html"]["dateformat"]) . '</p>';
                    }
                    if (strtolower($summary) == "y") {
                        $header .= $structure->generateSummaryHtml();
                    }
                    $data = $structure->generateHtml("tablename", "tablecomment", "datatable row-border display");
                    $bottom = '</body></html>';
                    $content = $header . $data . $bottom;
                } else if ($format == "tex") {
                    /**
                     * Latex formatting
                     */
                    $content = $structure->generateLatex($dbparam["latex"]["level"], $dbparam["latex"]["tableheader"], $dbparam["latex"]["tablefooter"]);
                } else {
                    /**
                     * Export CSV
                     */
                    if ($csvtype == "columns") {
                        $data = $structure->getAllColumns();
                    } else {
                        $data = $structure->getAllTables();
                    }
                    /**
                     * Generate the header
                     */
                    $csvheader = array();
                    foreach ($data[0] as $key => $value) {
                        $csvheader[] = $key;
                    }
                    fputcsv($handle, $csvheader);
                    /**
                     * Populate the file
                     */
                    foreach ($data as $value) {
                        fputcsv($handle, $value);
                    }
                }
                if ($format != "csv") {
                    fwrite($handle, $content);
                }
                fclose($handle);
                $message->set("File " . $export . " generate");
            } catch (Exception $e) {
                $message->set($e->getMessage());
                $message->set("The file can't be generated.");
            }
        }
    }
}
/**
 * Display messages
 */
foreach ($message->get() as $line) {
    echo ($line . PHP_EOL);
}
echo (PHP_EOL);
