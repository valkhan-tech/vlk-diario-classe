/** Ilustração do teclado de piano com pauta; cada tecla branca leva a cor de uma nota (dó…si). */
export function ArteMusical({ className = '' }: { className?: string }) {
  const notas = ['var(--color-nota-1)', 'var(--color-nota-2)', 'var(--color-nota-3)', 'var(--color-nota-4)', 'var(--color-nota-5)', 'var(--color-nota-6)', 'var(--color-nota-7)'];
  return (
    <svg viewBox="0 0 330 170" fill="none" className={className} aria-hidden="true">
      <g stroke="currentColor" strokeOpacity=".4" strokeWidth="2"><path d="M10 22h310M10 38h310M10 54h310M10 70h310M10 86h310" /></g>
      <g fill="currentColor">
        <ellipse cx="62" cy="70" rx="11" ry="8" transform="rotate(-20 62 70)" /><rect x="71" y="18" width="3.5" height="54" />
        <ellipse cx="128" cy="54" rx="11" ry="8" transform="rotate(-20 128 54)" /><rect x="137" y="6" width="3.5" height="50" />
        <ellipse cx="196" cy="86" rx="11" ry="8" transform="rotate(-20 196 86)" /><rect x="205" y="30" width="3.5" height="58" />
        <ellipse cx="250" cy="70" rx="11" ry="8" transform="rotate(-20 250 70)" /><rect x="259" y="14" width="3.5" height="58" />
        <path d="M205 30l54-16v8l-54 16z" />
      </g>
      <rect x="10" y="108" width="310" height="58" rx="8" fill="#fff" fillOpacity=".78" />
      <g stroke="currentColor" strokeOpacity=".25"><path d="M54 108v58M98 108v58M142 108v58M186 108v58M230 108v58M274 108v58" /></g>
      {notas.map((cor, i) => <rect key={i} x={10 + i * 44} y="108" width={i === 6 ? 46 : 44} height="8" fill={cor} />)}
      <g fill="currentColor">
        {[42, 86, 174, 218, 262].map((x) => <rect key={x} x={x} y="108" width="24" height="34" rx="3" />)}
      </g>
    </svg>
  );
}

/** Pauta com notas, usada de fundo no login. */
export function PautaFundo({ className = '' }: { className?: string }) {
  return (
    <svg viewBox="0 0 560 220" fill="none" stroke="currentColor" strokeWidth="3" className={className} aria-hidden="true">
      <path d="M0 60h560M0 90h560M0 120h560M0 150h560M0 180h560" />
      {/* nota cheia: haste na borda direita do círculo (cx + r) */}
      <circle cx="120" cy="150" r="14" fill="currentColor" /><path d="M134 150V70" />
      {/* notas vazadas: mesma regra, haste em cx + r */}
      <circle cx="240" cy="120" r="14" /><path d="M254 120V40" />
      <circle cx="350" cy="90" r="14" /><path d="M364 90V10" />
    </svg>
  );
}
