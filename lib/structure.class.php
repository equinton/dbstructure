<?php


/**
 * Class for extract structure of a Postgresql database
 */
class Structure extends ObjetBDD
{

    private $_schema = "public";

    private $_tables;

    private $_colonnes;

    public $tables;

    /**
     * __construct
     *  
     * @param PDO   $p_connection 
     * @param mixed $param 
     * 
     * @return void 
     */
    function __construct(\PDO $p_connection, array $param = array())
    {
        parent::__construct($p_connection, $param);
    }

    function extractData($schema = "public")
    {
        $schemas = explode(",", $schema);
        $comma = "";
        $this->_schema = "";
        foreach ($schemas as $sc) {
            $this->_schema .= $comma . "'" . $sc . "'";
            $comma = ",";
        }

        /** Extract table structure from database */
        $this->getTables();
        $this->getColumns();

         /*
         * Mise en forme du tableau utilisable
         */
        foreach ($this->_tables as $table) {
            /* 
             * Recherche des colonnes attachees
             */
            foreach ($this->_colonnes as $colonne) {
                if ($colonne["tablename"] == $table["tablename"] && $colonne["schemaname"] == $table["schemaname"]) {
                    $table["columns"][] = $colonne;
                }
            }
            $this->tables[] = $table;
        }
    }

    /**
     * Get tables list
     * 
     * @return void 
     */
    function getTables()
    {
        $sql = "select schemaname, relname as tablename, description
        from pg_catalog.pg_statio_all_tables st
        join pg_catalog.pg_description on (relid = objoid and objsubid = 0)
        where schemaname in (" . $this->_schema . ")
        order by schemaname, relname";
        $this->_tables = $this->getListeParam($sql);
    }

    /**
     * Get columns list
     * 
     * @return void 
     */
    function getColumns()
    {
        $sql = 'with req as 
        (SELECT DISTINCT on (schemaname, tablename, field) schemaname, pg_tables.tablename, 
           attnum,  pg_attribute.attname AS field,
            format_type(pg_attribute.atttypid,NULL) AS "type",
         (SELECT col_description(pg_attribute.attrelid,pg_attribute.attnum)) AS COMMENT,
         CASE pg_attribute.attnotnull
            WHEN FALSE THEN 0
           ELSE 1
         END AS "notnull",
         pg_constraint.conname AS "key",
         pc2.conname AS ckey,
         (SELECT pg_attrdef.adsrc
          FROM pg_attrdef
          WHERE pg_attrdef.adrelid = pg_class.oid
          AND   pg_attrdef.adnum = pg_attribute.attnum) AS def
        FROM pg_tables,
       pg_class
       JOIN pg_attribute
       ON pg_class.oid = pg_attribute.attrelid
       AND pg_attribute.attnum > 0
       LEFT JOIN pg_constraint
           ON pg_constraint.contype = \'p\'::"char"
           AND pg_constraint.conrelid = pg_class.oid
           AND (pg_attribute.attnum = ANY (pg_constraint.conkey))
        LEFT JOIN pg_constraint AS pc2
           ON pc2.contype = \'f\'::"char"
           AND pc2.conrelid = pg_class.oid
           AND (pg_attribute.attnum = ANY (pc2.conkey))
        WHERE pg_class.relname = pg_tables.tablename
        AND   pg_attribute.atttypid <> 0::OID
        and schemaname in ( ' . $this->_schema . ')
       ORDER BY schemaname, tablename, field ASC)
        select * from req order by tablename, attnum;
       ';
        $this->_colonnes = $this->getListeParam($sql);
    }
    
    /**
     * Format structure in html
     *
     * @param string $classTableName: css class for table name
     * @param string $classTableComment: css class for the comment of the table
     * @param string $classTableColumns: css class for the table of columns
     * @return string
     */
    function generateHtml(
        $classTableName = "tablename",
        $classTableComment = "tablecomment",
        $classTableColumns = "datatable"
    ) {
        $val = "";
        $currentSchema = "";
        foreach ($this->tables as $table) {
            if ($table["schemaname"] != $currentSchema) {
                $currentSchema = $table["schemaname"];
                $val .= '<h2>Schema ' . $currentSchema . '</h2>';
            }
            $val .= '<div id="'.$table["schemaname"].$table["tablename"] .'" class="' . $classTableName . '">' . $table["tablename"] . "</div>"
                . '<br><div class="' . $classTableComment . '">'
                . $table["description"] . '</div>.<br>';
            $val .= '<table class="' . $classTableColumns . '">';
            $val .= '<thead><tr>
                    <th>Column name</th>
                    <th>Type</th>
                    <th>Not null</th>
                    <th>key</th>
                    <th>Comment</th>
                    </tr></thead>';
            $val .= '<tbody>';
            foreach ($table["columns"] as $column) {
                $val .= '<tr>
                <td>' . $column["field"] . '</td>
                <td>' . $column["type"] . '</td>
                <td>' . $column["notnull"] . '</td>
                <td>' . $column["key"] . '</td>
                <td>' . $column["comment"] . '</td>
                </tr>';
            }
            $val .= '</tbody></table>';
            /**
             * Add references
             */
            $refs = $this->getReferences($table["schemaname"], $table["tablename"]);
            if (count($refs) > 0) {
               $val .= "<h3>References</h3>";
               foreach ($refs as $ref) {
                   $val .= $ref["column_name"]. ": ".$ref["foreign_schema_name"]
                   .'.<a href="#'.$ref["foreign_schema_name"].$ref["foreign_table_name"].'">'
                   .$ref["foreign_table_name"]
                   ."</a>"
                   ." (".$ref["foreign_column_name"].")<br>";
               }
            }
            $refs = $this->getReferencedBy($table["schemaname"], $table["tablename"]);
            if (count($refs) > 0) {
               $val .= "<h3>Referenced by</h3>";
               foreach ($refs as $ref) {
                   $val .= $ref["foreign_column_name"]. ": ".$ref["schema_name"]
                   .'.<a href="#'.$ref["schema_name"].$ref["table_name"].'">'
                   .$ref["table_name"]
                   ."</a>"
                   ." (".$ref["column_name"].")<br>";
               }
            }
        }

        return $val;
    }

    /**
     * format structure in latex
     *
     * @param string $structureLevel: first level of the structure in the latex document
     * @param string $headerTable: definition of the table
     * @param string $closeTable: end tag for table
     * @return string
     */
    function generateLatex(
        $structureLevel = "subsection",
        $headerTable = "\\begin{tabular}{|l|l|c|l|l|}",
        $closeTable = "\\end{tabular}"
    ) {
        $val = "";
        $currentSchema = "";
        foreach ($this->tables as $table) {
            if ($table["schemaname"] != $currentSchema) {
                $currentSchema = $table["schemaname"];
                $val .= '\\' . $structureLevel . "{Schema " . $currentSchema . '}' . PHP_EOL;
            }
            $val .= "\\sub" . $structureLevel . "{"
                . $this->el($table["tablename"]) . "}"
                . PHP_EOL;
            $val .= $this->el($table["description"]) . PHP_EOL . PHP_EOL;
            $val .= $headerTable . PHP_EOL;
            $val .= "\\hline" . PHP_EOL;
            $val .= "Column name & Type & Not null & Key & Comment \\\\" . PHP_EOL
                . "\\hline" . PHP_EOL;
            foreach ($table["columns"] as $column) {
                $val .= $this->el($column["field"]) . " & "
                    . $this->el($column["type"]) . " & ";
                ($column["notnull"] == 1) ? $val .= "X & " : $val .= " & ";
                strlen($column["key"]) > 0 ? $val .= "X & " : $val .= " & ";
                strlen($column["ckey"]) > 0 ? $val .= "X & " : $val .= " & ";
                $val .= $this->el($column["comment"])
                    . "\\\\" . PHP_EOL
                    . "\\hline" . PHP_EOL;
            }
            $val .= $closeTable . PHP_EOL;

             /**
             * Add references
             */
            $refs = $this->getReferences($table["schemaname"], $table["tablename"]);
            if (count($refs) > 0) {
               $val .= "\\paragraphe{References}".PHP_EOL;
               foreach ($refs as $ref) {
                   $val .= $this->el($ref["column_name"]). ": ".$this->el($ref["foreign_schema_name"])."."
                   .$this->el($ref["foreign_table_name"])
                   ." (".$this->el($ref["foreign_column_name"]).")".PHP_EOL.PHP_EOL;
               }
            }
            $refs = $this->getReferencedBy($table["schemaname"], $table["tablename"]);
            if (count($refs) > 0) {
               $val .= "\\paragraphe{Referenced by}".PHP_EOL;
               foreach ($refs as $ref) {
                   $val .= $this->el($ref["foreign_column_name"]). ": ".$this->el($ref["schema_name"])."."
                   .$this->el($ref["table_name"])
                   ." (".$this->el($ref["column_name"]).")".PHP_EOL.PHP_EOL;
               }
            }
        }
        return $val;
    }

    /**
     * escape  _ by \_ for latex
     * @param string $chaine
     *  
     * @return string 
     */
    function el($chaine)
    {
        return str_replace("_", "\\_", $chaine);
    }

    /**
     * Get the name of the database
     *
     * @return string
     */
    function getDatabaseName()
    {
        $sql = "select current_database()";
        $data = $this->lireParam($sql);
        return ($data["current_database"]);
    }

    /**
     * Get references for a table
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    function getReferences($schema, $table)
    {
        return $this->_getReference($schema, $table, false);
    }

    /**
     * Return the tables referenced by the table
     *
     * @param string $schema
     * @param string $table
     * @return array
     */
    function getReferencedBy($schema, $table)
    {
        return $this->_getReference($schema, $table, true);
    }

    /**
     * Execute the request to get references from a table,
     * or tables referenced by
     *
     * @param [type] $schema
     * @param [type] $table
     * @param boolean $referencedBy
     * @return array
     */
    private function _getReference($schema, $table, $referencedBy = false)
    {
        if ($referencedBy) {
            $type = 'y';
            $schemaType = 'x';
        } else {
            $type = 'x';
            $schemaType = 'y';
        }
        $sql = "
        select c.constraint_name
          , x.table_schema as schema_name
           , x.table_name
            , x.column_name
           , y.table_schema as foreign_schema_name
            , y.table_name as foreign_table_name
            , y.column_name as foreign_column_name
        from information_schema.referential_constraints c
        join information_schema.key_column_usage x
            on x.constraint_name = c.constraint_name
        join information_schema.key_column_usage y
           on y.ordinal_position = x.position_in_unique_constraint
            and y.constraint_name = c.unique_constraint_name
        where " . $type . ".table_schema = :schema and " . $type . ".table_name = :table
            and " . $schemaType . ".table_schema in (" . $this->_schema . ")
        order by y.table_schema, y.table_name
        ";
        return ($this->getListeParamAsPrepared($sql, array("schema" => $schema, "table" => $table)));
    }
}


?>