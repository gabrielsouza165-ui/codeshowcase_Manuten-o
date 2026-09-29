<?php

namespace App\Controller;

use App\Config\Security;
use App\DAO\ProjectDAO;
use App\Models\ProjectEntity;

class ProjectController {

    // ── Views ────────────────────────────────────────────────

    public function index() {
        \App\Config\Security::initSession();
        $dao      = new ProjectDAO();
        $projects = $dao->readAtivos();
        $flashErro = $_SESSION['flash_erro'] ?? null;
        unset($_SESSION['flash_erro']);
        require __DIR__ . '/../Views/ProjectView.php';
    }

    public function cadastroView() {
        Security::requireRole(['DESENVOLVEDOR']);

        $dao        = new ProjectDAO();
        $categorias = $dao->getCategorias();
        require __DIR__ . '/../Views/CadastroProjectView.php';
    }

    public function meusProjetosView() {
        Security::requireRole(['DESENVOLVEDOR']);

        $devId = $_SESSION['user']['dev_id'] ?? null;
        if (empty($devId)) {
            $this->redirectComErro('Cadastro de desenvolvedor não encontrado.', '/home');
        }

        $dao      = new ProjectDAO();
        $projects = $dao->readByDevId((int) $devId);
        require __DIR__ . '/../Views/MeusProjetosView.php';
    }

    public function editView() {
        Security::requireRole(['DESENVOLVEDOR']);

        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            $this->redirectComErro('Projeto não encontrado. Verifique o link e tente novamente.');
        }

        $dao     = new ProjectDAO();
        $project = $dao->read((int) $_GET['id']);

        if (!$project) {
            $this->redirectComErro('Projeto não encontrado. Ele pode ter sido excluído ou o id é inválido.');
        }

        $this->assertProjectOwnership($project);

        $categorias = $dao->getCategorias();
        require __DIR__ . '/../Views/EditProjectView.php';
    }

    public function comprarView() {
        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            $this->redirectComErro('Projeto não encontrado. Verifique o link e tente novamente.');
        }

        $dao = new ProjectDAO();
        $project = $dao->read((int) $_GET['id']);


        if (!$project) {
            $this->redirectComErro('Projeto não encontrado. Ele pode ter sido excluído ou o id é inválido.');
        }

        require __DIR__ . '/../Views/ComprarProjectView.php';
    }
    
    public function pagamentoView() {
        if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
            $this->redirectComErro('Projeto não encontrado. Verifique o link e tente novamente.');
        }

        $dao = new ProjectDAO();
        $project = $dao->read((int) $_GET['id']);

        if (!$project) {
            $this->redirectComErro('Projeto não encontrado. Ele pode ter sido excluído ou o id é inválido.');
        }

        // 🔥 Busca TODAS as categorias (você já tem esse método)
        $categorias = $dao->getCategorias();

        // Passa as duas variáveis para a view
        require __DIR__ . '/../Views/PagamentoProjectView.php';
    }

    public function processarPagamento() {
    // Iniciar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /projetos');
        exit;
    }

    if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Token CSRF inválido.');
    }

    // Pega os dados
    $id = $_POST['id'] ?? '';
    $metodo = $_POST['metodo'] ?? '';

    if (empty($id) || !is_numeric($id)) {
        $_SESSION['flash_erro'] = 'Projeto não encontrado. Verifique o link e tente novamente.';
        header('Location: /projetos');
        exit;
    }

    $dao = new ProjectDAO();
    $project = $dao->read((int) $id);
    if (!$project) {
        $_SESSION['flash_erro'] = 'Projeto não encontrado. Ele pode ter sido excluído ou o id é inválido.';
        header('Location: /projetos');
        exit;
    }

    $metodosPermitidos = ['cartao', 'pix', 'boleto'];
    if (!in_array($metodo, $metodosPermitidos, true)) {
        $_SESSION['erro_pagamento'] = 'Selecione um método de pagamento válido.';
        header('Location: /projetos/pagamento?id=' . urlencode($id));
        exit;
    }

    // Validação do cartão (só se for cartão)
    if ($metodo === 'cartao') {
        $cartao  = trim($_POST['cartao'] ?? '');
        $validade = trim($_POST['validade'] ?? '');
        $cvv     = trim($_POST['cvv'] ?? '');

        if (empty($cartao) || empty($validade) || empty($cvv)) {
            $_SESSION['erro_pagamento'] = 'Preencha todos os dados do cartão.';
            header('Location: /projetos/pagamento?id=' . urlencode($id));
            exit;
        }

        $somenteDigitos = preg_replace('/\D/', '', $cartao);
        if (strlen($somenteDigitos) < 13 || strlen($somenteDigitos) > 19) {
            $_SESSION['erro_pagamento'] = 'Número do cartão inválido.';
            header('Location: /projetos/pagamento?id=' . urlencode($id));
            exit;
        }

        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $validade, $m)) {
            $_SESSION['erro_pagamento'] = 'Validade do cartão inválida. Use MM/AA.';
            header('Location: /projetos/pagamento?id=' . urlencode($id));
            exit;
        }

        $mes = (int) $m[1];
        $ano = (int) $m[2];
        if ($ano < 100) $ano += 2000;
        $fimMes = mktime(23, 59, 59, $mes + 1, 0, $ano);
        if ($fimMes < time()) {
            $_SESSION['erro_pagamento'] = 'Cartão expirado.';
            header('Location: /projetos/pagamento?id=' . urlencode($id));
            exit;
        }

        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            $_SESSION['erro_pagamento'] = 'CVV inválido.';
            header('Location: /projetos/pagamento?id=' . urlencode($id));
            exit;
        }
    }

    // Marca pagamento como processado para impedir bypass via GET /comprar/sucesso
    $_SESSION['pagamento_ok'] = (int) $id;
    unset($_SESSION['erro_pagamento']);
    header('Location: /comprar/sucesso');
    exit;
}

    public function sucessoView() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['pagamento_ok'])) {
            header('Location: /projetos');
            exit;
        }
        unset($_SESSION['pagamento_ok']);
        require __DIR__ . '/../Views/SucessoView.php';
    }

    // ── CRUD ─────────────────────────────────────────────────

    public function createProject() {
        Security::requireRole(['DESENVOLVEDOR']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /projetos/cadastro');
            exit;
        }

        if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }

        $nomeProjeto  = trim($_POST['nome'] ?? '');
        $titulo       = trim($_POST['titulo'] ?? '');
        $descricao    = trim($_POST['descricao'] ?? '');
        $funcionalidades = trim($_POST['funcionalidades'] ?? '');
        $url          = trim($_POST['url'] ?? '');
        $precoProjeto = $_POST['preco'] ?? '';
        $categoriaId  = $_POST['categoria_id'] ?? '';
        $ativo        = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nomeProjeto) || empty($titulo) || empty($descricao) || $precoProjeto === '' || empty($categoriaId)) {
            die("Todos os campos obrigatórios devem ser preenchidos.");
        }

        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->handleUpload($_FILES['image']);
            if (!$imagePath) die("Erro no upload da imagem.");
        }

        $devId = $_SESSION['user']['dev_id'] ?? null;
        if (empty($devId)) {
            die('Seu cadastro de desenvolvedor não foi encontrado.');
        }

        $project = new ProjectEntity(
            null,
            $url,
            $imagePath,
            $nomeProjeto,
            $titulo,
            $descricao,
            (float) $precoProjeto,
            (int) $categoriaId,
            $ativo,
            (int) $devId,
            $funcionalidades
        );

        $dao = new ProjectDAO();
        $dao->create($project);

        header('Location: /projetos');
        exit;
    }

    // Atualiza o projeto usando o ID numérico enviado pelo formulário
    public function updateProject() {
        Security::requireRole(['DESENVOLVEDOR']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /projetos');
            exit;
        }

        if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }

        if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
            $this->redirectComErro('Projeto não encontrado. Verifique o link e tente novamente.');
        }

        $dao     = new ProjectDAO();
        $project = $dao->read((int) $_POST['id']);

        if (!$project) {
            $this->redirectComErro('Projeto não encontrado. Ele pode ter sido excluído ou o id é inválido.');
        }

        $this->assertProjectOwnership($project);

        $project->setNomeProjeto(trim($_POST['nome'] ?? ''));
        $project->setTitulo(trim($_POST['titulo'] ?? ''));
        $project->setDescricao(trim($_POST['descricao'] ?? ''));
        $project->setFuncionalidades(trim($_POST['funcionalidades'] ?? ''));
        $project->setUrl(trim($_POST['url'] ?? ''));
        $project->setPreco((float) ($_POST['preco'] ?? 0));
        $project->setCategoriaId((int) ($_POST['categoria_id'] ?? 0));
        $project->setAtivo(isset($_POST['ativo']) ? 1 : 0);

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = $this->handleUpload($_FILES['image']);
            if ($image) $project->setImage($image);
        }

        $dao->update($project);

        header('Location: /projetos');
        exit;
    }

    public function desativarProject() {
        Security::requireRole(['DESENVOLVEDOR']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Método não permitido. Use POST.');
        }

        if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }

        if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
            $this->redirectComErro('Projeto não encontrado. Verifique o link e tente novamente.');
        }

        $dao = new ProjectDAO();
        $project = $dao->read((int) $_POST['id']);

        if (!$project) {
            $this->redirectComErro('Projeto não encontrado. Ele pode ter sido excluído ou o id é inválido.');
        }

        $this->assertProjectOwnership($project);
        $dao->desativar((int) $_POST['id']);

        header('Location: /projetos');
        exit;
    }

    public function deleteProject() {
        Security::requireRole(['DESENVOLVEDOR']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Método não permitido. Use POST.');
        }

        if (!Security::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }

        if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
            $this->redirectComErro('Projeto não encontrado. Verifique o link e tente novamente.');
        }

        $dao = new ProjectDAO();
        $project = $dao->read((int) $_POST['id']);

        if (!$project) {
            $this->redirectComErro('Projeto não encontrado. Ele pode ter sido excluído ou o id é inválido.');
        }

        $this->assertProjectOwnership($project);
        $dao->delete((int) $_POST['id']);

        header('Location: /projetos');
        exit;
    }

    private function assertProjectOwnership(ProjectEntity $project): void {
        Security::requireRole(['DESENVOLVEDOR']);

        $devId = $_SESSION['user']['dev_id'] ?? null;
        if ((int) $devId !== (int) $project->getDevId()) {
            $this->redirectComErro('Você não tem permissão para alterar este projeto.');
        }
    }

    private function redirectComErro(string $mensagem, string $destino = '/projetos'): void {
        Security::initSession();
        $_SESSION['flash_erro'] = $mensagem;
        header('Location: ' . $destino);
        exit;
    }

    // ── Helper de upload ─────────────────────────────────────

    private function handleUpload(array $file): string|false {
        $uploadDir = __DIR__ . '/../../public/assets/uploads/';
        // MIME real => extensões permitidas
        $allowedMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png'  => ['png'],
            'image/webp' => ['webp'],
            'image/gif'  => ['gif'],
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) return false;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) return false;

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);
        if (!isset($allowedMap[$realMime])) return false;
        if (!in_array($ext, $allowedMap[$realMime], true)) return false;

        // Garante que é imagem válida
        if (@getimagesize($file['tmp_name']) === false) return false;

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = 'proj_' . bin2hex(random_bytes(16)) . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) return false;

        return '/assets/uploads/' . $filename;
    }
}
?>