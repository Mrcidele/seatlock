/**
 * Relógio sincronizado com o servidor. O contador regressivo usa o
 * expires_at do servidor, então precisa do "agora" do servidor também:
 * o relógio local do usuário pode estar adiantado ou atrasado.
 */
let offsetMs = 0;

/**
 * Registra o horário informado pelo servidor numa resposta. Desconta metade
 * do tempo de ida e volta para aproximar o instante em que ele foi gerado.
 */
export function syncWithServer(serverTime: string, requestStartedAt: number, responseReceivedAt: number = Date.now()): void {
    const server = Date.parse(serverTime);

    if (Number.isNaN(server)) {
        return;
    }

    const midpoint = requestStartedAt + (responseReceivedAt - requestStartedAt) / 2;
    offsetMs = server - midpoint;
}

export function serverNow(): number {
    return Date.now() + offsetMs;
}

export function clockOffset(): number {
    return offsetMs;
}

export function resetClock(): void {
    offsetMs = 0;
}
