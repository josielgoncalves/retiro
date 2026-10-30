export function formatCents(cents: number): string {
  return (cents / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  });
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  const date = new Date(value);
  return date.toLocaleDateString('pt-BR');
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—';
  const date = new Date(value);
  return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

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
  partial: 'Parcial',
  paid: 'Paga',
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
