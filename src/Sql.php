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
        if( is_array($value) ){
            for( $x=0; $x<count($value); $x++ ){
                $statment->bindParam( $key.$x, $value[$x] );
            }
        }else{
            $statment->bindParam($key, $value);
        }
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
        $arr = $stmt->errorInfo();
        if( isset($arr[0]) && ($arr[0] > 0)){
            echo "\nPDOStatement::errorInfo():\n";
            print_r($arr);
        }
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

    public function indexFetch( $rawQuery, $params = [], $index ){
        $stmt = $this->query( $rawQuery, $params );
        $data = $stmt->fetchAll( PDO::FETCH_ASSOC );
        $return = [];
        foreach ( $data as $key => $value ){
            $return[ $value[$index] ] = $value;
            unset($return[$value[ $index ]][ $index ]);
        }
        return $return;
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

        if( isset( $options['additionalCommand'] ) ){
            $cmd .= $options['additionalCommand'];
        }
        if( isset($options['index']) ){
            return $this->IndexFetch( $cmd, $parameters, $options['index'] );
        }

        if( isset( $options['fetch'] ) ){
            return $this->singleFetchAssoc( $cmd, $parameters);
        }
        if( ( empty($options['fetch']) ) && ( empty($options['index'] ) ) ){
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

    public function delete( $table, $where ) {
        $cmd = "DELETE FROM " . $table
            . " WHERE " . $this->concatAndParams( $where );
        $parameters = [];
        foreach( $where as $key => $value ){
            $parameters[":" . $key] = $value;
        }
        $this->query( $cmd, $parameters );
    }

    public function call( $procedure, $arguments, $single = false ) {
        $cmd = "CALL " . $procedure
            . "(" . $this->concatArrayValues( $arguments ) . ")";
        if( $single ){
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

    private function boundKeyString( $key, $string, $operator ){
        if( strcasecmp( $operator, 'LIKE' ) === 0 ){
            return $key . ' LIKE :' . $key;
        }else{
            return $key . ' ' . $operator . ' :' . $key;
        }
    }
    
    public function concatAndParams( $array, $options = null ):string{
        $string = '';
        $last = count( $array );
        $c = 0;
        foreach( $array as $key => $value ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= " AND ";
            }
            if( is_array($value) ){
                for( $x=0; $x < count($value); $x++  ){
                    if( $x >= 1 ) {
                        $string .= " AND ";
                    }
                    $comparisonOperator = ( isset( $options['comparisonOperators'][$c][$x] ) ? $options['comparisonOperators'][$c][$x] : '=' );
                    $string .= $this->boundKeyString( $key, $string, $comparisonOperators );
                }
            }else{
                $comparisonOperator = ( isset( $options['comparisonOperators'][$c] ) ? $options['comparisonOperators'][$c] : '=' );
                $string .= $this->boundKeyString( $key, $string, $comparisonOperator );
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
            if( is_array($value) ){
                for( $x=0; $x < count($value); $x++  ){
                    if( $x >= 1 ) {
                        $string .= " OR ";
                    }
                    $string .= $this->boundKeyString( $key, $string, $options['comparisonOperators'][$c][$x] );
                }
            }else{
                $string .= $this->boundKeyString( $key, $string, $options['comparisonOperators'][$c] );
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
            if( is_array($value) ){
                for( $x=0; $x < count($value); $x++  ){
                    if( $x >= 1 ) {
                        $string .= $options['conditionalList'][$c][$x];
                    }
                    $string .= $this->boundKeyString( $key, $string, $options['comparisonOperators'][$c][$x] );
                }
            }else{
                $string .= $this->boundKeyString( $key, $string, $options['comparisonOperators'][$c] );
            }
            $c++;
        }
        return $string;
    }
    
    public function concatSetParams( $array ):string{
        $string = '';
        $c = 0;
        $last = count( $array );
        foreach( $array as $key => $value ){
            if( ( $c >= 1 ) &&( $c<$last ) ){
                $string .= " , ";
            }
            if( is_array($value) ){
                for( $x=0; $x < count($value); $x++  ){
                    if( $x >= 1 ) {
                        $string .= " , ";
                    }
                    $string .= $this->boundKeyString( $key, $string, $options['comparisonOperators'][$c][$x] );
                }
            }else{
                $string .= $this->boundKeyString( $key, $string, $options['comparisonOperators'][$c] );
            }
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