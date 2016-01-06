<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// inicial

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.twig');
});


//conexão com banco de dados
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
  array(
    'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"],
    'pdo.port' => $dbopts["port"],
    'pdo.username' => $dbopts["user"],
    'pdo.password' => $dbopts["pass"]
  )
);

// teste de bd
$app->get('/db/', function() use($app) {
  $st = $app['pdo']->prepare('SELECT * FROM test_table');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('database.twig', array(
    'names' => $names
  ));
});

//  ----------------- app propriamente dito ----------------------

// inicial pesquisa
$app->get('/itriad/', function() use($app) {
  $st = $app['pdo']->prepare('SELECT * FROM funcionario');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('pesquisa.twig', array(
    'names' => $names
  ));
});

// editar registro
$app->get('/editar/{id}', function($id) use($app) {
  $st = $app['pdo']->prepare('SELECT * FROM funcionario where id='.$id);
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('editar.twig', array(
      'names' => $names
  ));
});

//adicionar novo
$app->get('/editar/', function() use($app) {
  return $app['twig']->render('criar.twig');
});


// salvar registro editado
$app->post('/salvar/{id}', function($id) use($app) {
  $st = $app['pdo']->prepare(
      'UPDATE funcionario
       SET  cpf = '.$_POST['cpf'].',
            nome = \''.$_POST['nome'].'\'
            endereco = \''.$_POST['endereco'].'\'
            data_nascimento = \''.$_POST['data_nascimento'].'\'
      where id='.$id
  );
  $st->execute();

  return $app->redirect('/editar/{id}');
});


$app->run();
