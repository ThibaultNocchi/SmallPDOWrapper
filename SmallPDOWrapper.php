<?php

/* Updated on 22-02-2019 18:54 */

class DB{

	private $_host;
	private $_db;
	private $_user;
	private $_pass;
	private $_handle;

	public function __construct($host, $db, $user, $pass){ // Enregistre les paramètres en variables locales et ouvre la connexion
		$this->setHost($host);
		$this->setDb($db);
		$this->setUser($user);
		$this->setPass($pass);

		$this->_handle = $this->openDb($this->_host, $this->_db, $this->_user, $this->_pass);

	}

	public function __destruct(){ // Ferme la connexion en détruisant le handle
		$this->_handle = null;
	}

	private function openDb($host, $db, $user, $pass){ // Ouvre la connexion et renvoie le handle pour l'utiliser plus tard
		$handle = false;
		try {
			$pdo_options[PDO::ATTR_EMULATE_PREPARES] = false;
    		$pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
    		$pdo_options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
    		$handle = new PDO('mysql:host='.$host.';dbname='.$db.';charset=utf8mb4', $user, $pass, $pdo_options);
		}
		catch(Exception $e) {
    		die('Erreur : '.$e->getMessage());
		}
		return $handle;
	}

	public function isTableInDatabase($name){
		$query = "SELECT * FROM information_schema.tables WHERE table_name = :name";
		$results = $this->query($query, ["name"=>$name]);
		if(count($results) > 0) return true;
		else return false;
	}

	public function createTable($name, $cols, $constraints = null, $encoding = null){

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

		if($encoding != null && $encoding != "")
			$query .= " CHARACTER SET ".$encoding;

		$this->queryNoFetch($query);
		return true;
	}

	public function query($query, $parameters = []){ // Effectue la requête $query avec les paramètres $parameters. Les clés doivent porter le nom des paramètres // Erreur : 1 = Mauvaise query
		$req = $this->_handle->prepare($query);
		if(!$req) return 1;
		$err = $req->execute($parameters);
		$ret = [];
		while($data = $req->fetch()){
			array_push($ret, $data);
		}
		$req->closeCursor();
		return $ret; // Retourne un tableau avec tous les résultats
	}

	public function queryNoFetch($query, $parameters = []){ // Effectue la requête $query avec les paramètres $parameters. Les clés doivent porter le nom des paramètres. Cette fonction ne fetch pas, donc pas d'erreur avec update ou insert...s // Erreur : 1 = Mauvaise query
		$req = $this->_handle->prepare($query);
		if(!$req) return 1;
		$err = $req->execute($parameters);
		return 0;
	}

	public function retrieveCol($table, $col){ // Récupère les valeurs d'une colonne $col d'une table $table en tant que clés d'un tableau qui est retourné
		$query = "SELECT ".$col." AS col FROM ".$table;
		$req = $this->_handle->prepare($query);
		$req->execute(array());
		$ret = [];
		while($data = $req->fetch()){
			$ret[$data['col']] = 1;
		}
		return $ret;
	}

	public function insertDatas($table, $datas){ // Requête préparée INSERT
		$req = 'INSERT INTO '.$table.'('; // $table est le nom de la table
		$d = false;
		foreach ($datas as $key => $value) { // $datas sont les paramètres en tableau avec en clé le nom de la colonne et en valeur bah la valeur
			if($d){
				$req .= ', ';
			}
			$req .= $key;
			$d = true;
		}
		$req .= ') VALUES(';
		$d = false;
		foreach ($datas as $key => $value) {
			if($d){
				$req .= ', ';
			}
			$req .= ':'.$key;
			$d = true;
		}
		$req .= ')';
		
		$req = $this->_handle->prepare($req);
		$req->execute($datas);

	}

	public function insertMultipleValues($table, $datas){ // Insertion de plusieurs données dans une table. Le tableau doit être 2D. $datas[0] = ["col1", "col2", "col3"] et $datas[ligne] = [valeur1, valeur2, valeur3]

		$req = "INSERT IGNORE INTO ".$table."("; // Utilisation du mot clé IGNORE pour éviter les erreurs si une clé unique existe déjà
		$d = false; // Permet d'ajouter le délimiteur si besoin

		foreach ($datas[0] as $key => $value) { // Parcourt les noms de colonnes pour les ajouter à la requête
			if($d) $req .= ', '; // Si ce n'est pas la première valeur, on ajoute une virgule
			$req .= $value; // On ajoute le nom de la colonne
			$d = true; // On met à true pour qu'un délimiteur soit mis avant la prochaine valeur
		}

		$req .= ") VALUES"; // On ferme la liste de colonnes et on prépare l'arrivée des valeurs

		array_shift($datas); // On retire le premier tableau avec les noms de colonnes

		$datasToSend = []; // On prépare le tableau de données

		$d = false; // On prépare le booléen de délimiteur entre les différentes données de ligne

		foreach($datas as $key => $value) { // On parcourt chaque ligne
			$d2 = false; // On prépare le délimiteur entre chaque élément de la ligne
			if($d) $req .= ", "; // On ajoute le délimiteur si ce n'est pas la première ligne
			$req .= "("; // On ouvre l'insertion des valeurs de la ligne

			foreach ($value as $key2 => $value2) { // Pour chaque valeur de la ligne
				if($d2) $req .= ", "; // On ajoute le délimiteur si c'est pas la première valeur
				$req .= "?"; // On met un point d'interrogation pour la requête préparée
				array_push($datasToSend, $value2); // On ajoute la valeur à l'array 1D à envoyer
				$d2 = true; // On met à true pour qu'un délimiteur soit mis avant la prochaine valeur
			}

			$req .= ")"; // Fin de la ligne
			$d = true; // On met à true pour qu'un délimiteur soit mis avant la prochaine valeur
		}

		$req = $this->_handle->prepare($req);
		$req->execute($datasToSend); // Envoi de la requête

	}

	public function updateDatasOnKey($table, $datas, $whereKey){ // Requête préparée UPDATE avec un WHERE sur une clé précise
		$req = 'UPDATE '.$table.' SET '; // $table est le nom de la table
		$d = false;
		foreach ($datas as $key => $value) { // $datas est un tableau contenant tous les paramètres avec en clé le nom de la colonne et en valeur la valeur
			if($key == $whereKey){ continue; } // $whereKey est une chaîne avec le nom de la clé qui fait le where (WHERE $whereKey = :$whereKey)
			if($d){ $req .= ', '; }
			$req .= $key." = :".$key;
			$d = true;
		}
		$req .= " WHERE ".$whereKey." = :".$whereKey;

		$req = $this->_handle->prepare($req);
		$req->execute($datas);
	}

	public function truncateTable($table){ // Reset la table $table
		$req = $this->_handle->query("TRUNCATE TABLE ".$table);
	}

	public function doesKeyExist($table, $datas){ // Vérifie si une ligne existe satisfaisant les données existe

		$req = 'SELECT * FROM '.$table.' WHERE ';
		$d = false;
		foreach ($datas as $key => $value) {
			if($d){ $req .= ' AND '; }
			$req .= $key." = :".$key;
			$d = true;
		}
		
		$req = $this->_handle->prepare($req);
		$req->execute($datas);

		$res = $req->fetch();
		$res = boolval($res);
		$req->closeCursor();

		return $res;

	}

	public function deleteKey($table, $keys){
		$query = "DELETE FROM ".$table." WHERE ";
		$toInsert = [];
		foreach ($keys as $key => $value) {
			array_push($toInsert, $key." = :".$key);
		}
		$toInsert = join(" AND ", $toInsert);
		$query .= $toInsert;
		$this->queryNoFetch($query, $keys);
	}

	public function countRows($table, $datas = [], $whereKey = ""){ // Compte le nombre de lignes qui correpondent à la clé dans whereKey

		$req = 'SELECT COUNT(*) AS nbr FROM '.$table;

		if($whereKey != "" AND isset($datas[$whereKey])){
			$req .= ' WHERE '.$whereKey.' = :'.$whereKey;
		}

		$datas = $this->query($req, $datas)[0]["nbr"];
		return $datas;	

	}

	private function setHost($host){ $this->_host = $host; }
	private function setDb($db){ $this->_db = $db; }
	private function setUser($user){ $this->_user = $user; }
	private function setPass($pass){ $this->_pass = $pass; }

}

?>