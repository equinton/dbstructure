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


$paramfile = "param.ini";
$sectionName = "database";
$cssfile = "lib/dbstructure.css";

$message = new Message();

if ($argv[1] == "-h" || $argv[1] == "--help") {
    $message->set("DbStructure : display the structure of a Postgresql database");
    $message->set("Licence : MIT. Copyright © 2019 - Éric Quinton");
    $message->set("Options :");
    $message->set("-h ou --help : ce message d'aide");
    $message->set("--export=filename : name of export file (default: dbstructure-YYYYMMDDHHmm.html");
    $message->set("--format=tex|html : export format (html by default)");
    $message->set("Change params in param.ini to specify the parameters to connect the database, and specify the list of schemas to analyze, separated by a comma");
} else {


    $dbparam = parse_ini_file($paramfile, true);

    $isConnected = false;
    $formatExport = "html";
    $fileExport = "";
    $schemas = $dbparam[$sectionName]["schema"];
    /** 
     * Database connection
     */
    try {
        $bdd = new PDO($dbparam[$sectionName]["dsn"], $dbparam[$sectionName]["login"], $dbparam[$sectionName]["passwd"]);
        $isConnected = true;
    } catch (PDOException $e) {
        $message->set($e->getMessage());
    }

    if ($isConnected) {
        $structure = new Structure($bdd);
        /**
         * Processing args
         */
        for ($i = 1; $i <= count($argv); $i++) {
            $arg = explode("=", $argv[$i]);
            if (strlen($arg[1]) > 0) {
                switch ($arg[0]) {
                    case "--export":
                        $fileExport = $arg[1];
                        break;
                    case "--format":
                        $formatExport = $arg[1];
                        break;
                }
            }
        }
        if (strlen($fileExport) == 0) {
            $fileExport = "dbstructure-" . date('YmdHi') . "." . $formatExport;
        }
        /**
         * Generation of code
         */
        try {
            $dbname = $structure->getDatabaseName();
            $structure->extractData($schemas);
            /**
             * HTML formatting
             */
            if ($formatExport == "html") {
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
            <h1>Database ' . $dbname . ' structure</h1>
            ';
                $data = $structure->generateHtml("tablename", "tablecomment", "datatable row-border display");
                $bottom = '</body></html>';
                $content = $header . $data . $bottom;
            } else {
                /**
                 * Latex formatting
                 */
                $content = $structure->generateLatex(
                    "subsection",
                    "\\begin{tabular}{|l| p{2cm}|c|c| p{5cm}|}",
                    "\\end{tabular}"
                );

            }
            $handle = fopen($fileExport, "w");
            fwrite($handle, $content);
            fclose($handle);
            $message->set("File " . $fileExport . " generate");
        } catch (Exception $e) {
            $message->set($e->getMessage());
            $message->set("The file can't be generated.");
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


?>