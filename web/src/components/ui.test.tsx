import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Aviso, Botao, Campo, Confirmar, Tabela, Td } from './ui';

describe('componentes de UI', () => {
  it('Campo associa o rótulo ao input e anuncia o erro', () => {
    render(<Campo rotulo="Nome" erro="Informe o nome." name="nome" />);
    const input = screen.getByLabelText('Nome');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('alert')).toHaveTextContent('Informe o nome.');
  });

  it('Aviso de erro usa role=alert e os demais role=status', () => {
    const { rerender } = render(<Aviso>falhou</Aviso>);
    expect(screen.getByRole('alert')).toBeInTheDocument();
    rerender(<Aviso tipo="sucesso">ok</Aviso>);
    expect(screen.getByRole('status')).toHaveTextContent('ok');
  });

  it('Botão em carregamento fica desabilitado e não dispara o clique', async () => {
    const aoClicar = vi.fn();
    render(<Botao carregando onClick={aoClicar}>Salvar</Botao>);
    const botao = screen.getByRole('button');
    expect(botao).toBeDisabled();
    await userEvent.click(botao);
    expect(aoClicar).not.toHaveBeenCalled();
  });

  it('Tabela tem rótulo acessível e cabeçalhos com escopo de coluna', () => {
    render(<Tabela rotulo="Alunos" cabecalhos={['Nome']}><tr><Td>Ana</Td></tr></Tabela>);
    expect(screen.getByRole('table', { name: 'Alunos' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Nome' })).toHaveAttribute('scope', 'col');
  });

  it('Confirmar só executa a ação depois do botão de confirmação', async () => {
    const aoConfirmar = vi.fn();
    render(<Confirmar aberto aoFechar={() => {}} aoConfirmar={aoConfirmar} titulo="Excluir" texto="Tem certeza?" rotuloConfirmar="Excluir" />);
    expect(aoConfirmar).not.toHaveBeenCalled();
    await userEvent.click(screen.getByRole('button', { name: 'Excluir' }));
    expect(aoConfirmar).toHaveBeenCalledTimes(1);
  });
});
