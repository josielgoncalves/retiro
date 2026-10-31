export function formatCents(cents: number): string {
  return (cents / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });
}

const CIVIL_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;
const BRAZILIAN_DATE_PATTERN = /^(\d{2})\/(\d{2})\/(\d{4})$/;
const SAO_PAULO_TIME_ZONE = 'America/Sao_Paulo';

/** Formats a civil date without converting it to a UTC instant. */
export function formatDateBR(value: string | null | undefined): string {
  if (!value) return '—';
  const match = CIVIL_DATE_PATTERN.exec(value);
  if (!match) return value;

  const [, year, month, day] = match;
  return `${day}/${month}/${year}`;
}

/** Converts a Brazilian civil date to the API's YYYY-MM-DD representation. */
export function parseDateBR(value: string): string | null {
  const match = BRAZILIAN_DATE_PATTERN.exec(value.trim());
  if (!match) return null;

  const [, day, month, year] = match;
  const candidate = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)));
  if (
    candidate.getUTCFullYear() !== Number(year)
    || candidate.getUTCMonth() !== Number(month) - 1
    || candidate.getUTCDate() !== Number(day)
  ) return null;

  return `${year}-${month}-${day}`;
}

/** Formats a real instant in the portal's business timezone. */
export function formatDateTimeBR(value: string | null | undefined): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: SAO_PAULO_TIME_ZONE,
  }).format(date);
}

/** Returns today's date in the business timezone, without a UTC day shift. */
export function todayDateBR(): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: SAO_PAULO_TIME_ZONE,
  }).formatToParts(new Date());
  const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === type)?.value ?? '';
  return `${part('year')}-${part('month')}-${part('day')}`;
}

// Backwards-compatible alias while existing screens migrate to the explicit name.
export const formatDate = formatDateBR;

const methodLabels: Record<string, string> = {
  pix: 'Pix',
  cash: 'Dinheiro',
  transfer: 'Transferência',
  other: 'Outro',
};

export function methodLabel(method: string): string {
  return methodLabels[method] ?? method;
}

const installmentStatusLabels: Record<string, string> = {
  pending: 'Pendente',
  partial: 'Parcialmente paga',
  paid: 'Quitada',
  overdue: 'Vencida',
};

export function installmentStatusLabel(status: string): string {
  return installmentStatusLabels[status] ?? status;
}

export function installmentStatusColor(status: string): string {
  switch (status) {
    case 'paid':
      return 'positive';
    case 'overdue':
      return 'negative';
    case 'partial':
      return 'warning';
    default:
      return 'grey-6';
  }
}

export function normalizePhoneForWhatsApp(phone: string): string {
  const digits = phone.replace(/\D/g, '');

  if (digits.startsWith('55')) {
    return digits;
  }

  return `55${digits}`;
}
