# Regras financeiras

Toda a lógica descrita aqui está implementada em `apps/api/app/Services/FinancialService.php` e testada em `apps/api/tests/Feature/Financial/`. Nenhum controller ou componente de frontend calcula totais, saldos ou status por conta própria.

## Modalidade fixa

- O admin informa o valor total e as datas de vencimento das parcelas; a quantidade de parcelas é implícita (uma por data informada).
- O valor de cada parcela é calculado automaticamente: `distributeInstallmentAmounts()` divide o total pelo número de parcelas e distribui o resto da divisão (em centavos) de forma determinística — as primeiras N parcelas recebem +1 centavo, onde N é o resto. Exemplo: R$ 1.000,00 em 3 parcelas → R$ 333,34 + R$ 333,33 + R$ 333,33 (soma exata, sem perda de centavos).
- Pagamento parcial é permitido.
- Um único pagamento pode quitar várias parcelas de uma vez (aloca na mais antiga não quitada primeiro, até o valor acabar).
- Várias pagamentos podem ser necessários para quitar uma única parcela.
- Antecipação: o admin pode escolher explicitamente em quais parcelas um pagamento deve ser alocado (`installment_ids`), permitindo pagar uma parcela futura antes de uma mais antiga.
- Sem escolha explícita, a alocação é automática: parcela mais antiga não quitada primeiro (por `due_date`, depois por `installment_number`).
- Uma alocação nunca pode exceder o saldo da parcela (validado por linha, com lock).
- Pagamentos de inscrições fixas precisam ser **totalmente** alocados — não é permitido sobrar valor sem parcela correspondente.

### Regeneração de parcelas

- O admin pode alterar simultaneamente a quantidade total, a data inicial e a periodicidade das parcelas.
- A operação exige uma prévia antes da confirmação e informa valores e vencimentos resultantes.
- Parcelas com qualquer histórico de alocação de pagamento, inclusive de pagamento posteriormente estornado, não são apagadas. Elas são preservadas e contabilizadas na quantidade total escolhida.
- As parcelas sem vínculo com pagamentos são substituídas, e o valor restante do contrato é redistribuído exatamente entre as novas parcelas, sem perda de centavos.
- A quantidade escolhida não pode ser menor que a quantidade de parcelas que precisam ser preservadas.
- A confirmação ocorre em transação, com lock da inscrição, e registra em auditoria as parcelas removidas, preservadas e criadas.

## Modalidade flexível

- O admin informa só o valor contratado total.
- Nenhuma parcela é gerada.
- Pagamentos de qualquer valor e em qualquer data são aceitos, sem alocação (não existe `payment_allocations` para essas inscrições).
- Não existe "parcelas quitadas"; a tela mostra percentual quitado (`total pago / total contratado`).
- `installment_ids` no payload de pagamento é rejeitado para inscrições flexíveis (não faz sentido alocar numa parcela inexistente).

## Regras comuns a ambas as modalidades

- Todo valor é um inteiro de centavos (`BIGINT`), nunca `float`.
- Pagamento de valor zero ou negativo é rejeitado (`422`).
- Pagamento que excederia o saldo restante da inscrição é rejeitado (`422`) — **validado no backend, com lock transacional**, nunca confiando em cálculo do frontend.
- Um pagamento confirmado nunca é editado. Para corrigir, o fluxo é: estornar (`POST /payments/{id}/reverse`, exige `reason`) e lançar um novo pagamento correto.
- Estorno marca `reversed_at`/`reversed_by`/`reversal_reason`; a linha nunca é apagada, e as alocações associadas também são preservadas (só deixam de contar nos totais, via `whereNull('reversed_at')` nas somas).
- Datas retroativas são permitidas: `paid_at` é a data informada (pode ser passada); `created_at` é sempre o instante real do lançamento no sistema.
- Alterar a modalidade de pagamento ou o valor contratado depois que a inscrição já tem pagamentos ativos é bloqueado com uma mensagem explicando o motivo (`assertFinancialsAreEditable()`). Não existe, neste MVP, um fluxo de "revisão financeira" para contornar isso — é uma limitação conhecida, documentada em `docs/IMPLEMENTATION_PROGRESS.md`.
- Inscrições não podem ser fisicamente excluídas pela API (não há rota `DELETE /enrollments/{id}`); cancelamento (`POST /enrollments/{id}/cancel`) muda o `status` para `cancelled` sem apagar nenhum histórico financeiro.

## Concorrência

`registerPayment()` abre uma transação de banco e aplica `SELECT ... FOR UPDATE` na linha da inscrição antes de ler o saldo e decidir se o novo pagamento cabe. Isso serializa requisições concorrentes para a mesma inscrição: se dois pagamentos que juntos excederiam o saldo chegarem "ao mesmo tempo", apenas um consegue o lock primeiro, grava, libera o lock — o segundo então lê o saldo já atualizado e é corretamente rejeitado.

Isso é testado com concorrência **real** (dois processos de sistema operacional separados, não apenas chamadas sequenciais simuladas em um único processo PHPUnit) em `ConcurrentPaymentTest` — ver o comentário no topo desse arquivo para o porquê dessa abordagem.

## Status de uma parcela

Calculado sempre em runtime por `Installment::status()`, nunca armazenado:

| Condição | Status |
|---|---|
| saldo da parcela = 0 | `paid` |
| saldo > 0, algo já pago, vencimento no futuro | `partial` |
| saldo > 0, algo já pago, vencimento no passado | `overdue` |
| saldo = valor total (nada pago), vencimento no futuro | `pending` |
| saldo = valor total (nada pago), vencimento no passado | `overdue` |
