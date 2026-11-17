import type { SVGProps } from 'react';

/** Ícones de traço (24×24), todos com currentColor. */
const CAMINHOS = {
  home: <><path d="M3 11l9-8 9 8" /><path d="M5 10v10h14V10" /><path d="M10 20v-6h4v6" /></>,
  users: <><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6" /><circle cx="17.5" cy="9" r="2.5" /><path d="M17 14c2.7 0 4.5 1.8 4.5 4.5" /></>,
  calendario: <><rect x="3" y="5" width="18" height="16" rx="3" /><path d="M3 10h18M8 3v4M16 3v4" /></>,
  nota: <><path d="M9 18V5l11-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="17" cy="16" r="3" /></>,
  carteira: <><path d="M3 7a2 2 0 012-2h12v4" /><rect x="3" y="7" width="18" height="13" rx="3" /><path d="M16 14h2" /></>,
  livro: <><path d="M4 4h6a3 3 0 013 3v13a2 2 0 00-2-2H4z" /><path d="M20 4h-6a3 3 0 00-3 3v13a2 2 0 012-2h7z" /></>,
  escudo: <><path d="M12 3l8 3v6c0 4.5-3.2 7.8-8 9-4.8-1.2-8-4.5-8-9V6z" /><path d="M9 12l2 2 4-4" /></>,
  lista: <><path d="M8 6h13M8 12h13M8 18h13" /><circle cx="3.5" cy="6" r="1" /><circle cx="3.5" cy="12" r="1" /><circle cx="3.5" cy="18" r="1" /></>,
  sino: <><path d="M6 16V11a6 6 0 1112 0v5l2 2H4z" /><path d="M10 21h4" /></>,
  busca: <><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></>,
  mais: <path d="M12 5v14M5 12h14" />,
  check: <path d="M5 12.5l4.5 4.5L19 7" />,
  x: <path d="M6 6l12 12M18 6L6 18" />,
  seta: <path d="M9 6l6 6-6 6" />,
  relogio: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  lixeira: <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />,
  editar: <path d="M4 20h4L19 9l-4-4L4 16z" />,
  imprimir: <><path d="M7 9V3h10v6" /><rect x="4" y="9" width="16" height="9" rx="2" /><path d="M7 14h10v7H7z" /></>,
  baixar: <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />,
  alerta: <><path d="M12 3l10 18H2z" /><path d="M12 10v5M12 18h.01" /></>,
  info: <><circle cx="12" cy="12" r="9" /><path d="M12 11v6M12 7.5h.01" /></>,
  sair: <><path d="M9 4H5a2 2 0 00-2 2v12a2 2 0 002 2h4" /><path d="M16 8l4 4-4 4M20 12H9" /></>,
  grade: <><rect x="3" y="3" width="7" height="7" rx="2" /><rect x="14" y="3" width="7" height="7" rx="2" /><rect x="3" y="14" width="7" height="7" rx="2" /><rect x="14" y="14" width="7" height="7" rx="2" /></>,
  menu: <path d="M4 7h16M4 12h16M4 17h16" />,
  tel: <path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z" />,
  piano: <><rect x="3" y="5" width="18" height="14" rx="2.5" /><path d="M8 13v6M12 13v6M16 13v6" /><path d="M7 5v8h2V5M11 5v8h2V5M15 5v8h2V5" fill="currentColor" /></>,
  chave: <><circle cx="8" cy="15" r="4" /><path d="M11 12l9-9M16 7l3 3" /></>,
} as const;

export type NomeIcone = keyof typeof CAMINHOS;

export function Icone({ nome, className = 'h-5 w-5', ...resto }: { nome: NomeIcone } & SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" className={`shrink-0 ${className}`} aria-hidden="true" {...resto}>
      {CAMINHOS[nome]}
    </svg>
  );
}
