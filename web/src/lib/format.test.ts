import { describe, expect, it } from 'vitest';
import { data, dataHora, moeda, percentual, DIAS_SEMANA } from './format';

describe('format', () => {
  it('formata moeda em reais', () => {
    expect(moeda(1234.5).replace(/\s/g, ' ')).toBe('R$ 1.234,50');
    expect(moeda(null).replace(/\s/g, ' ')).toBe('R$ 0,00');
    expect(moeda('150.00').replace(/\s/g, ' ')).toBe('R$ 150,00');
  });

  it('formata datas do banco (com ou sem hora)', () => {
    expect(data('2026-11-17')).toBe('17/11/2026');
    expect(data('2026-11-17 09:30:00')).toBe('17/11/2026');
    expect(dataHora('2026-11-17 09:30:00')).toBe('17/11/2026 09:30');
    expect(data(null)).toBe('—');
  });

  it('formata percentual com uma casa e traço quando não há base', () => {
    expect(percentual(11.6)).toBe('11,6%');
    expect(percentual(100)).toBe('100%');
    expect(percentual(null)).toBe('—');
  });

  it('tem os 7 dias da semana começando no domingo', () => {
    expect(DIAS_SEMANA).toHaveLength(7);
    expect(DIAS_SEMANA[0]).toBe('Domingo');
    expect(DIAS_SEMANA[6]).toBe('Sábado');
  });
});
