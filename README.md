# sql-pdo-library

Uma biblioteca para simplificar o uso de PDO no mysql.

<h3>Funções basicas</h3>
<ul>
<li><a href="#select">select</a></li>
<li><a href="#insert">insert</a></li>
<li><a href="#delete">delete</a></li>
<li><a href="#call">call</a></li>
</ul>

<h3 id="select"> $object->select ( $fields, $table, $parameters, $options )</h3>

$fields = array contendo o campos, comandos e afins relativos as colunas.
Ex :
$fields = [ 'DISTINCT column', 'COUNT(column)', 'column', 'column' ];

$table = nome da tabela onde deverá ser realizado o select
Ex :
$table = 'schema.tableName';

$parameters = array contendo os parametros a serem utilizados no campo where 
Ex :
$parameters = [
  'column' => 'value',
  'column' => 'value',
  'column' => 'value',
  'column' => [
    'value',
    'value'
  ]
];

$options = matriz contendo diversas configurações 

$options['additionalCommand'] = definição de comandos posteriores ao where, como limit, order by e afins
$options['index'] = define uma coluna que terá o valor utilizado para ser usada como chave no array de retorno
$options['fetch'] = define que o select terá apenas uma unica linha como retorno
$options['operator'] = define o operador a ser usado no where, em todas as comparações ( padrão = AND )
$options['conditionalList'] = define a lista de operadores a serem utilizados nas comparações, em casos onde se deseje usar diferentes operadores
Ex : $options['conditionalList'] = ['AND', 'OR', 'AND',['OR','OR']]
