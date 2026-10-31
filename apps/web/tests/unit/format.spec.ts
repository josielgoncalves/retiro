import { describe, it, expect } from 'vitest';
import { formatCents, formatDateBR, formatDateTimeBR, parseDateBR, methodLabel, installmentStatusLabel, normalizePhoneForWhatsApp } from '@/utils/format';

describe('formatCents', () => {
  it('formats cents as BRL currency', () => {
    expect(formatCents(123456)).toBe('R$ 1.234,56');
  });

  it('formats zero correctly', () => {
    expect(formatCents(0)).toBe('R$ 0,00');
  });
});

describe('civil dates', () => {
  it('formats an ISO date as dd/MM/yyyy', () => {
    expect(formatDateBR('2026-11-15')).toBe('15/11/2026');
    expect(formatDateBR('2028-02-29')).toBe('29/02/2028');
    expect(formatDateBR('2027-01-31')).toBe('31/01/2027');
  });

  it('returns a dash for null/undefined', () => {
    expect(formatDateBR(null)).toBe('—');
    expect(formatDateBR(undefined)).toBe('—');
  });

  it('parses only valid Brazilian civil dates', () => {
    expect(parseDateBR('15/11/2026')).toBe('2026-11-15');
    expect(parseDateBR('29/02/2028')).toBe('2028-02-29');
    expect(parseDateBR('29/02/2027')).toBeNull();
  });
});

describe('formatDateTimeBR', () => {
  it('formats an instant in America/Sao_Paulo regardless of browser timezone', () => {
    expect(formatDateTimeBR('2026-11-15T02:30:00.000Z')).toBe('14/11/2026, 23:30');
  });
});

describe('methodLabel', () => {
  it('translates known payment methods to pt-BR', () => {
    expect(methodLabel('pix')).toBe('Pix');
    expect(methodLabel('cash')).toBe('Dinheiro');
    expect(methodLabel('transfer')).toBe('Transferência');
  });

  it('falls back to the raw value for unknown methods', () => {
    expect(methodLabel('crypto')).toBe('crypto');
  });
});

describe('installmentStatusLabel', () => {
  it('translates known installment statuses to pt-BR', () => {
    expect(installmentStatusLabel('paid')).toBe('Quitada');
    expect(installmentStatusLabel('partial')).toBe('Parcialmente paga');
    expect(installmentStatusLabel('overdue')).toBe('Vencida');
  });
});

describe('normalizePhoneForWhatsApp', () => {
  it('strips non-digit characters and keeps the country code', () => {
    expect(normalizePhoneForWhatsApp('+55 11 91234-5678')).toBe('5511912345678');
  });

  it('prepends the Brazil country code when missing', () => {
    expect(normalizePhoneForWhatsApp('(11) 91234-5678')).toBe('5511912345678');
  });
});
