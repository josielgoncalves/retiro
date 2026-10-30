import { describe, it, expect } from 'vitest';
import { formatCents, formatDate, methodLabel, installmentStatusLabel, normalizePhoneForWhatsApp } from '@/utils/format';

describe('formatCents', () => {
  it('formats cents as BRL currency', () => {
    expect(formatCents(123456)).toBe('R$ 1.234,56');
  });

  it('formats zero correctly', () => {
    expect(formatCents(0)).toBe('R$ 0,00');
  });
});

describe('formatDate', () => {
  it('formats an ISO date as dd/MM/yyyy', () => {
    expect(formatDate('2027-03-10')).toBe('10/03/2027');
  });

  it('returns a dash for null/undefined', () => {
    expect(formatDate(null)).toBe('—');
    expect(formatDate(undefined)).toBe('—');
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
    expect(installmentStatusLabel('paid')).toBe('Paga');
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
