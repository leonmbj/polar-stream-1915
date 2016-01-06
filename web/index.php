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
    'twig.path' => __DIR__ . '/views',
));

// inicial

$app->get('/', function () use ($app) {
    $app['monolog']->addDebug('logging output.');
    return $app['twig']->render('index.twig');
});


//conexão com banco de dados
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
    array(
        'pdo.dsn' => 'pgsql:dbname=' . ltrim($dbopts["path"], '/') . ';host=' . $dbopts["host"],
        'pdo.port' => $dbopts["port"],
        'pdo.username' => $dbopts["user"],
        'pdo.password' => $dbopts["pass"]
    )
);

// teste de bd
$app->get('/db/', function () use ($app) {
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
$app->get('/itriad/', function () use ($app) {
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
$app->get('/editar/{id}', function ($id) use ($app) {
    $st = $app['pdo']->prepare('SELECT * FROM funcionario where id=' . $id);
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
$app->get('/editar/', function () use ($app) {
    return $app['twig']->render('criar.twig');
});


// salvar registro editado
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->post('/salvar/{id}', function (Request $request, $id) use ($app) {
    $nome = $request->get('nome');
    $cpf = $request->get('cpf');
    $endereco = $request->get('endereco');
    $data_nascimento = $request->get('data_nascimento');
    $sql = 'UPDATE funcionario
       SET  cpf = ' . $cpf . ',
            nome = \'' . $nome . '\',
            endereco = \'' . $endereco . '\',
            data_nascimento = \'' . $data_nascimento . '\'
      where id=' . $id;
    $st = $app['pdo']->prepare($sql);
    $st->execute();

    //return new Response($sql, 201);
    return $app->redirect("/editar/$id");
});

//criat novo registro
$app->post('/salvar/', function (Request $request) use ($app) {
    $nome = $request->get('nome');
    $cpf = $request->get('cpf');
    $endereco = $request->get('endereco');
    $data_nascimento = $request->get('data_nascimento');
    $sql = 'INSERT into funcionario (cpf,nome,endereco,data_nascimento) VALUES (' . $cpf . ',\'' . $nome . '\',\'' . $endereco . '\',\'' . $data_nascimento . '\'';
    $st = $app['pdo']->prepare($sql);
    $st->execute();
    $id = $app['pdo']->lastInsertId();

    return new Response($sql, 201);
    //return $app->redirect("/editar/$id");
});


$app->run();
