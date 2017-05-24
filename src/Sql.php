<?php 

Namespace Gallahaaz\SqlPDOLibrary

class Sql extends PDO 
{

	private $sql;

	public function __construct(){
		$this->sql = new PDO("mysql:host=" . DBHOST . ";dbname=" . DBSCHEMA, DBUSER, DBPASSWORD);
	}

	private function setParam( $statment, $key, $value ){
		$statment->bindParam( $key, $value );
	}

	private function setParams( $statment, $parameters = [] ){
		foreach( $parameters as $key => $value ){
			$this->setParam( $statment, $key, $value );
		}
	}

	public function query( $raw, $params = [] ){
		$stmt = $this->sql->prepare($raw);
		$this->setParams( $stmt, $params );
		$stmt->execute();
		return $stmt;
	}

	public function fetchAssoc( $rawQuery, $params = [] ):array{
		$stmt = $this->query( $rawQuery, $params );
		$stmt->fetchAll( PDO::FETCH_ASSOC );
	}

	public function select( $fields, $table, $searchFields = null, $options=null ){
	}

	public function insert( $table, $columns, $values ){

	}

	public function update( $table, $set, $where ){

	}

	public function delete( $table, $where ){

	}

	public function call( $procedure, $arguments, $single = false ){
		return $this->fetchAssoc();
	}

}