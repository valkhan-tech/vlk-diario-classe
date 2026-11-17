import logo from '../assets/logo.png';
import logo2x from '../assets/logo@2x.png';

/** Logotipo do Diário de Classe (caderno com nota musical). `alt` vazio quando o nome já aparece ao lado. */
export function Logo({ tamanho = 40, alt = '', className = '' }: { tamanho?: number; alt?: string; className?: string }) {
  return (
    <img src={logo} srcSet={`${logo} 1x, ${logo2x} 2x`} width={tamanho} height={tamanho} alt={alt} className={`shrink-0 select-none object-contain ${className}`} draggable={false} />
  );
}
