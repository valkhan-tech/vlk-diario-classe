<?php

declare(strict_types=1);

namespace App\Aplicacao\Acesso;

use App\Dominio\Acesso\SenhaPolitica;
use App\Http\Erros\Conflito;
use App\Http\Erros\ErroValidacao;
use App\Infra\Repositorios\SessaoRepositorio;
use App\Infra\Repositorios\UsuarioRepositorio;

final class UsuarioServico
{
    public function __construct(
        private readonly UsuarioRepositorio $usuarios,
        private readonly SessaoRepositorio $sessoes,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        return array_map(UsuarioRepositorio::publico(...), $this->usuarios->listar());
    }

    /** @return array<string,mixed> */
    public function obter(int $id): array
    {
        return UsuarioRepositorio::publico($this->usuarios->obter($id));
    }

    /**
     * @param array{nome:string,email:string,perfil:string,senha:string} $dados
     * @return array<string,mixed>
     */
    public function criar(array $dados): array
    {
        $this->exigirSenha($dados['senha']);
        $email = mb_strtolower($dados['email']);
        if ($this->usuarios->buscarPorEmail($email) !== null) {
            throw new Conflito('Já existe um usuário com este e-mail.');
        }
        $criado = $this->usuarios->inserir([
            'nome' => $dados['nome'],
            'email' => $email,
            'perfil' => $dados['perfil'],
            'senha_hash' => password_hash($dados['senha'], PASSWORD_DEFAULT),
            'ativo' => 1,
            'troca_senha_obrigatoria' => 1,
        ]);
        return UsuarioRepositorio::publico($criado);
    }

    /**
     * @param array<string,mixed> $dados nome, email, perfil, ativo
     * @return array<string,mixed>
     */
    public function atualizar(int $id, array $dados, int $usuarioLogadoId): array
    {
        $atual = $this->usuarios->obter($id);
        if (isset($dados['email'])) {
            $dados['email'] = mb_strtolower((string) $dados['email']);
            $outro = $this->usuarios->buscarPorEmail($dados['email']);
            if ($outro !== null && (int) $outro['id'] !== $id) {
                throw new Conflito('Já existe um usuário com este e-mail.');
            }
        }
        $perdeAdmin = ($atual['perfil'] === 'admin' && (int) $atual['ativo'] === 1)
            && ((isset($dados['perfil']) && $dados['perfil'] !== 'admin') || (isset($dados['ativo']) && !$dados['ativo']));
        if ($perdeAdmin) {
            $this->garantirOutroAdmin($id, $usuarioLogadoId);
        }
        if (isset($dados['ativo'])) {
            $dados['ativo'] = $dados['ativo'] ? 1 : 0;
        }
        $atualizado = $this->usuarios->atualizar($id, $dados);
        if (isset($dados['ativo']) && $dados['ativo'] === 0) {
            $this->sessoes->excluirDoUsuario($id);
        }
        return UsuarioRepositorio::publico($atualizado);
    }

    public function definirSenha(int $id, string $senha): void
    {
        $this->exigirSenha($senha);
        $this->usuarios->atualizar($id, [
            'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
            'troca_senha_obrigatoria' => 1,
        ]);
        $this->sessoes->excluirDoUsuario($id);
    }

    public function excluir(int $id, int $usuarioLogadoId): void
    {
        $atual = $this->usuarios->obter($id);
        if ($atual['perfil'] === 'admin' && (int) $atual['ativo'] === 1) {
            $this->garantirOutroAdmin($id, $usuarioLogadoId);
        }
        $this->usuarios->excluir($id);
        $this->sessoes->excluirDoUsuario($id);
    }

    private function garantirOutroAdmin(int $id, int $usuarioLogadoId): void
    {
        if ($id === $usuarioLogadoId) {
            throw new Conflito('Você não pode remover seu próprio acesso de administrador.');
        }
        if ($this->usuarios->contarAdminsAtivos($id) < 1) {
            throw new Conflito('Deve existir pelo menos um administrador ativo.');
        }
    }

    private function exigirSenha(string $senha): void
    {
        if (!SenhaPolitica::valida($senha)) {
            throw new ErroValidacao(['senha' => SenhaPolitica::MENSAGEM]);
        }
    }
}
