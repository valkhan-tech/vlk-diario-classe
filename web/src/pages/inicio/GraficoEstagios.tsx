import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

/** Carregado sob demanda (Recharts é pesado e só a tela inicial usa). */
export default function GraficoEstagios({ dados }: { dados: { rotulo: string; quantidade: number }[] }) {
  return (
    <div role="img" aria-label="Gráfico de barras com a quantidade de alunos por estágio" style={{ width: '100%', height: 280 }}>
      <ResponsiveContainer>
        <BarChart data={dados} layout="vertical" margin={{ left: 30 }}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis type="number" allowDecimals={false} />
          <YAxis type="category" dataKey="rotulo" width={130} />
          <Tooltip />
          <Bar dataKey="quantidade" name="Alunos" fill="#4f46e5" isAnimationActive={false} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
