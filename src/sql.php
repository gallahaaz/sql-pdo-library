<?php

Namespace Gallahaaz\SqlPDOLibrary;
use PDO;

class Sql extends PDO {

    private $sql;
    private $sqlCommands = [
        'NOW()',
        'CURDATE()',
        'CURTIME()'
    ];

    public function __construct() {
        try {
            $this->sql = new PDO('mysql:host=' . DBHOSTNAME . ';dbname=' . DBSCHEMA . ';port=' . DBPORT, DBUSER, DBPASSWORD );
        } catch (Exception $e) {
            //echo 'Erro ao conectar' . $e;
            die($e->getMessage());
            //exit();
        }
    }

    private function setParam($statment, $key, $value) {
        $statment->bindParam($key, $value);
    }

    private function setParams($statment, $parameters = []) {
        foreach ( $parameters as $key => $value ) {
            $this->setParam($statment, $key, $value);
        }
    }

    public function query( $raw, $params = [] ) {
        $stmt = $this->sql->prepare( $raw );
        $this->setParams( $stmt, $params );
        $data = $stmt->execute();
        return $stmt;
    }

    public function fetchAssoc( $rawQuery, $params = [] ){
        $stmt = $this->query( $rawQuery, $params );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }
    
    public function fetchNum( $rawQuery, $params = [] ){
        $stmt = $this->query( $rawQuery, $params );
        return $stmt->fetchAll( PDO::FETCH_NUM );
    }
      
    public function fetchObj( $rawQuery, $params = [] ){
        $stmt = $this->query( $rawQuery, $params );
        return $stmt->fetchAll( PDO::FETCH_OBJ );
    }

    public function singleFetchAssoc( $rawQuery, $params = [] ){
        $rawQuery .= " LIMIT 1";
        $stmt = $this->query( $rawQuery, $params );
        $data = $stmt->fetchAll( PDO::FETCH_ASSOC );
        if( isset($data[0]) ){
            return $data[0];
        }else{
            return false;
        }
    }

    public function select( $fields, $table, $parameters, $options = [ 'operator' => 'AND' ]  ) {
        $cmd = "SELECT "
            . $this->concatArray( $fields )
            . " FROM "
            . $table;
        if( empty( $options['operator'] ) ){
            $options['operator'] = "AND";
        }
        $concatCommand = 'concat' . ucfirst( $options['operator'] ) . 'Params';
        if( isset( $parameters ) ){
            $cmd .= " WHERE " . $this->$concatCommand( $parameters, $options );
        }
        if( isset( $options['fetch'] ) ){
            return $this->singleFetchAssoc( $cmd, $parameters);
        }else{
            if( isset( $options['additionalCommand'] ) ){
                $cmd .= $options['additionalCommand'];
            }
            return $this->fetchAssoc( $cmd, $parameters );
        }
    }

    public function insert( $table, $columns, $values ) {
        $cmd = "INSERT INTO "
            . $table
            . " (" . $this->concatArray($columns) . ") "
            . " VALUES "
            . " (" . $this->concatParams($columns) . ");";
        $keys = [];
        foreach( $columns as $key ){
            array_push( $keys, ':' . $key );
        }
        $parameters = array_combine( $keys, $values );
        return $this->query( $cmd, $parameters );
    }
    
    public function update( $table, $set, $where ) {
        $cmd = "UPDATE "
            . $table
            . " SET "
            . $this->concatSetParams( $set )
            . " WHERE "
            . $this->concatAndParams( $where );
        $parameters = [];
        $combined = array_merge($set, $where);
        foreach( $combined as $key => $value ){
            $parameters[":" . $key] = $value;
        }
        $this->query( $cmd, $parameters );
    }

    //Deleta informações da tabela, com base nas informações informadas no Where
    public function delete( $table, $where ) {
        $cmd = "DELETE FROM " . $table
            . " WHERE " . $this->concatAndParams( $where );
        $parameters = [];
        foreach( $where as $key => $value ){
            $parameters[":" . $key] = $value;
        }
        $this->query( $cmd, $parameters );
    }

    //executa procedures cadastradas na base de dados
    public function call( $procedure, $arguments, $single = false ) {
        $cmd = "CALL " . $procedure
            . "(" . $this->concatArrayValues( $arguments ) . ")";
        if($single){
            $res = $this->fetchNum( $cmd );
            return $res[0][0];
        }else{
            return $this->fetchAssoc( $cmd );
        }
    }
    
    public function concatArray( $array ){
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= ',' ;
                $string .= $key;
            }else{
                $string .= $key;
            }
            $c++;
        }
        return $string;
    }
    
    public function concatParams( $array ){
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key => $value ){ 
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= ",";
            }
            $string .= ' :' . $value;
            $c++;
        }
        return $string;
    }
    
    public function concatAndParams( $array, $options = null ){
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key => $value ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= " AND ";
            }
            if( isset( $options['like'] ) ){
                $string .= $key . ' LIKE :' . $key;
            }else{
                $string .= $key . ' = :' . $key;
            }
            $c++;
        }
        return $string;
    }

    public function concatOrParams( $array, $options = null ){
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key => $value ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= " OR ";
            }
            if( isset( $options['like'] ) ){
                $string .= $key . ' LIKE :' . $key;
            }else{
                $string .= $key . ' = :' . $key;
            }
            $c++;
        }
        return $string;
    }
    
    public function concatListParams( $array, $options = null ){
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key => $value ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= $options['conditionalList'][$c];
            }
            if( isset( $options['like'] ) ){
                $string .= $key . ' LIKE :' . $key;
            }else{
                $string .= $key . ' = :' . $key;
            }
            $c++;
        }
        return $string;
    }
    
    public function concatSetParams( $array ){
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key => $value ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= ", ";
            }
            $string .= $key . ' = :' . $key;
            $c++;
        }
        return $string;
    }
    
    public function concatArrayValues( $array ){
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                if( is_integer($key) || is_float($key) ){
                    $string .= ", " . str_replace( ',', '.', $key);
                }else{
                    if( in_array( $key, $this->sqlCommands ) ){
                        $string .= ", " . $key . " ";
                    }else{
                        $string .= ", '" . urlencode($key) . "' ";
                    }
                }
            }else{
                if( is_integer($key) || is_float($key) ){
                    $string .= $key ;
                }else{
                    $string .= " '" . $key . "' ";
                }
            }
            $c++;
        }
        return $string;
    }
    
}