<?php
/**
 * Programme permettant de récupérer la structure d'une base de données Postgresql
 * Copyright © 2019, Eric Quinton
 * Distribué sous licence BSD (https://directory.fsf.org/wiki/License:BSD-3-Clause)
 */

error_reporting(E_ERROR|E_WARNING|E_PARSE);
 require_once "ObjetBDD.php";
require_once 'structure.class.php';
require_once 'Message.php';


$paramfile = "param.ini";
$sectionName = "database";

$message = new Message();

if ($argv[1] == "-h" || $argv[1] == "--help") {
    $message->set("DbStructure : affichage de la structure d'une base de données Postgresql");
    $message->set("Licence : BSD. Copyright © 2019 - Éric Quinton");
    $message->set("Options :");
    $message->set("-h ou --help : ce message d'aide");
    $message->set("--export=nom_fichier : nom du fichier d'export");
    $message->set("--format=latex|html : format d'exportation (html par défaut");
    $message->set("Les paramètres de connexion à la base de données ainsi que les schémas à extraire sont décrits dans le fichier param.ini");
} else {


    $dbparam = parse_ini_file($paramfile, true);

    $isConnected = false;
    $formatExport = "html";
    $fileExport = "dbstructure-" . date('YmdHi') . ".html";
    $schemas = $dbparam[$sectionName]["schema"];
    /** 
     * connexion à la base de données
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
         * Traitement des arguments
         */
        for ($i = 1; $i <= count($argv); $i++) {
            $arg = explode("=", $argv[$i]);
            switch ($arg[0]) {
                case "--export":
                    $fileExport = $arg[1];
                    break;
                case "--format":
                    $formatExport = $arg[1];
                    break;
            }
        }
        /**
         * Generation du code
         */
        try {
            $dbname = $structure->getDatabaseName();
            $structure->extractData($schemas);
            /**
             * Mise en forme HTML
             */
            if ($formatExport == "html") {
                 $header = '<html>
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <title>'.$dbname.'</title>
            <link rel="stylesheet" type="text/css" href="dbstructure.css" >
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
            <script type="text/javascript" charset="utf-8" src="https://code.jquery.com/jquery-3.3.1.js"></script>
            <script type="text/javascript" charset="utf-8" src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
            </head>
            <body>
            <script>
            $(document).ready( function () {
                $(".datatable").DataTable(
                    "paging": false;
                    "searching": false;
                );
            } );
            </script>
            <h1>Structure de la base de données '.$dbname.'</h1>
            ';
                $data = $structure->generateHtml("tablename", "tablecomment", "datatable row-border display");
                $bottom = '</body></html>';
                $content = $header . $data . $bottom;
            } else {
                /**
                 * Mise en forme Latex
                 */
                $content = $structure->generateLatex(
                    "subsection",
                    "\\begin{tabular}{|l| p{2cm}|c|c|c| p{3cm}|}",
                    "\\end{tabular}"
                );

            }
            $handle = fopen($fileExport, "w");
            fwrite($handle, $content);
            fclose($handle);
            $message->set("Fichier ".$fileExport." généré");
        } catch (Exception $e) {
            $message->set($e->getMessage());
            $message->set("Le fichier n'a pas pu être généré. Vérifiez vos paramètres dans la ligne de commande");
        }

    }
}
/**
 * Affichage des messages
 */
foreach ($message->get() as $line) {
    echo ($line . PHP_EOL);
}
echo (PHP_EOL);


?>