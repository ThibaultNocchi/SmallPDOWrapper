<?php

/* Updated on 26-02-2019 22:51 */

class SmallPDOWrapper{

    private $_handle;

    /**
     * __construct
     * 
     * Connects to the DB and stores the handle.
     *
     * @see openDb
     *
     * @return void
     */
    public function __construct(string $host, string $db, string $user, string $pass){
        $this->_handle = $this->openDb($host, $db, $user, $pass);
    }

    /**
     * __destruct
     *
     * Destroys the associated handle and PDO automatically closes the connection when the handle is destroyed.
     * 
     * @return void
     */
    public function __destruct(){
        $this->_handle = null;
    }

    /**
     * openDb
     * 
     * Opens the connection to the DB and returns the handle.
     *
     * @param  string $host Database's host.
     * @param  string $db Database's name.
     * @param  string $user Database's user.
     * @param  string $pass Database's password.
     *
     * @return PDO Handle to the PDO connection.
     */
    private function openDb(string $host, string $db, string $user, string $pass) : PDO{
        $handle = false;
        $pdo_options[PDO::ATTR_EMULATE_PREPARES] = false;
        $pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $pdo_options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $handle = new PDO('mysql:host='.$host.';dbname='.$db.';charset=utf8mb4', $user, $pass, $pdo_options);
        return $handle;
    }

    /**
     * isTableInDatabase
     *
     * Returns whether the table is already in the database.
     * 
     * @param  string $name Name of the table.
     *
     * @return bool
     */
    public function isTableInDatabase(string $name) : bool{
        $query = "SELECT * FROM information_schema.tables WHERE table_name = :name";
        $results = $this->query($query, ["name"=>$name]);
        if(count($results) > 0) return true;
        else return false;
    }


    /**
     * createTable
     * 
     * Creates a table with these parameters.
     * 
     * @param  string $name Table's name.
     * @param  array $cols Columns names as keys and their types and other parameters as values.
     * @param  array $constraints List of different constraints to apply to the table (foreign keys for example).
     * @param  string $encoding String encoding type (default: utf8).
     *
     * @return bool False if the creation was cancelled, true if it succeeded. PDO can also throw an exception if it fails.
     */
    public function createTable(string $name, array $cols, array $constraints = null, string $encoding = null) : bool{

        if($this->isTableInDatabase($name)) return false;

        $lines = [];
        foreach ($cols as $key => $value) {
            array_push($lines, $key . " " . $value);
        }
        $lines = join(',',$lines);
        $query = "CREATE TABLE " . $name . " (" . $lines;

        if($constraints != null && $constraints != []){
            $lines = join(',',$constraints);
            $query .= ",".$lines;
        }

        $query .= ")";

        if($encoding == null || $encoding == "") $encoding = "utf8";
        $query .= " CHARACTER SET ".$encoding;

        $this->queryNoFetch($query);
        return true;
    }

    /**
     * query
     * 
     * Sends the query to the database with the specified named parameters.
     * Ex.:
     * SELECT * FROM MyTable WHERE ID = :param_id
     * $parameters = ["param_id" => 2]
     * 
     * Results are like this:
     * $results = [0 => ["ID" => 2, "Name" => "Marc-Emmanuel"], 1 => ["ID" => 2, "Name" => "Jean-Lou"], ...]
     * 
     * @param  string $query Query to execute.
     * @param  array $parameters Array of named parameters. Keys are the parameters names and the values, their values.
     *
     * @return array List of arrays representing lines resulting from the query.
     */
    public function query(string $query, array $parameters = []) : array{
        $req = $this->_handle->prepare($query);
        $err = $req->execute($parameters);
        $ret = [];
        while($data = $req->fetch()){
            array_push($ret, $data);
        }
        $req->closeCursor();
        return $ret;
    }

    /**
     * queryNoFetch
     * 
     * Sends the query to the database with the specified named parameters, without fetching results from it.
     * Used to execute INSERT, UPDATES, etc. statements.
     *
     * @see query
     *
     * @return void
     */
    public function queryNoFetch(string $query, array $parameters = []){
        $req = $this->_handle->prepare($query);
        $err = $req->execute($parameters);
    }

    /**
     * retrieveCol
     * 
     * Retrieves all values from a column.
     *
     * @param  string $table Table to use.
     * @param  string $col Column values to retrieve.
     *
     * @return array List of values in the specified column.
     */
    public function retrieveCol(string $table, string $col) : array{
        $query = "SELECT ".$col." AS col FROM ".$table;
        $req = $this->_handle->prepare($query);
        $req->execute(array());
        $ret = [];
        while($data = $req->fetch()){
            array_push($ret, $data["col"]);
        }
        return $ret;
    }

    /**
     * insertDatas
     * 
     * Insert a line of datas into the specified table.
     *
     * @param  mixed $table Table used to insert the values into.
     * @param  mixed $datas Array of named parameters. Keys are the columns names and the values, their values.
     * 
     * Ex.:
     * $parameters = ["ID" => 1, "Name" => "Christian"]
     *
     * @return void
     */
    public function insertDatas(string $table, array $datas){

        $req = 'INSERT INTO '.$table.'(';

        $columns = [];
        foreach ($datas as $key => $value) {
            array_push($columns, $key);
        }
        $req .= join(",", $columns);

        $req .= ') VALUES(';

        $values = [];
        foreach ($datas as $key => $value) {
            array_push($values, ':' . $key);
        }
        $req .= join(",", $values);

        $req .= ')';
        
        $req = $this->_handle->prepare($req);
        $req->execute($datas);

    }

    /**
     * insertMultipleValues
     * 
     * Insert multiple lines of data at once.
     * Datas is a 2D array, with the first dimension being different lines, and each line being an array of values to insert.
     * Datas[0] also doesn't have values, it has the names of the different columns. And values afterward needs to be in the same position as the columns names in Datas[0].
     *
     * Ex.:
     * $datas =
     * [
     *  0 => ["ID", "Name"],
     *  1 => [0, "Daniel"],
     *  2 => [1, "Sebastian"],
     *  3 => [2, "Max"],
     *  4 => [232, "Lewis"]
     * ]
     * 
     * @param  string $table Table's name.
     * @param  array $datas Datas to insert, as explicited above.
     *
     * @return void
     */
    public function insertMultipleValues(string $table, array $datas){

        $req = "INSERT IGNORE INTO ".$table."("; // Ignoring already used keys.

        $columns = [];
        foreach ($datas[0] as $key => $value) { // Specifies the columns to be inserted.
            array_push($columns, $value);
        }
        $req .= join(",", $columns);

        $req .= ") VALUES";

        array_shift($datas); // We remove the list of columns names.

        $datasToSend = []; // On prépare le tableau de données.

        $lines = [];
        foreach($datas as $key => $value) { // For each line to add.

            $values = [];
            foreach ($value as $key2 => $value2) { // For each value of a line.
                array_push($datasToSend, $value2); // We add the value to the list of parameters.
                array_push($values, "?");
            }
            array_push($lines, "(" . join(",",$values) . ")");
        }
        $req .= join(",", $lines);
        
        $req = $this->_handle->prepare($req);
        $req->execute($datasToSend);

    }

    /**
     * updateDatasOnKey
     * 
     * Updates lines specified by the where key with the given datas.
     * The value of the where key is to be put into the datas.
     * 
     * If you want to update where ID = 2,
     * $whereKey = "ID"
     * $datas = ["ID" => 2, "Name" => "Kimi"]
     *
     * @param  string $table Table's name.
     * @param  array $datas Datas to put into updated lines.
     * @param  string $whereKey Key to select lines.
     *
     * @return void
     */
    public function updateDatasOnKey(string $table, array $datas, string $whereKey){
        $req = 'UPDATE '.$table.' SET ';
        
        $columns = [];
        foreach ($datas as $key => $value) {
            if($key == $whereKey){ continue; }
            array_push($columns, $key . " = :" . $key);
        }
        $req .= join(",", $columns);
        $req .= " WHERE ".$whereKey." = :".$whereKey;
        
        $req = $this->_handle->prepare($req);
        $req->execute($datas);
    }

    /**
     * truncateTable
     * 
     * Truncates (empty) the given table.
     *
     * @param  string $table Table to truncate.
     *
     * @return void
     */
    public function truncateTable(string $table){
        $this->_handle->query("TRUNCATE TABLE ".$table);
    }

    /**
     * doesKeyExist
     * 
     * Checks if there is at least one line corresponding to the given parameters.
     *
     * @param  string $table Table's name.
     * @param  array $datas Datas to check against.
     *
     * @return bool Whether the parameters are in the table or not.
     */
    public function doesKeyExist(string $table, array $datas) : bool{

        $req = 'SELECT COUNT(*) AS total FROM '.$table.' WHERE ';
        
        $columns = [];
        foreach ($datas as $key => $value) {
            array_push($columns, $key . " = :" . $key);
        }
        $req .= join(" AND ", $columns);
        
        $req = $this->_handle->prepare($req);
        $req->execute($datas);

        $res = $req->fetch();
        $res = $res["total"] > 0;
        $req->closeCursor();

        return $res;

    }

    /**
     * deleteKey
     * 
     * Deletes lines corresponding to the given parameters.
     *
     * @param  string $table Table's name.
     * @param  array $keys Datas to check against.
     *
     * @return void
     */
    public function deleteKey(string $table, array $keys){
        $query = "DELETE FROM ".$table." WHERE ";
        $columns = [];
        foreach ($keys as $key => $value) {
            array_push($columns, $key . " = :" . $key);
        }
        $query .= join(" AND ", $columns);
        $this->queryNoFetch($query, $keys);
    }

}

?>