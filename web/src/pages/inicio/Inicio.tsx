import { Cartao, Titulo } from '../../components/ui';
import { useAuth } from '../../auth/AuthContext';

// Será substituída pelo painel completo na entrega E6.
export function PaginaInicio() {
  const { usuario } = useAuth();
  return (
    <>
      <Titulo>Olá, {usuario?.nome}</Titulo>
      <Cartao>Bem-vinda ao Diário de Classe.</Cartao>
    </>
  );
}
