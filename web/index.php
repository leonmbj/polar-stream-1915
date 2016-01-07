<?php

require('../vendor/autoload.php');
include ('funcoes.php');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

// inicial
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

// ------------------------- FUNCIONÁRIO -----------------------------

// tela editar registro funcionario
$app->get('/editar/{id}', function ($id) use ($app) {
    $st = $app['pdo']->prepare('SELECT * FROM funcionario where id=' . $id);
    $st->execute();

    $names = array();
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $app['monolog']->addDebug('Row ' . $row['nome']);
        $names[] = $row;
    }

    //gerar lista de dependentes
    $st2 = $app['pdo']->prepare('SELECT * FROM dependente where funcionario_id=' . $id);
    $st2->execute();

    $names2 = array();
    while ($row2 = $st2->fetch(PDO::FETCH_ASSOC)) {
        $app['monolog']->addDebug('Row ' . $row2['nome']);
        $names2[] = $row2;
    }

    return $app['twig']->render('editar.twig', array(
        'names' => $names, 'names2' => $names2, 'id' => $id
    ));
});

// tela adicionar novo funcionario
$app->get('/editar/', function () use ($app) {
    return $app['twig']->render('criar.twig');
});


// salvar registro editado funcionario

$app->post('/salvar/{id}', function (Request $request, $id) use ($app) {
    $nome = $request->get('nome');
    $cpf = $request->get('cpf');
    $endereco = $request->get('endereco');
    $data_nascimento = $request->get('data_nascimento');

    //validacoes
    if (!(valida_cpf($cpf))){
        return new Response('CPF inválido<br><br><a href="/editar/'.$id.'">Voltar</a> ', 201);
    }
    if (!(validaData2($data_nascimento))){
        return new Response('Data inválida<br><br><a href="/editar/'.$id.'">Voltar</a> ', 201);
    }

    //update
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

//criar novo registro funcionario
$app->post('/salvar/', function (Request $request) use ($app) {
    $nome = $request->get('nome');
    $cpf = $request->get('cpf');
    $endereco = $request->get('endereco');
    $data_nascimento = $request->get('data_nascimento');

    //validacoes
    if (!(valida_cpf($cpf))){
        return new Response('CPF inválido<br><br><a href="/editar/">Voltar</a> ', 201);
    }
    if (!(validaData2($data_nascimento))){
        return new Response('Data inválida<br><br><a href="/editar/">Voltar</a> ', 201);
    }

    //insert
    $sql = 'INSERT into funcionario (cpf,nome,endereco,data_nascimento)
            VALUES (' . $cpf . ',\'' . $nome . '\',\'' . $endereco . '\',\'' . $data_nascimento . '\');';
    $st = $app['pdo']->prepare($sql);
    $st->execute();


    //return new Response($sql, 201);
    return $app->redirect("/itriad/");
});


// apagar registro funcionario
$app->get('/apagar/{id}', function ($id) use ($app) {
    $st = $app['pdo']->prepare('DELETE FROM funcionario where id=' . $id);
    $st->execute();

    return $app->redirect("/itriad/");
});



// ------------------------- DEPENDENTE -----------------------------

// tela editar registro DEPENDENTE
$app->get('/editar_dependente/{id}', function ($id) use ($app) {
    $st = $app['pdo']->prepare('SELECT * FROM dependente where id=' . $id);
    $st->execute();

    $names = array();
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $app['monolog']->addDebug('Row ' . $row['nome']);
        $names[] = $row;
    }

    //carregar parentesco
    $conjuge = '';
    $filho = '';
    if ($names[0]['parentesco']=='Cônjuge'){
        $conjuge = 'selected';
    } else {
        $filho = 'selected';
    }


    return $app['twig']->render('editar_dependente.twig', array(
        'names' => $names, 'funcionario_id' => $names[0]['funcionario_id'], 'filho' => $filho, 'conjuge' => $conjuge
    ));
});

// tela adicionar novo DEPENDENTE
$app->get('/criar_dependente/{funcionario_id}', function ($funcionario_id) use ($app) {
    return $app['twig']->render('criar_dependente.twig', array(
        'funcionario_id' => $funcionario_id
    ));
});


// salvar registro editado DEPENDENTE

$app->post('/salvar_dependente/{id}', function (Request $request, $id) use ($app) {
    $nome = $request->get('nome');
    $parentesco = $request->get('parentesco');
    $data_nascimento = $request->get('data_nascimento');

    //validacoes
    if (!(validaData2($data_nascimento))){
        return new Response('Data inválida<br><br><a href="/editar_dependente/'.$id.'">Voltar</a> ', 201);
    }

    //update
    $sql = 'UPDATE dependente
       SET
            nome = \'' . $nome . '\',
            parentesco = \'' . $parentesco . '\',
            data_nascimento = \'' . $data_nascimento . '\'
      where id=' . $id;
    $st = $app['pdo']->prepare($sql);
    $st->execute();

    //return new Response($sql, 201);
    return $app->redirect("/editar_dependente/$id");
});

//criar novo registro DEPENDENTE
$app->post('/salvar_dependente/', function (Request $request) use ($app) {
    $nome = $request->get('nome');
    $funcionario_id= $request->get('funcionario_id');
    $parentesco = $request->get('parentesco');
    $data_nascimento = $request->get('data_nascimento');

    //validacoes
    if (!(validaData2($data_nascimento))){
        return new Response('Data inválida<br><br><a href="/criar_dependente/'.$funcionario_id.'">Voltar</a> ', 201);
    }

    $sql = 'INSERT into dependente (funcionario_id,nome,parentesco,data_nascimento)
            VALUES (' . $funcionario_id . ',\'' . $nome . '\',\'' . $parentesco . '\',\'' . $data_nascimento . '\');';
    $st = $app['pdo']->prepare($sql);
    $st->execute();

    //return new Response($sql, 201);
    return $app->redirect("/editar/$funcionario_id");
});


// apagar registro DEPENDENTE
$app->get('/apagar_dependente/{id}', function ($id) use ($app) {
    $st = $app['pdo']->prepare('DELETE FROM dependente where id=' . $id);
    $st->execute();

    return $app->redirect("/itriad/");
});


$app->run();
