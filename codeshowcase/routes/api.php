<?php
use App\Controller\UserController;
use App\Controller\ProjectController;

//Cadastro de usuário
$router->post(
    '/cadastro-user',
    [
        UserController::class,
        'createUser'
    ]
);

//Login de usuário
$router->post(
    '/login',
    [
        UserController::class,
        'login'
    ]
);

//Cadastro de desenvolvedor
$router->post(
    '/dev/cadastrar',
    [
        UserController::class,
        'createDev'
    ]
);

//Logout de usuário
$router->get(
    '/logout',
    [
        UserController::class,
        'logout'
    ]
);

//Rotas de projeto
$router->post(
    '/projetos/cadastrar',
     [
        ProjectController::class,
         'createProject'
    ]
);

$router->post(
    '/projetos/atualizar',
     [
        ProjectController::class,
         'updateProject'
    ]
);
 
// Deleção — ações destrutivas exigem POST + CSRF (verificação no controller)
$router->post(
    '/projetos/deletar',
     [
        ProjectController::class,
         'deleteProject'
    ]
);

$router->post(
    '/projetos/desativar',
     [
        ProjectController::class,
         'desativarProject'
    ]
);
?>